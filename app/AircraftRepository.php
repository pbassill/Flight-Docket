<?php

declare(strict_types=1);

namespace OTR;

class AircraftRepository
{
    private string $storageDir;

    public function __construct(array $config)
    {
        $this->storageDir = $config['paths']['aircraft'] ?? __DIR__ . '/../storage/aircraft';
        
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0750, true);
        }
    }

    /**
     * Generate a unique aircraft ID
     */
    public function generateId(): string
    {
        return uniqid('aircraft_', true);
    }

    /**
     * Save aircraft configuration
     */
    public function save(string $id, array $data): bool
    {
        $filename = $this->storageDir . '/' . $id . '.json';
        $data['id'] = $id;
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        
        return file_put_contents($filename, $json) !== false;
    }

    /**
     * Load aircraft configuration by ID
     */
    public function load(string $id): ?array
    {
        $filename = $this->storageDir . '/' . $id . '.json';
        
        if (!file_exists($filename)) {
            return null;
        }
        
        $json = file_get_contents($filename);
        if ($json === false) {
            return null;
        }
        
        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /**
     * List all aircraft configurations
     */
    public function listAll(): array
    {
        $aircraft = [];
        $files = glob($this->storageDir . '/*.json');
        
        if ($files === false) {
            return [];
        }
        
        foreach ($files as $file) {
            $json = file_get_contents($file);
            if ($json === false) {
                continue;
            }
            
            $data = json_decode($json, true);
            if (is_array($data)) {
                $aircraft[] = $data;
            }
        }
        
        // Sort by name
        usort($aircraft, function($a, $b) {
            return strcmp($a['name'] ?? '', $b['name'] ?? '');
        });
        
        return $aircraft;
    }

    /**
     * Delete aircraft configuration
     */
    public function delete(string $id): bool
    {
        $filename = $this->storageDir . '/' . $id . '.json';
        
        if (!file_exists($filename)) {
            return false;
        }
        
        return unlink($filename);
    }

    /**
     * Check if aircraft ID exists
     */
    public function exists(string $id): bool
    {
        $filename = $this->storageDir . '/' . $id . '.json';
        return file_exists($filename);
    }
}
