<?php

declare(strict_types=1);

namespace OTR\Api;

final class NotamApiClient
{
    public function __construct(
        private readonly string $baseUrl
    ) {}

    /**
     * Fetch NOTAM data for a given ICAO code
     * 
     * @param string $icao ICAO airport code
     * @return array|null NOTAM data or null on failure
     */
    public function getNotams(string $icao): ?array
    {
        $url = "{$this->baseUrl}/notams/{$icao}";
        $response = $this->makeRequest($url);
        
        if ($response === null) {
            return null;
        }

        return $response;
    }

    /**
     * Make HTTP request to Notamify API
     * 
     * @param string $url Full URL to request
     * @return array|null Decoded JSON response or null on failure
     */
    private function makeRequest(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            error_log("Notamify API: Failed to initialize cURL for URL: {$url}");
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("Notamify API: cURL error for URL {$url}: {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("Notamify API: HTTP {$httpCode} for URL: {$url}");
            return null;
        }

        $data = json_decode((string)$response, true);
        return is_array($data) ? $data : null;
    }
}
