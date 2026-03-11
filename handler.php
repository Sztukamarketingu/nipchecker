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

// Tylko GUS BIR1.1 – jedyne źródło danych
if (GUS_BIR1_KEY === '') {
    http_response_code(500);
    echo json_encode(['error' => 'Brak klucza GUS BIR1. Skonfiguruj GUS_BIR1_KEY.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = fetchFromGusBir1($nip, $date);

if ($result !== null) {
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

http_response_code(200);
echo json_encode([
    'found' => false,
    'nip' => $nip,
    'error' => 'Nie znaleziono podmiotu dla podanego NIP.'
], JSON_UNESCAPED_UNICODE);

/**
 * Pobiera dane firmy z GUS BIR1.1 (SOAP).
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

/**
 * Mapuje wiersz XML GUS na obiekt company (wszystkie dostępne pola).
 */
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

/**
 * Parse GUS BIR1.1 XML result (root/row structure) into associative array.
 */
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
