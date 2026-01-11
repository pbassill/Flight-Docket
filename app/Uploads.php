<?php

declare(strict_types=1);

namespace OTR;

final class Uploads
{
    public static function ensureDir(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException("Failed to create directory: {$path}");
        }
    }

    public static function isPdfUpload(array $file, array $cfg): bool
    {
        if (!isset($file['tmp_name'], $file['name'], $file['size'], $file['error'])) {
            return false;
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        if ($file['size'] <= 0 || $file['size'] > $cfg['max_bytes']) {
            return false;
        }

        // Verify actual file size matches reported size
        if (!is_uploaded_file($file['tmp_name'])) {
            return false;
        }
        
        $actualSize = filesize($file['tmp_name']);
        if ($actualSize === false || $actualSize !== $file['size']) {
            return false;
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $cfg['allowed_ext'], true)) {
            return false;
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!in_array($mime, $cfg['allowed_mime'], true)) {
            return false;
        }

        return true;
    }

    public static function moveUploadedPdf(array $file, string $destPath): void
    {
        $destDir = dirname($destPath);
        self::ensureDir($destDir);

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        chmod($destPath, 0640);
    }
}
