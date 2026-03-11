<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vendor/autoload.php';

use GusApi\GusApi;
use GusApi\Exception\InvalidUserKeyException;
use GusApi\Exception\NotFoundException;

header('Content-Type: application/json');

$nip = $_GET['nip'] ?? $_POST['nip'] ?? '';
if (!preg_match('/^\d{10}$/', $nip)) {
    http_response_code(422);
    echo json_encode(['error' => 'Niepoprawny NIP. Oczekiwano 10 cyfr.']);
    exit;
}

$date = date('Y-m-d');
$debug = isset($_GET['debug']) && $_GET['debug'] === '1';

// GUS BIR1.1 – jedyne źródło danych
$result = fetchFromGusBir1($nip, $date, $debug);
if ($result !== null) {
    if (is_array($result) && isset($result['found'])) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    // debug zwrócił błąd
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
 * GUS BIR1.1 – wszystkie podmioty (przez GusApi z obsługą MTOM).
 */
function fetchFromGusBir1(string $nip, string $date, bool $debug = false): array|null
{
    $useTest = defined('GUS_BIR1_USE_TEST') && GUS_BIR1_USE_TEST;
    $env = $useTest ? 'dev' : 'prod';

    try {
        $gus = new GusApi(GUS_BIR1_KEY, $env);
        $gus->login();
        $reports = $gus->getByNip($nip);
        $gus->logout();
    } catch (InvalidUserKeyException $e) {
        error_log('GUS BIR1: Nieprawidłowy klucz API');
        return $debug ? ['found' => false, 'debug_error' => 'InvalidUserKeyException: Nieprawidłowy klucz API'] : null;
    } catch (NotFoundException $e) {
        return $debug ? ['found' => false, 'debug_error' => 'NotFoundException: GUS nie znalazł podmiotu'] : null;
    } catch (Throwable $e) {
        error_log('GUS BIR1: ' . $e->getMessage());
        return $debug ? [
            'found' => false,
            'debug_error' => $e->getMessage(),
            'debug_class' => get_class($e),
            'debug_file' => $e->getFile() . ':' . $e->getLine()
        ] : null;
    }

    if (empty($reports)) {
        return $debug ? ['found' => false, 'debug_error' => 'GUS zwrócił pustą listę'] : null;
    }

    $report = $reports[0];
    $company = mapGusReportToCompany($report, $nip);
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
        'raw' => $report->jsonSerialize()
    ];
}

function mapGusReportToCompany(\GusApi\SearchReport $report, string $nip): array
{
    return [
        'name' => $report->getName(),
        'nip' => $nip,
        'regon' => $report->getRegon(),
        'country' => 'Polska',
        'voivodeship' => $report->getProvince(),
        'county' => $report->getDistrict(),
        'municipality' => $report->getCommunity(),
        'city' => $report->getCity(),
        'postOffice' => $report->getPostCity(),
        'street' => $report->getStreet(),
        'buildingNumber' => $report->getPropertyNumber(),
        'apartmentNumber' => $report->getApartmentNumber(),
        'postalCode' => $report->getZipCode(),
        'type' => $report->getType(),
        'pkd' => $report->getRegon(),
        'workingAddress' => ''
    ];
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
