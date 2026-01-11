<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OTR\DocketRepository;
use OTR\Uploads;
use OTR\Pdf\PdfBuilder;
use OTR\Security;
use OTR\ErrorHandler;
use OTR\AipCharts;

$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

// Initialize error handler
$logDir = $config['paths']['logs'] ?? __DIR__ . '/../storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0750, true);
}
ErrorHandler::register($logDir . '/error.log');

Security::startSecureSession();
Security::setSecurityHeaders();

// CSRF validation
$csrfToken = (string)($_POST['csrf_token'] ?? '');
if (!Security::validateCsrfToken($csrfToken)) {
    http_response_code(403);
    die('CSRF token validation failed.');
}

$repo = new DocketRepository($config);
$id = $repo->newId();

$uploadDir = rtrim($config['paths']['uploads'], '/') . "/{$id}";
Uploads::ensureDir($uploadDir);

function post(string $k): string {
    return trim((string)($_POST[$k] ?? ''));
}

// Validate required fields
$aircraftType = post('aircraft_type');
$registration = strtoupper(post('registration'));
$callsign = strtoupper(post('callsign'));
$departure = strtoupper(post('departure'));
$destination = strtoupper(post('destination'));
$alternates = post('alternates');
$etdLocal = post('etd_local');

// Validate aircraft type (max 20 chars)
if (strlen($aircraftType) > 20 || strlen($aircraftType) === 0) {
    http_response_code(400);
    die('Invalid aircraft type.');
}

// Validate registration format
if (!Security::validateRegistration($registration)) {
    http_response_code(400);
    die('Invalid registration format.');
}

// Validate callsign (max 10 chars, alphanumeric)
if ($callsign !== '' && (strlen($callsign) > 10 || !ctype_alnum($callsign))) {
    http_response_code(400);
    die('Invalid callsign format.');
}

// Validate ICAO codes
if (!Security::validateIcaoCode($departure)) {
    http_response_code(400);
    die('Invalid departure ICAO code.');
}

if (!Security::validateIcaoCode($destination)) {
    http_response_code(400);
    die('Invalid destination ICAO code.');
}

// Process alternates
$alternatesArray = array_values(array_filter(array_map(
    static fn($x) => strtoupper(trim($x)),
    explode(',', $alternates)
)));

// Validate each alternate ICAO code
foreach ($alternatesArray as $alt) {
    if (!Security::validateIcaoCode($alt)) {
        http_response_code(400);
        die('Invalid alternate ICAO code: ' . htmlspecialchars($alt));
    }
}

// Limit alternates to 5
if (count($alternatesArray) > 5) {
    http_response_code(400);
    die('Too many alternates (max 5).');
}

$flight = [
    'aircraft_type' => $aircraftType,
    'registration'  => $registration,
    'callsign'      => $callsign,
    'departure'     => $departure,
    'destination'   => $destination,
    'alternates'    => $alternatesArray,
    'etd_local'     => $etdLocal,
];

$requiredFiles = [
    'accepted_flight_plan' => 'accepted_flight_plan.pdf',
    'mass_balance'         => 'mass_balance.pdf',
    'performance'          => 'performance.pdf',
    'notams'               => 'notams.pdf',
];

$optionalFiles = [
    'operational_flight_plan' => 'operational_flight_plan.pdf',
    'sigwx'                   => 'sigwx.pdf',
    'winds'                   => 'winds.pdf',
    'metar_taf'               => 'metar_taf.pdf',
];

$stored = [];

$uploadCfg = $config['uploads'];

// Helper function to get API file info
function getApiFileInfo(string $field): ?array {
    $apiKey = $_POST["{$field}_api_key"] ?? '';
    if (empty($apiKey) || !isset($_SESSION['api_files'][$apiKey])) {
        return null;
    }
    return [
        'key' => $apiKey,
        'path' => $_SESSION['api_files'][$apiKey],
    ];
}

// Helper function to process file (API or upload)
function processFile(string $field, string $filename, string $uploadDir, array $uploadCfg, bool $required): ?string {
    // Check if file was fetched via API
    $apiInfo = getApiFileInfo($field);
    if ($apiInfo !== null) {
        $apiFilePath = $apiInfo['path'];
        if (is_file($apiFilePath)) {
            $dest = "{$uploadDir}/{$filename}";
            copy($apiFilePath, $dest);
            chmod($dest, 0640);
            // Clean up temp file
            unlink($apiFilePath);
            unset($_SESSION['api_files'][$apiInfo['key']]);
            return $dest;
        }
    }
    
    // Otherwise, handle as regular upload
    $file = $_FILES[$field] ?? null;
    
    if ($required) {
        if (!is_array($file) || !Uploads::isPdfUpload($file, $uploadCfg)) {
            http_response_code(400);
            echo "Missing or invalid required upload: {$field}";
            exit;
        }
    } else {
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (!Uploads::isPdfUpload($file, $uploadCfg)) {
            http_response_code(400);
            echo "Invalid optional upload (must be PDF): {$field}";
            exit;
        }
    }
    
    $dest = "{$uploadDir}/{$filename}";
    Uploads::moveUploadedPdf($file, $dest);
    return $dest;
}

foreach ($requiredFiles as $field => $filename) {
    $stored[$field] = processFile($field, $filename, $uploadDir, $uploadCfg, true);
}

foreach ($optionalFiles as $field => $filename) {
    $stored[$field] = processFile($field, $filename, $uploadDir, $uploadCfg, false);
}

// Gather charts from AIP storage
$aipBasePath = $config['paths']['aip'] ?? __DIR__ . '/../storage/aip';

// Departure charts
$departureCharts = AipCharts::getChartPack($departure, $aipBasePath);
$stored['charts_departure'] = null;
if (!empty($departureCharts)) {
    $chartsDeparturePath = "{$uploadDir}/charts_departure.pdf";
    if (AipCharts::mergeCharts($departureCharts, $chartsDeparturePath)) {
        $stored['charts_departure'] = $chartsDeparturePath;
    }
}

// Destination charts
$destinationCharts = AipCharts::getChartPack($destination, $aipBasePath);
$stored['charts_destination'] = null;
if (!empty($destinationCharts)) {
    $chartsDestinationPath = "{$uploadDir}/charts_destination.pdf";
    if (AipCharts::mergeCharts($destinationCharts, $chartsDestinationPath)) {
        $stored['charts_destination'] = $chartsDestinationPath;
    }
}

// Alternates charts
$stored['charts_alternates'] = null;
if (!empty($alternatesArray)) {
    $allAlternateCharts = [];
    foreach ($alternatesArray as $altIcao) {
        $altCharts = AipCharts::getChartPack($altIcao, $aipBasePath);
        $allAlternateCharts = array_merge($allAlternateCharts, $altCharts);
    }
    if (!empty($allAlternateCharts)) {
        $chartsAlternatesPath = "{$uploadDir}/charts_alternates.pdf";
        if (AipCharts::mergeCharts($allAlternateCharts, $chartsAlternatesPath)) {
            $stored['charts_alternates'] = $chartsAlternatesPath;
        }
    }
}

$generatedPath = rtrim($config['paths']['generated'], '/') . "/{$id}.pdf";

$ordered = [
    'Accepted Flight Plan'    => $stored['accepted_flight_plan'],
    'Operational Flight Plan' => $stored['operational_flight_plan'],
    'Mass & Balance'          => $stored['mass_balance'],
    'Performance'             => $stored['performance'],
    'NOTAMs'                  => $stored['notams'],
    'SIGWX'                   => $stored['sigwx'],
    'Wind Charts'             => $stored['winds'],
    'METAR & TAF'             => $stored['metar_taf'],
    'Charts: Departure'       => $stored['charts_departure'],
    'Charts: Destination'     => $stored['charts_destination'],
    'Charts: Alternates'      => $stored['charts_alternates'],
];

PdfBuilder::build(
    $generatedPath,
    'OTR Aviation',
    $config['paths']['logo'],
    $flight,
    $ordered
);

$hash = hash_file('sha256', $generatedPath) ?: '';

$docket = [
    'id' => $id,
    'created_at' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
    'created_by' => 'local-user',
    'branding' => [
        'operator' => 'OTR Aviation',
        'logo_path' => 'public/assets/otr-logo.png',
    ],
    'flight' => $flight,
    'inputs' => [
        'mass_balance' => [
            'entered_in_app' => false,
            'computed' => null,
        ],
    ],
    'files' => [
        'accepted_flight_plan' => $stored['accepted_flight_plan'],
        'operational_flight_plan' => $stored['operational_flight_plan'],
        'mass_balance' => $stored['mass_balance'],
        'performance' => $stored['performance'],
        'notams' => $stored['notams'],
        'sigwx' => $stored['sigwx'],
        'winds' => $stored['winds'],
        'metar_taf' => $stored['metar_taf'],
        'charts' => [
            'departure' => $stored['charts_departure'],
            'destination' => $stored['charts_destination'],
            'alternates' => $stored['charts_alternates'] ? [$stored['charts_alternates']] : [],
        ],
    ],
    'generated_pdf' => $generatedPath,
    'hashes' => [
        'generated_pdf_sha256' => $hash,
    ],
];

$repo->save($docket);

header('Location: view.php?id=' . urlencode($id));
exit;
