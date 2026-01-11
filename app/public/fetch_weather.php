<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OTR\Api\WeatherApiClient;
use OTR\Api\NotamApiClient;
use OTR\Api\PdfGenerator;
use OTR\Security;
use OTR\ErrorHandler;

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

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// CSRF validation
$csrfToken = (string)($_POST['csrf_token'] ?? '');
if (!Security::validateCsrfToken($csrfToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
}

// Get parameters
$departure = strtoupper(trim((string)($_POST['departure'] ?? '')));
$destination = strtoupper(trim((string)($_POST['destination'] ?? '')));
$alternates = trim((string)($_POST['alternates'] ?? ''));
$dataType = (string)($_POST['data_type'] ?? '');

// Validate ICAO codes
if (!Security::validateIcaoCode($departure)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid departure ICAO code']);
    exit;
}

if (!Security::validateIcaoCode($destination)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid destination ICAO code']);
    exit;
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
        header('Content-Type: application/json');
        echo json_encode(['error' => "Invalid alternate ICAO code: {$alt}"]);
        exit;
    }
}

// Limit alternates to 5
if (count($alternatesArray) > 5) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Too many alternates (max 5)']);
    exit;
}

// Collect all airfields
$allAirfields = array_merge([$departure, $destination], $alternatesArray);
$allAirfields = array_unique($allAirfields);

try {
    $result = [];
    
    // Fetch data based on type
    switch ($dataType) {
        case 'metar_taf':
            $checkwxConfig = $config['apis']['checkwx'];
            if (!$checkwxConfig['enabled'] || empty($checkwxConfig['api_key'])) {
                throw new \RuntimeException('CheckWX API is not configured');
            }
            
            $weatherClient = new WeatherApiClient(
                $checkwxConfig['api_key'],
                $checkwxConfig['base_url']
            );
            
            $airfieldData = [];
            foreach ($allAirfields as $icao) {
                $metar = $weatherClient->getMetar($icao);
                $taf = $weatherClient->getTaf($icao);
                
                $airfieldData[$icao] = [
                    'metar' => $metar,
                    'taf' => $taf,
                ];
            }
            
            // Generate PDF
            $tempFile = tempnam(sys_get_temp_dir(), 'metar_taf_') . '.pdf';
            PdfGenerator::generateMetarTafPdf($airfieldData, $tempFile);
            
            // Store file in session
            if (!isset($_SESSION['api_files'])) {
                $_SESSION['api_files'] = [];
            }
            $fileKey = 'metar_taf_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $_SESSION['api_files'][$fileKey] = $tempFile;
            
            $result = [
                'success' => true,
                'file_key' => $fileKey,
                'filename' => 'metar_taf.pdf',
            ];
            break;
            
        case 'sigmet':
            $checkwxConfig = $config['apis']['checkwx'];
            if (!$checkwxConfig['enabled'] || empty($checkwxConfig['api_key'])) {
                throw new \RuntimeException('CheckWX API is not configured');
            }
            
            $weatherClient = new WeatherApiClient(
                $checkwxConfig['api_key'],
                $checkwxConfig['base_url']
            );
            
            $airfieldData = [];
            foreach ($allAirfields as $icao) {
                $sigmet = $weatherClient->getSigmet($icao);
                $airfieldData[$icao] = [
                    'sigmet' => $sigmet,
                ];
            }
            
            // Generate PDF
            $tempFile = tempnam(sys_get_temp_dir(), 'sigmet_') . '.pdf';
            PdfGenerator::generateSigmetPdf($airfieldData, $tempFile);
            
            // Store file in session
            if (!isset($_SESSION['api_files'])) {
                $_SESSION['api_files'] = [];
            }
            $fileKey = 'sigmet_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $_SESSION['api_files'][$fileKey] = $tempFile;
            
            $result = [
                'success' => true,
                'file_key' => $fileKey,
                'filename' => 'sigmet.pdf',
            ];
            break;
            
        case 'notams':
            $notamifyConfig = $config['apis']['notamify'];
            if (!$notamifyConfig['enabled']) {
                throw new \RuntimeException('Notamify API is not configured');
            }
            
            $notamClient = new NotamApiClient($notamifyConfig['base_url']);
            
            $airfieldData = [];
            foreach ($allAirfields as $icao) {
                $notams = $notamClient->getNotams($icao);
                $airfieldData[$icao] = [
                    'notams' => $notams,
                ];
            }
            
            // Generate PDF
            $tempFile = tempnam(sys_get_temp_dir(), 'notams_') . '.pdf';
            PdfGenerator::generateNotamPdf($airfieldData, $tempFile);
            
            // Store file in session
            if (!isset($_SESSION['api_files'])) {
                $_SESSION['api_files'] = [];
            }
            $fileKey = 'notams_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
            $_SESSION['api_files'][$fileKey] = $tempFile;
            
            $result = [
                'success' => true,
                'file_key' => $fileKey,
                'filename' => 'notams.pdf',
            ];
            break;
            
        default:
            throw new \RuntimeException('Invalid data type');
    }
    
    // Return success with file path
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
