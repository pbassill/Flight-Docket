<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OTR\AlternatesFinder;
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

// Set JSON content type
header('Content-Type: application/json');

// CSRF validation
$csrfToken = (string)($_GET['csrf_token'] ?? '');
if (!Security::validateCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['error' => 'CSRF token validation failed']);
    exit;
}

$departure = strtoupper(trim((string)($_GET['departure'] ?? '')));
$destination = strtoupper(trim((string)($_GET['destination'] ?? '')));

// Validate ICAO codes
if (!Security::validateIcaoCode($departure)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid departure ICAO code']);
    exit;
}

if (!Security::validateIcaoCode($destination)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid destination ICAO code']);
    exit;
}

try {
    $finder = new AlternatesFinder();
    $alternates = $finder->findAlternates($departure, $destination);
    
    echo json_encode([
        'success' => true,
        'alternates' => $alternates,
        'count' => count($alternates)
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to find alternates: ' . $e->getMessage()]);
}
