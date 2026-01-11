<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

\OTR\Security::setSecurityHeaders();

$repo = new \OTR\DocketRepository($config);

$id = (string)($_GET['id'] ?? '');
$docket = $id ? $repo->loadById($id) : null;

if (!$docket) {
    http_response_code(404);
    exit('Docket not found.');
}

$path = (string)($docket['generated_pdf'] ?? '');
if (!$path || !is_file($path)) {
    http_response_code(404);
    exit('PDF not found.');
}

$safeFilename = \OTR\Security::sanitizeFilename($id) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
header('Content-Length: ' . (string)filesize($path));
readfile($path);
exit;
