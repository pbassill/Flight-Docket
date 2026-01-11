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

// Check if updating existing or creating new
$id = trim((string)($_POST['id'] ?? ''));
if ($id === '') {
    $id = $aircraftRepo->generateId();
}

// Validate and sanitize inputs
$typeCode = trim((string)($_POST['type_code'] ?? ''));
$name = trim((string)($_POST['name'] ?? ''));

if ($typeCode === '' || strlen($typeCode) > 20) {
    http_response_code(400);
    die('Invalid aircraft type code.');
}

if ($name === '' || strlen($name) > 100) {
    http_response_code(400);
    die('Invalid aircraft name.');
}

// Helper function to get optional numeric value
function getNumeric(string $key): ?float {
    $value = trim((string)($_POST[$key] ?? ''));
    if ($value === '') {
        return null;
    }
    // Validate that it's actually a number
    if (!is_numeric($value)) {
        http_response_code(400);
        die("Invalid numeric value for field: {$key}");
    }
    return (float)$value;
}

// Helper function to get optional string value
function getString(string $key): ?string {
    $value = trim((string)($_POST[$key] ?? ''));
    return $value === '' ? null : $value;
}

// Build aircraft data structure
$aircraftData = [
    'type_code' => $typeCode,
    'name' => $name,
    'mass_balance' => [
        'empty_weight' => getNumeric('empty_weight'),
        'empty_moment_arm' => getNumeric('empty_moment_arm'),
        'max_takeoff_weight' => getNumeric('max_takeoff_weight'),
        'max_landing_weight' => getNumeric('max_landing_weight'),
        'pilot_moment_arm' => getNumeric('pilot_moment_arm'),
        'passenger_moment_arm' => getNumeric('passenger_moment_arm'),
        'baggage_moment_arm' => getNumeric('baggage_moment_arm'),
        'fuel_moment_arm' => getNumeric('fuel_moment_arm'),
        'max_fuel_capacity' => getNumeric('max_fuel_capacity'),
        'cg_limits' => getString('cg_limits'),
    ],
    'performance' => [
        'cruise_speed' => getNumeric('cruise_speed'),
        'stall_speed' => getNumeric('stall_speed'),
        'climb_rate' => getNumeric('climb_rate'),
        'takeoff_distance' => getNumeric('takeoff_distance'),
        'landing_distance' => getNumeric('landing_distance'),
        'service_ceiling' => getNumeric('service_ceiling'),
        'fuel_consumption' => getNumeric('fuel_consumption'),
        'range' => getNumeric('range'),
        'endurance' => getNumeric('endurance'),
        'notes' => getString('performance_notes'),
    ],
];

// Validate CG limits JSON if provided
if ($aircraftData['mass_balance']['cg_limits'] !== null) {
    $cgLimits = json_decode($aircraftData['mass_balance']['cg_limits'], true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        die('Invalid CG limits JSON format.');
    }
    
    // Validate structure
    if (!is_array($cgLimits)) {
        http_response_code(400);
        die('CG limits must be a JSON array.');
    }
    
    foreach ($cgLimits as $limit) {
        if (!is_array($limit) || 
            !isset($limit['weight']) || !is_numeric($limit['weight']) ||
            !isset($limit['cg_forward']) || !is_numeric($limit['cg_forward']) ||
            !isset($limit['cg_aft']) || !is_numeric($limit['cg_aft'])) {
            http_response_code(400);
            die('Each CG limit entry must have numeric weight, cg_forward, and cg_aft properties.');
        }
    }
}

// Save aircraft configuration
if (!$aircraftRepo->save($id, $aircraftData)) {
    http_response_code(500);
    die('Failed to save aircraft configuration.');
}

// Redirect back to configure page
header('Location: configure_aircraft.php?success=1');
exit;
