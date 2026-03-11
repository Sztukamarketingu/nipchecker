<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$nip = $_GET['nip'] ?? $_POST['nip'] ?? '';
if (!preg_match('/^\d{10}$/', $nip)) {
    http_response_code(422);
    echo json_encode(['error' => 'Niepoprawny NIP. Oczekiwano 10 cyfr.']);
    exit;
}

$date = date('Y-m-d');

// 1. MF (Biała Lista) – firmy z VAT
$mfResult = fetchFromMf($nip, $date);
if ($mfResult !== null) {
    echo json_encode($mfResult, JSON_UNESCAPED_UNICODE);
    exit;
}

// 2. GUS BIR1.1 – fallback (firmy bez VAT, wszystkie podmioty)
if (GUS_BIR1_KEY !== '') {
    $gusResult = fetchFromGusBir1($nip, $date);
    if ($gusResult !== null) {
        echo json_encode($gusResult, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

http_response_code(200);
echo json_encode([
    'found' => false,
    'nip' => $nip,
    'error' => 'Nie znaleziono podmiotu dla podanego NIP.'
], JSON_UNESCAPED_UNICODE);

/**
 * MF (Biała Lista) – firmy zarejestrowane jako podatnicy VAT.
 */
function fetchFromMf(string $nip, string $date): ?array
{
    $url = "https://wl-api.mf.gov.pl/api/search/nip/{$nip}?date={$date}";
    $httpCode = 0;
    $response = '';
    $requestError = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
        $response = (string)curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $requestError = (string)curl_error($ch);
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'header' => "Accept: application/json\r\nUser-Agent: Bitrix24-NIP-Checker\r\n"
            ]
        ]);
        $response = (string)@file_get_contents($url, false, $context);
        $headers = $http_response_header ?? [];
        if (!empty($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
            $httpCode = (int)$m[1];
        }
        if ($response === '') {
            $requestError = 'Brak odpowiedzi z API MF.';
        }
    }

    if ($response === '' && $requestError !== '') {
        http_response_code(502);
        echo json_encode(['error' => 'Błąd połączenia z API MF: ' . $requestError], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($httpCode >= 400) {
        if ($httpCode === 404) {
            return null;
        }
        http_response_code(502);
        echo json_encode(['error' => 'API MF zwróciło błąd HTTP ' . $httpCode], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        http_response_code(502);
        echo json_encode(['error' => 'Nieprawidłowa odpowiedź z API MF.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $subject = $decoded['result']['subject'] ?? null;
    if (!is_array($subject) || empty($subject)) {
        return null;
    }

    $name = trim((string)($subject['name'] ?? ''));
    $workingAddressRaw = $subject['workingAddress'] ?? $subject['residenceAddress'] ?? '';
    $workingAddress = trim((string)$workingAddressRaw);
    $statusVat = strtolower((string)($subject['statusVat'] ?? ''));
    $regon = trim((string)($subject['regon'] ?? ''));
    $address = parseAddress($workingAddress);

    return [
        'found' => true,
        'nip' => $nip,
        'vatActive' => in_array($statusVat, ['czynny', 'active'], true),
        'source' => 'mf',
        'requestDate' => $date,
        'company' => [
            'name' => $name,
            'nip' => $nip,
            'regon' => $regon,
            'country' => 'Polska',
            'voivodeship' => '',
            'county' => '',
            'municipality' => '',
            'city' => $address['city'],
            'postOffice' => '',
            'street' => $address['street'],
            'buildingNumber' => $address['buildingNumber'],
            'apartmentNumber' => $address['apartmentNumber'],
            'postalCode' => $address['postalCode'],
            'type' => '',
            'pkd' => $regon,
            'workingAddress' => $workingAddress
        ],
        'raw' => $decoded
    ];
}

/**
 * GUS BIR1.1 – wszystkie podmioty (fallback gdy MF zwraca pusty).
 */
function fetchFromGusBir1(string $nip, string $date): ?array
{
    $useTest = defined('GUS_BIR1_USE_TEST') && GUS_BIR1_USE_TEST;
    $wsdl = $useTest
        ? 'https://wyszukiwarkaregontest.stat.gov.pl/wsBIR/wsdl/UslugaBIRzewnPubl-ver11-test.wsdl'
        : 'https://wyszukiwarkaregon.stat.gov.pl/wsBIR/wsdl/UslugaBIRzewnPubl-ver11-prod.wsdl';

    try {
        $client = new SoapClient($wsdl, [
            'trace' => 0,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'connection_timeout' => 15,
            'soap_version' => SOAP_1_2,
        ]);
    } catch (Throwable $e) {
        error_log('GUS BIR1 SoapClient: ' . $e->getMessage());
        return null;
    }

    try {
        $loginResult = $client->Zaloguj(['pKluczUzytkownika' => GUS_BIR1_KEY]);
        $sessionId = $loginResult->ZalogujResult ?? '';
        if ($sessionId === '' || strlen($sessionId) < 10) {
            return null;
        }

        $header = new SoapHeader('http://tempuri.org/', 'sid', $sessionId);
        $client->__setSoapHeaders($header);

        $searchParams = ['pParametryWyszukiwania' => ['Nip' => $nip]];
        $searchResult = $client->DaneSzukajPodmioty($searchParams);
        $xmlResult = $searchResult->DaneSzukajPodmiotyResult ?? '';

        if ($xmlResult === '' || $xmlResult === '4') {
            return null;
        }

        $row = parseGusXmlResult($xmlResult);
        if ($row === null) {
            return null;
        }

        $company = mapGusRowToCompany($row, $nip);
        $company['workingAddress'] = buildWorkingAddress(
            $company['street'],
            $company['buildingNumber'],
            $company['apartmentNumber'],
            $company['postalCode'],
            $company['city']
        );

        return [
            'found' => true,
            'nip' => $nip,
            'vatActive' => false,
            'source' => 'gus',
            'requestDate' => $date,
            'company' => $company,
            'raw' => $row
        ];
    } catch (Throwable $e) {
        error_log('GUS BIR1: ' . $e->getMessage());
        return null;
    }
}

function mapGusRowToCompany(array $row, string $nip): array
{
    $get = function (array $keys) use ($row): string {
        foreach ($keys as $k) {
            $v = $row[$k] ?? null;
            if ($v !== null && $v !== '') {
                return trim((string)$v);
            }
        }
        return '';
    };

    return [
        'name' => $get(['Nazwa', 'nazwa']),
        'nip' => $nip,
        'regon' => $get(['Regon', 'regon']),
        'country' => 'Polska',
        'voivodeship' => $get(['Wojewodztwo', 'wojewodztwo']),
        'county' => $get(['Powiat', 'powiat']),
        'municipality' => $get(['Gmina', 'gmina']),
        'city' => $get(['Miejscowosc', 'miejscowosc']),
        'postOffice' => $get(['MiejscowoscPoczty', 'miejscowoscPoczty']),
        'street' => $get(['Ulica', 'ulica']),
        'buildingNumber' => $get(['NrNieruchomosci', 'nrNieruchomosci', 'numer_budynku']),
        'apartmentNumber' => $get(['NrLokalu', 'nrLokalu', 'numer_lokalu']),
        'postalCode' => $get(['KodPocztowy', 'kodPocztowy', 'kod_pocztowy']),
        'type' => $get(['Typ', 'typ']),
        'pkd' => $get(['Regon', 'regon']),
        'workingAddress' => ''
    ];
}

function parseGusXmlResult(string $xml): ?array
{
    $doc = @simplexml_load_string($xml);
    if ($doc === false) {
        return null;
    }

    $rows = null;
    $ns = $doc->getNamespaces(true);
    foreach ($ns as $uri) {
        $children = $doc->children($uri);
        if (isset($children->row)) {
            $rows = $children->row;
            break;
        }
        if (isset($children->Row)) {
            $rows = $children->Row;
            break;
        }
    }
    if ($rows === null) {
        $rows = $doc->row ?? $doc->Row ?? null;
    }

    if ($rows === null) {
        return null;
    }

    $first = $rows[0] ?? $rows;
    if ($first === null) {
        return null;
    }

    $out = [];
    foreach ((array)$first as $k => $v) {
        if (is_object($v) && $v instanceof SimpleXMLElement) {
            $out[$k] = (string)$v;
        } else {
            $out[$k] = is_scalar($v) ? $v : (string)$v;
        }
    }
    return $out;
}

function buildWorkingAddress(string $street, string $buildingNumber, string $apartmentNumber, string $postalCode, string $city): string
{
    $parts = [];
    if ($street !== '') {
        $parts[] = trim($street . ' ' . $buildingNumber . ($apartmentNumber !== '' ? '/' . $apartmentNumber : ''));
    }
    if ($postalCode !== '' || $city !== '') {
        $parts[] = trim($postalCode . ' ' . $city);
    }
    return implode(', ', $parts);
}

function parseAddress(string $workingAddress): array
{
    $result = [
        'street' => '',
        'buildingNumber' => '',
        'apartmentNumber' => '',
        'postalCode' => '',
        'city' => ''
    ];

    if ($workingAddress === '') {
        return $result;
    }

    $parts = array_map('trim', explode(',', $workingAddress, 2));
    $streetPart = $parts[0] ?? '';
    $cityPart = $parts[1] ?? '';

    if (preg_match('/^(.*?)(?:\s+(\d+[A-Za-z]?))(?:\/(\d+[A-Za-z]?))?$/u', $streetPart, $m)) {
        $result['street'] = trim($m[1]);
        $result['buildingNumber'] = trim($m[2] ?? '');
        $result['apartmentNumber'] = trim($m[3] ?? '');
    } else {
        $result['street'] = $streetPart;
    }

    if (preg_match('/(\d{2}-\d{3})\s+(.+)/u', $cityPart, $m)) {
        $result['postalCode'] = trim($m[1]);
        $result['city'] = trim($m[2]);
    } else {
        $result['city'] = $cityPart;
    }

    return $result;
}
