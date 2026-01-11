<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OTR\Api\AipEspanaApiClient;
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
$icao = strtoupper(trim((string)($_POST['icao'] ?? '')));
$chartType = (string)($_POST['chart_type'] ?? '');

// Validate ICAO code
if (!Security::validateIcaoCode($icao)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid ICAO code']);
    exit;
}

// Check if it's a Spanish airport
if (!AipEspanaApiClient::isSpanishAirport($icao)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'AIP España only supports Spanish airports (ICAO codes starting with LE)']);
    exit;
}

// Validate chart type
if (!in_array($chartType, ['departure', 'destination', 'alternates'], true)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid chart type']);
    exit;
}

try {
    $aipConfig = $config['apis']['aip_espana'];
    if (!$aipConfig['enabled']) {
        throw new \RuntimeException('AIP España integration is not enabled');
    }
    
    $aipClient = new AipEspanaApiClient($aipConfig['base_url']);
    
    // Fetch charts for the airport
    $chartsData = $aipClient->getAerodromeCharts($icao);
    
    if ($chartsData === null || empty($chartsData)) {
        throw new \RuntimeException("No charts found for {$icao}. Charts may not be available or the airport may not be in the AIP España database.");
    }
    
    // Generate PDF with the charts
    $tempFile = tempnam(sys_get_temp_dir(), "aip_charts_{$icao}_");
    if ($tempFile === false) {
        throw new \RuntimeException('Failed to create temporary file');
    }
    $tempFile .= '.pdf';
    
    PdfGenerator::mergeChartPdfs($chartsData, $tempFile);
    
    // Store file in session
    if (!isset($_SESSION['api_files'])) {
        $_SESSION['api_files'] = [];
    }
    $fileKey = "charts_{$chartType}_" . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
    $_SESSION['api_files'][$fileKey] = $tempFile;
    
    $result = [
        'success' => true,
        'file_key' => $fileKey,
        'filename' => "charts_{$icao}.pdf",
        'charts_found' => count($chartsData),
    ];
    
    // Return success
    header('Content-Type: application/json');
    echo json_encode($result);
    
} catch (\Exception $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
