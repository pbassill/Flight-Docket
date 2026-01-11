<?php

declare(strict_types=1);

namespace OTR\Api;

final class AipCrawler
{
    private const BASE_URL = 'https://aip.enaire.es/AIP';
    
    public function __construct(
        private readonly string $storageBasePath
    ) {}

    /**
     * Download all PDF files for a given ICAO airport code
     * 
     * @param string $icao ICAO airport code (e.g., 'LEMD', 'LEBL')
     * @return array Array with 'success' boolean and 'message' string
     */
    public function downloadAirportPdfs(string $icao): array
    {
        // Validate ICAO code format (4 uppercase letters)
        if (!preg_match('/^[A-Z]{4}$/', $icao)) {
            return [
                'success' => false,
                'message' => "Invalid ICAO code format: {$icao}. Expected 4 uppercase letters."
            ];
        }

        // Create directory for this airport
        $airportDir = $this->storageBasePath . '/' . $icao;
        if (!is_dir($airportDir) && !mkdir($airportDir, 0755, true)) {
            return [
                'success' => false,
                'message' => "Failed to create directory: {$airportDir}"
            ];
        }

        // Get list of PDF URLs for this airport
        $pdfUrls = $this->findPdfUrls($icao);
        
        if (empty($pdfUrls)) {
            return [
                'success' => false,
                'message' => "No PDF files found for ICAO code: {$icao}"
            ];
        }

        // Download each PDF
        $downloaded = 0;
        $failed = 0;
        $errors = [];

        foreach ($pdfUrls as $url) {
            $filename = $this->extractFilename($url, $icao);
            $filepath = $airportDir . '/' . $filename;

            $result = $this->downloadFile($url, $filepath);
            
            if ($result['success']) {
                $downloaded++;
                error_log("AIP Crawler: Downloaded {$filename} for {$icao}");
            } else {
                $failed++;
                $errors[] = $result['message'];
                error_log("AIP Crawler: Failed to download {$filename} for {$icao}: {$result['message']}");
            }
        }

        $message = "Downloaded {$downloaded} PDF(s) for {$icao}";
        if ($failed > 0) {
            $message .= ", {$failed} failed. Errors: " . implode('; ', $errors);
        }

        return [
            'success' => $downloaded > 0,
            'message' => $message,
            'downloaded' => $downloaded,
            'failed' => $failed
        ];
    }

    /**
     * Download all PDFs for multiple ICAO codes
     * 
     * @param array $icaoCodes Array of ICAO codes
     * @return array Results for each ICAO code
     */
    public function downloadMultipleAirports(array $icaoCodes): array
    {
        $results = [];
        
        foreach ($icaoCodes as $icao) {
            $icao = strtoupper(trim($icao));
            $results[$icao] = $this->downloadAirportPdfs($icao);
        }

        return $results;
    }

    /**
     * Find PDF URLs for a given ICAO code using known ENAIRE URL patterns
     * 
     * Note: This method generates all potential PDF URLs based on the known ENAIRE structure.
     * Not all URLs may exist for every airport (some may return 404), but the downloadFile
     * method handles this gracefully by checking HTTP status and validating PDF content.
     * 
     * @param string $icao ICAO airport code
     * @return array Array of PDF URLs to attempt downloading
     */
    private function findPdfUrls(string $icao): array
    {
        $pdfUrls = [];

        // Extract country prefix (first 2 letters of ICAO code)
        $countryPrefix = substr($icao, 0, 2);

        // Base URL pattern for ENAIRE AIP
        // Pattern: https://aip.enaire.es/AIP/contenido_AIP/AD/AD2/{ICAO}/{PREFIX}_AD_2_{ICAO}[_TYPE]_en.pdf
        $baseUrl = self::BASE_URL . "/contenido_AIP/AD/AD2/{$icao}";

        // Document types to download
        $documentTypes = [
            '',         // Main aerodrome document: LE_AD_2_LEGR_en.pdf
            '_ADC_1',   // Aerodrome Chart: LE_AD_2_LEGR_ADC_1_en.pdf
            '_PDC_1',   // Parking/Docking Chart: LE_AD_2_LEGR_PDC_1_en.pdf
            '_VAC_1',   // Visual Approach Chart: LE_AD_2_LEGR_VAC_1_en.pdf
        ];

        foreach ($documentTypes as $docType) {
            // Construct the PDF filename
            $filename = "{$countryPrefix}_AD_2_{$icao}{$docType}_en.pdf";
            $pdfUrl = "{$baseUrl}/{$filename}";
            $pdfUrls[] = $pdfUrl;
        }

        return $pdfUrls;
    }

    /**
     * Extract PDF links from a web page
     * 
     * @param string $url URL to crawl
     * @param string|null $filter Optional ICAO code to filter links
     * @return array Array of PDF URLs
     */
    private function extractPdfLinksFromPage(string $url, ?string $filter = null): array
    {
        $pdfUrls = [];
        
        $html = $this->fetchPageContent($url);
        if ($html === null) {
            return $pdfUrls;
        }

        // Extract all PDF links from the HTML
        // Look for href attributes pointing to PDF files
        $pattern = '/href=["\']((?:[^"\']*\/)?[^"\']*\.pdf)["\']/i';
        if (preg_match_all($pattern, $html, $matches)) {
            foreach ($matches[1] as $pdfPath) {
                // Convert relative URLs to absolute
                if (strpos($pdfPath, 'http') !== 0) {
                    // Handle different relative path formats
                    if ($pdfPath[0] === '/') {
                        $pdfUrl = 'https://aip.enaire.es' . $pdfPath;
                    } else {
                        $pdfUrl = dirname($url) . '/' . $pdfPath;
                    }
                } else {
                    $pdfUrl = $pdfPath;
                }

                // Filter by ICAO code if specified
                if ($filter === null || stripos($pdfUrl, $filter) !== false) {
                    $pdfUrls[] = $pdfUrl;
                }
            }
        }

        return $pdfUrls;
    }

    /**
     * Fetch content from a URL
     * 
     * @param string $url URL to fetch
     * @return string|null HTML content or null on failure
     */
    private function fetchPageContent(string $url): ?string
    {
        $ch = curl_init($url);
        if ($ch === false) {
            $logPath = $this->sanitizeUrlForLogging($url);
            error_log("AIP Crawler: Failed to initialize cURL for path: {$logPath}");
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OTR-Flight-Docket/1.0)',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            $logPath = $this->sanitizeUrlForLogging($url);
            error_log("AIP Crawler: cURL error for path {$logPath}: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $logPath = $this->sanitizeUrlForLogging($url);
            error_log("AIP Crawler: HTTP {$httpCode} for path: {$logPath}");
            return null;
        }

        return (string)$response;
    }

    /**
     * Download a file from a URL
     * 
     * @param string $url URL to download from
     * @param string $filepath Local path to save file
     * @return array Array with 'success' boolean and 'message' string
     */
    private function downloadFile(string $url, string $filepath): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            $logPath = $this->sanitizeUrlForLogging($url);
            return [
                'success' => false,
                'message' => "Failed to initialize cURL for path: {$logPath}"
            ];
        }

        $fp = fopen($filepath, 'wb');
        if ($fp === false) {
            curl_close($ch);
            return [
                'success' => false,
                'message' => "Failed to open file for writing: {$filepath}"
            ];
        }

        curl_setopt_array($ch, [
            CURLOPT_FILE => $fp,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OTR-Flight-Docket/1.0)',
        ]);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if ($success === false) {
            unlink($filepath);
            return [
                'success' => false,
                'message' => "cURL error: {$error}"
            ];
        }

        if ($httpCode !== 200) {
            unlink($filepath);
            return [
                'success' => false,
                'message' => "HTTP {$httpCode}"
            ];
        }

        // Verify the downloaded file is a valid PDF
        if (filesize($filepath) < 100 || !$this->isPdfFile($filepath)) {
            unlink($filepath);
            return [
                'success' => false,
                'message' => "Downloaded file is not a valid PDF"
            ];
        }

        return [
            'success' => true,
            'message' => "Successfully downloaded to {$filepath}"
        ];
    }

    /**
     * Check if a file is a valid PDF
     * 
     * @param string $filepath Path to file
     * @return bool True if file appears to be a PDF
     */
    private function isPdfFile(string $filepath): bool
    {
        $fp = fopen($filepath, 'rb');
        if ($fp === false) {
            return false;
        }

        $header = fread($fp, 5);
        fclose($fp);

        return $header === '%PDF-';
    }

    /**
     * Extract a safe filename from a URL
     * 
     * @param string $url URL containing filename
     * @param string $icao ICAO code for fallback naming
     * @return string Safe filename
     */
    private function extractFilename(string $url, string $icao): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $filename = basename($path ?? '');

        // If we couldn't extract a filename, generate one
        if (empty($filename) || !str_ends_with(strtolower($filename), '.pdf')) {
            $filename = $icao . '_' . md5($url) . '.pdf';
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);

        return $filename;
    }

    /**
     * Sanitize URL for logging (remove sensitive information)
     * 
     * @param string $url URL to sanitize
     * @return string Sanitized URL path for logging
     */
    private function sanitizeUrlForLogging(string $url): string
    {
        $parsedUrl = parse_url($url);
        $logPath = ($parsedUrl['path'] ?? '/') . (isset($parsedUrl['query']) ? '?...' : '');
        return $logPath;
    }
}
