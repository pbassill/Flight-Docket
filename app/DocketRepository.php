<?php

declare(strict_types=1);

namespace OTR;

final class DocketRepository
{
    public function __construct(
        private readonly array $config
    ) {}

    private function isValidId(string $id): bool
    {
        // DOCKET-20260111-123456-ABC123 format
        return preg_match('/^DOCKET-\d{8}-\d{6}-[A-F0-9]{6}$/', $id) === 1;
    }

    public function newId(): string
    {
        $stamp = (new \DateTimeImmutable('now'))->format('Ymd-His');
        $rand = strtoupper(bin2hex(random_bytes(3)));
        return "DOCKET-{$stamp}-{$rand}";
    }

    public function docketJsonPath(string $id): string
    {
        $dt = new \DateTimeImmutable('now');
        $year = $dt->format('Y');
        $month = $dt->format('m');

        $base = rtrim($this->config['paths']['dockets'], '/');
        return "{$base}/{$year}/{$month}/{$id}.json";
    }

    public function save(array $docket): void
    {
        $path = $this->docketJsonPath($docket['id']);
        Uploads::ensureDir(dirname($path));

        $json = json_encode($docket, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode docket JSON.');
        }

        if (file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Failed to write docket JSON.');
        }

        chmod($path, 0640);
    }

    public function listRecent(int $limit = 25): array
    {
        // Limit maximum results to prevent resource exhaustion
        $limit = min(max(1, $limit), 100);
        
        $base = rtrim($this->config['paths']['dockets'], '/');
        if (!is_dir($base)) {
            return [];
        }

        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        $files = [];
        foreach ($rii as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.json')) {
                $files[] = $file->getPathname();
            }
        }

        usort($files, static fn($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, $limit);

        $out = [];
        foreach ($files as $path) {
            $raw = file_get_contents($path);
            if ($raw === false) {
                continue;
            }
            $data = json_decode($raw, true);
            if (is_array($data)) {
                $out[] = $data;
            }
        }
        return $out;
    }

    public function loadById(string $id): ?array
    {
        if (!$this->isValidId($id)) {
            return null;
        }

        $base = rtrim($this->config['paths']['dockets'], '/');
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
        foreach ($rii as $file) {
            if ($file->isFile() && $file->getFilename() === "{$id}.json") {
                $raw = file_get_contents($file->getPathname());
                if ($raw === false) {
                    return null;
                }
                $data = json_decode($raw, true);
                return is_array($data) ? $data : null;
            }
        }
        return null;
    }
}
