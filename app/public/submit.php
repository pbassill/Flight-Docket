<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use OTR\DocketRepository;
use OTR\Uploads;
use OTR\Pdf\PdfBuilder;

$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone']);

$repo = new DocketRepository($config);
$id = $repo->newId();

$uploadDir = rtrim($config['paths']['uploads'], '/') . "/{$id}";
Uploads::ensureDir($uploadDir);

function post(string $k): string {
    return trim((string)($_POST[$k] ?? ''));
}

$flight = [
    'aircraft_type' => post('aircraft_type'),
    'registration'  => post('registration'),
    'callsign'      => post('callsign'),
    'departure'     => strtoupper(post('departure')),
    'destination'   => strtoupper(post('destination')),
    'alternates'    => array_values(array_filter(array_map(
        static fn($x) => strtoupper(trim($x)),
        explode(',', post('alternates'))
    ))),
    'etd_local'     => post('etd_local'),
];

$requiredFiles = [
    'accepted_flight_plan' => 'accepted_flight_plan.pdf',
    'mass_balance'         => 'mass_balance.pdf',
    'performance'          => 'performance.pdf',
    'notams'               => 'notams.pdf',
    'charts_departure'     => 'charts_departure.pdf',
    'charts_destination'   => 'charts_destination.pdf',
];

$optionalFiles = [
    'operational_flight_plan' => 'operational_flight_plan.pdf',
    'sigwx'                   => 'sigwx.pdf',
    'winds'                   => 'winds.pdf',
    'metar_taf'               => 'metar_taf.pdf',
    'charts_alternates'       => 'charts_alternates.pdf',
];

$stored = [];

$uploadCfg = $config['uploads'];

foreach ($requiredFiles as $field => $filename) {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || !Uploads::isPdfUpload($file, $uploadCfg)) {
        http_response_code(400);
        echo "Missing or invalid required upload: {$field}";
        exit;
    }
    $dest = "{$uploadDir}/{$filename}";
    Uploads::moveUploadedPdf($file, $dest);
    $stored[$field] = $dest;
}

foreach ($optionalFiles as $field => $filename) {
    $file = $_FILES[$field] ?? null;
    if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $stored[$field] = null;
        continue;
    }
    if (!Uploads::isPdfUpload($file, $uploadCfg)) {
        http_response_code(400);
        echo "Invalid optional upload (must be PDF): {$field}";
        exit;
    }
    $dest = "{$uploadDir}/{$filename}";
    Uploads::moveUploadedPdf($file, $dest);
    $stored[$field] = $dest;
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
