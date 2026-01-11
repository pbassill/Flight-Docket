<?php

declare(strict_types=1);

namespace OTR;

final class AlternatesFinder
{
    private array $airports = [];

    public function __construct()
    {
        $dataFile = __DIR__ . '/data/airports.json';
        if (!file_exists($dataFile)) {
            throw new \RuntimeException('Airports database not found');
        }
        
        $json = file_get_contents($dataFile);
        if ($json === false) {
            throw new \RuntimeException('Failed to read airports database');
        }
        
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Invalid airports database format');
        }
        
        $this->airports = $decoded;
    }

    /**
     * Find alternates within specified distance of departure and destination
     * 
     * @param string $departureIcao Departure ICAO code
     * @param string $destinationIcao Destination ICAO code
     * @return array Array of ICAO codes
     */
    public function findAlternates(string $departureIcao, string $destinationIcao): array
    {
        $departure = $this->findAirport($departureIcao);
        $destination = $this->findAirport($destinationIcao);
        
        if ($departure === null || $destination === null) {
            return [];
        }
        
        // Try 10 miles first
        $alternates = $this->findAlternatesWithinDistance($departure, $destination, 10.0);
        
        // If none found, try 25 miles
        if (empty($alternates)) {
            $alternates = $this->findAlternatesWithinDistance($departure, $destination, 25.0);
        }
        
        return $alternates;
    }

    /**
     * Find airports within specified distance (in miles) of departure or destination
     * 
     * @param array $departure Departure airport data
     * @param array $destination Destination airport data
     * @param float $maxDistanceMiles Maximum distance in statute miles
     * @return array Array of ICAO codes
     */
    private function findAlternatesWithinDistance(array $departure, array $destination, float $maxDistanceMiles): array
    {
        $alternates = [];
        
        foreach ($this->airports as $airport) {
            $icao = $airport['icao'];
            
            // Skip if it's the departure or destination
            if ($icao === $departure['icao'] || $icao === $destination['icao']) {
                continue;
            }
            
            // Check distance from departure
            $distanceFromDeparture = $this->calculateDistance(
                $departure['lat'],
                $departure['lon'],
                $airport['lat'],
                $airport['lon']
            );
            
            // Check distance from destination
            $distanceFromDestination = $this->calculateDistance(
                $destination['lat'],
                $destination['lon'],
                $airport['lat'],
                $airport['lon']
            );
            
            // If within range of either departure or destination, add it
            if ($distanceFromDeparture <= $maxDistanceMiles || $distanceFromDestination <= $maxDistanceMiles) {
                $alternates[] = $icao;
            }
        }
        
        return $alternates;
    }

    /**
     * Find airport by ICAO code
     * 
     * @param string $icao ICAO code
     * @return array|null Airport data or null if not found
     */
    private function findAirport(string $icao): ?array
    {
        foreach ($this->airports as $airport) {
            if ($airport['icao'] === strtoupper($icao)) {
                return $airport;
            }
        }
        return null;
    }

    /**
     * Calculate distance between two points using Haversine formula
     * Returns distance in statute miles
     * 
     * @param float $lat1 Latitude of point 1
     * @param float $lon1 Longitude of point 1
     * @param float $lat2 Latitude of point 2
     * @param float $lon2 Longitude of point 2
     * @return float Distance in statute miles
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusMiles = 3958.8; // Earth radius in statute miles
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadiusMiles * $c;
    }
}
