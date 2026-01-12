<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

use OTR\AircraftRepository;
use OTR\Pdf\TrainingPdfGenerator;
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

// Get aircraft ID from query parameter
$aircraftId = trim((string)($_GET['aircraft_id'] ?? ''));

if ($aircraftId === '') {
    http_response_code(400);
    die('Aircraft ID is required.');
}

// Validate aircraft ID format to prevent directory traversal attacks
if (!preg_match('/^aircraft_(?:default_[a-zA-Z0-9]+|[a-f0-9]{24})$/', $aircraftId)) {
    http_response_code(400);
    die('Invalid aircraft ID format.');
}

// Load aircraft configuration
$aircraftRepo = new AircraftRepository($config);
$aircraft = $aircraftRepo->load($aircraftId);

if ($aircraft === null) {
    http_response_code(404);
    die('Aircraft configuration not found.');
}

// Validate that aircraft has necessary data
if (!isset($aircraft['mass_balance']) || !isset($aircraft['performance'])) {
    http_response_code(400);
    die('Aircraft configuration is incomplete. Please ensure Mass & Balance and Performance data are configured.');
}

// Generate PDF filename
$typeCode = $aircraft['type_code'] ?? 'aircraft';
$timestamp = date('Ymd_His');
$filename = "training_{$typeCode}_{$timestamp}.pdf";

// Ensure generated directory exists
$generatedDir = $config['paths']['generated'] ?? __DIR__ . '/../storage/generated';
if (!is_dir($generatedDir)) {
    mkdir($generatedDir, 0750, true);
}

$outputPath = $generatedDir . '/' . $filename;

try {
    // Generate the training PDF
    TrainingPdfGenerator::generateTrainingPdf($aircraft, $outputPath);
    
    // Read file into memory before unlinking to avoid race conditions
    $pdfContent = file_get_contents($outputPath);
    if ($pdfContent === false) {
        throw new \RuntimeException('Failed to read generated PDF file.');
    }
    
    // Delete the file immediately after reading
    unlink($outputPath);
    
    // Send PDF to browser
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($pdfContent));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $pdfContent;
    
} catch (\Exception $e) {
    error_log('Training PDF generation error: ' . $e->getMessage());
    http_response_code(500);
    die('Failed to generate training PDF: ' . htmlspecialchars($e->getMessage()));
}
