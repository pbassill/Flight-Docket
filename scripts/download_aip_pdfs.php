#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * AIP PDF Downloader Script
 * 
 * This script downloads all PDF files from the ENAIRE AIP (Aeronautical Information Publication)
 * website for specified airport ICAO codes.
 * 
 * Usage:
 *   php scripts/download_aip_pdfs.php ICAO1 [ICAO2 ICAO3 ...]
 *   php scripts/download_aip_pdfs.php --help
 * 
 * Examples:
 *   php scripts/download_aip_pdfs.php LEMD
 *   php scripts/download_aip_pdfs.php LEMD LEBL LEMG
 * 
 * Downloaded PDFs will be stored in: storage/aip/ICAO_CODE/
 */

// Load configuration
$config = require __DIR__ . '/../config.php';

// Simple autoloader for our classes
spl_autoload_register(function ($class) {
    $prefix = 'OTR\\';
    $base_dir = __DIR__ . '/../app/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use OTR\Api\AipCrawler;

// Parse command line arguments
$args = array_slice($argv, 1);

// Show help if requested or no arguments provided
if (empty($args) || in_array('--help', $args) || in_array('-h', $args)) {
    showHelp();
    exit(0);
}

// Validate all ICAO codes before starting
$icaoCodes = [];
foreach ($args as $arg) {
    $icao = strtoupper(trim($arg));
    
    if (!preg_match('/^[A-Z]{4}$/', $icao)) {
        echo "Error: Invalid ICAO code format: {$arg}\n";
        echo "ICAO codes must be exactly 4 uppercase letters (e.g., LEMD, LEBL)\n";
        exit(1);
    }
    
    $icaoCodes[] = $icao;
}

// Initialize the crawler
$storageBasePath = $config['paths']['storage'] . '/aip';

// Ensure storage directory exists
if (!is_dir($storageBasePath) && !mkdir($storageBasePath, 0755, true)) {
    echo "Error: Failed to create storage directory: {$storageBasePath}\n";
    exit(1);
}

$crawler = new AipCrawler($storageBasePath);

// Download PDFs for each ICAO code
echo "Starting AIP PDF download for " . count($icaoCodes) . " airport(s)...\n";
echo str_repeat('=', 70) . "\n\n";

$totalDownloaded = 0;
$totalFailed = 0;
$successfulAirports = 0;

foreach ($icaoCodes as $icao) {
    echo "Processing: {$icao}\n";
    echo str_repeat('-', 70) . "\n";
    
    $result = $crawler->downloadAirportPdfs($icao);
    
    echo "Status: " . ($result['success'] ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Message: {$result['message']}\n";
    
    if (isset($result['downloaded'])) {
        $totalDownloaded += $result['downloaded'];
        $totalFailed += $result['failed'];
        
        if ($result['downloaded'] > 0) {
            $successfulAirports++;
        }
    }
    
    echo "\n";
}

// Summary
echo str_repeat('=', 70) . "\n";
echo "Summary:\n";
echo "  - Airports processed: " . count($icaoCodes) . "\n";
echo "  - Airports with downloads: {$successfulAirports}\n";
echo "  - Total PDFs downloaded: {$totalDownloaded}\n";
echo "  - Total PDFs failed: {$totalFailed}\n";
echo "\nPDFs are stored in: {$storageBasePath}/ICAO_CODE/\n";

// Exit with appropriate code
exit($totalDownloaded > 0 ? 0 : 1);

/**
 * Display help message
 */
function showHelp(): void
{
    echo <<<'HELP'
AIP PDF Downloader Script
==========================

This script downloads all PDF files from the ENAIRE AIP (Aeronautical Information 
Publication) website for specified airport ICAO codes.

USAGE:
  php scripts/download_aip_pdfs.php ICAO1 [ICAO2 ICAO3 ...]
  php scripts/download_aip_pdfs.php --help

ARGUMENTS:
  ICAO1, ICAO2, ...    One or more ICAO airport codes (4-letter codes)
  --help, -h           Show this help message

EXAMPLES:
  # Download PDFs for Madrid-Barajas Airport
  php scripts/download_aip_pdfs.php LEMD
  
  # Download PDFs for multiple airports
  php scripts/download_aip_pdfs.php LEMD LEBL LEMG
  
  # Download PDFs for all major Spanish airports
  php scripts/download_aip_pdfs.php LEMD LEBL LEMG LEVC LEAL LEZL

OUTPUT:
  Downloaded PDFs will be stored in: storage/aip/ICAO_CODE/
  
  Example directory structure:
    storage/aip/LEMD/
    storage/aip/LEBL/
    storage/aip/LEMG/

NOTES:
  - ICAO codes must be exactly 4 uppercase letters
  - The script will crawl the ENAIRE AIP website (https://aip.enaire.es/AIP)
  - Download progress and errors are logged to stderr
  - The storage/aip/ directory is excluded from git by .gitignore

COMMON SPANISH AIRPORT ICAO CODES:
  LEMD - Madrid-Barajas
  LEBL - Barcelona-El Prat
  LEMG - Málaga-Costa del Sol
  LEVC - Valencia
  LEAL - Alicante-Elche
  LEZL - Seville
  LEBB - Bilbao
  GCLP - Gran Canaria
  GCTS - Tenerife South
  GCFV - Fuerteventura

HELP;
}
