<?php

declare(strict_types=1);

namespace OTR\Api;

final class AipEspanaApiClient
{
    public function __construct(
        private readonly string $baseUrl
    ) {}

    /**
     * Fetch aerodrome charts for a given ICAO code
     * 
     * @param string $icao ICAO airport code (must be Spanish airport starting with LE)
     * @return array|null Chart data or null on failure
     */
    public function getAerodromeCharts(string $icao): ?array
    {
        // Validate that this is a Spanish airport
        if (!str_starts_with(strtoupper($icao), 'LE')) {
            return null;
        }

        $charts = [];
        
        // Try to fetch different chart types
        $chartTypes = [
            'AD-2' => 'Aerodrome Charts',
            'VAC' => 'Visual Approach Charts',
            'ADC' => 'Aerodrome Chart',
            'PDC' => 'Precision Approach Chart',
        ];

        foreach ($chartTypes as $type => $description) {
            $url = $this->constructChartUrl($icao, $type);
            $chartData = $this->fetchChart($url, $type, $description);
            if ($chartData !== null) {
                $charts[] = $chartData;
            }
        }

        return !empty($charts) ? $charts : null;
    }

    /**
     * Construct URL for a specific chart type
     * 
     * @param string $icao ICAO code
     * @param string $chartType Chart type identifier
     * @return string URL to the chart
     */
    private function constructChartUrl(string $icao, string $chartType): string
    {
        $icaoUpper = strtoupper($icao);
        
        // AIP España typically uses formats like:
        // https://aip.enaire.es/AIP/AD-2.LEGR-ADC.pdf
        // https://aip.enaire.es/AIP/AD-2.LEGR.pdf
        
        if ($chartType === 'AD-2') {
            return "{$this->baseUrl}/{$chartType}.{$icaoUpper}.pdf";
        }
        
        return "{$this->baseUrl}/{$chartType}-{$icaoUpper}.pdf";
    }

    /**
     * Fetch a chart from the given URL
     * 
     * @param string $url URL to fetch
     * @param string $type Chart type
     * @param string $description Chart description
     * @return array|null Chart data or null if not available
     */
    private function fetchChart(string $url, string $type, string $description): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            error_log("AIP España: Failed to initialize cURL for {$url}");
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; OTR Flight Docket)',
            CURLOPT_HTTPHEADER => [
                'Accept: application/pdf',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("AIP España: cURL error for {$url}: {$error}");
            return null;
        }

        // Only accept successful responses with PDF content
        if ($httpCode !== 200) {
            return null;
        }

        // Verify it's actually a PDF
        if ($contentType !== null && !str_contains(strtolower($contentType), 'pdf')) {
            return null;
        }

        // Verify PDF magic number
        if (!str_starts_with($response, '%PDF')) {
            return null;
        }

        return [
            'type' => $type,
            'description' => $description,
            'url' => $url,
            'content' => $response,
            'size' => strlen($response),
        ];
    }

    /**
     * Check if an ICAO code is a Spanish airport
     * 
     * @param string $icao ICAO code
     * @return bool True if Spanish airport
     */
    public static function isSpanishAirport(string $icao): bool
    {
        return str_starts_with(strtoupper(trim($icao)), 'LE');
    }
}
