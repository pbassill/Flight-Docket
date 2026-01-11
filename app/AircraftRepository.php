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
     * Validate aircraft ID format
     */
    private function isValidId(string $id): bool
    {
        // aircraft_[timestamp].[random] or aircraft_default_[code] format
        return preg_match('/^aircraft_(?:default_[a-zA-Z0-9]+|[a-f0-9]+\.[0-9]+)$/', $id) === 1;
    }

    /**
     * Get file path for aircraft ID
     */
    private function getFilePath(string $id): string
    {
        if (!$this->isValidId($id)) {
            throw new \InvalidArgumentException('Invalid aircraft ID format.');
        }
        return $this->storageDir . '/' . $id . '.json';
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
        $filename = $this->getFilePath($id);
        $data['id'] = $id;
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            return false;
        }
        
        if (file_put_contents($filename, $json, LOCK_EX) === false) {
            return false;
        }
        
        chmod($filename, 0640);
        return true;
    }

    /**
     * Load aircraft configuration by ID
     */
    public function load(string $id): ?array
    {
        $filename = $this->getFilePath($id);
        
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
        $filename = $this->getFilePath($id);
        
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
        try {
            $filename = $this->getFilePath($id);
            return file_exists($filename);
        } catch (\InvalidArgumentException $e) {
            return false;
        }
    }
}
