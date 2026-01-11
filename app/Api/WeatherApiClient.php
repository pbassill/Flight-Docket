<?php

declare(strict_types=1);

namespace OTR\Api;

final class WeatherApiClient
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl
    ) {}

    /**
     * Fetch METAR data for a given ICAO code
     * 
     * @param string $icao ICAO airport code
     * @return array|null METAR data or null on failure
     */
    public function getMetar(string $icao): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $url = "{$this->baseUrl}/metar/{$icao}/decoded";
        $response = $this->makeRequest($url);
        
        if ($response === null || !isset($response['data']) || empty($response['data'])) {
            return null;
        }

        return $response;
    }

    /**
     * Fetch TAF data for a given ICAO code
     * 
     * @param string $icao ICAO airport code
     * @return array|null TAF data or null on failure
     */
    public function getTaf(string $icao): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $url = "{$this->baseUrl}/taf/{$icao}/decoded";
        $response = $this->makeRequest($url);
        
        if ($response === null || !isset($response['data']) || empty($response['data'])) {
            return null;
        }

        return $response;
    }

    /**
     * Fetch SIGMET data for a given ICAO code
     * 
     * @param string $icao ICAO airport code
     * @return array|null SIGMET data or null on failure
     */
    public function getSigmet(string $icao): ?array
    {
        if (empty($this->apiKey)) {
            return null;
        }

        $url = "{$this->baseUrl}/sigmet/{$icao}/decoded";
        $response = $this->makeRequest($url);
        
        if ($response === null) {
            return null;
        }

        return $response;
    }

    /**
     * Make HTTP request to CheckWX API
     * 
     * @param string $url Full URL to request
     * @return array|null Decoded JSON response or null on failure
     */
    private function makeRequest(string $url): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                "X-API-Key: {$this->apiKey}",
                'Accept: application/json',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode((string)$response, true);
        return is_array($data) ? $data : null;
    }
}
