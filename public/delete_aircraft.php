<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

use OTR\AircraftRepository;
use OTR\Security;
use OTR\ErrorHandler;

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

$aircraftRepo = new AircraftRepository($config);

// Get aircraft ID
$id = trim((string)($_POST['id'] ?? ''));

if ($id === '') {
    http_response_code(400);
    die('Aircraft ID is required.');
}

// Check if aircraft exists
if (!$aircraftRepo->exists($id)) {
    http_response_code(404);
    die('Aircraft not found.');
}

// Delete aircraft configuration
if (!$aircraftRepo->delete($id)) {
    http_response_code(500);
    die('Failed to delete aircraft configuration.');
}

// Redirect back to configure page
header('Location: configure_aircraft.php?deleted=1');
exit;
