<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OTR\Security;

$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

Security::startSecureSession();
Security::setSecurityHeaders();

// Get file parameter
$file = (string)($_GET['file'] ?? '');

// Validate file path is in session
if (!isset($_SESSION['api_files'][$file])) {
    http_response_code(404);
    die('File not found');
}

$filePath = $_SESSION['api_files'][$file];

// Validate file exists
if (!is_file($filePath)) {
    http_response_code(404);
    die('File not found');
}

// Get original filename
$filename = basename($file);

// Send file
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filePath));
readfile($filePath);

// Clean up temp file after sending
unlink($filePath);
unset($_SESSION['api_files'][$file]);
exit;
