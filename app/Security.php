<?php

declare(strict_types=1);

namespace OTR;

final class Security
{
    public static function startSecureSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        
        session_start();
    }

    public static function generateCsrfToken(): string
    {
        self::startSecureSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrfToken(string $token): bool
    {
        self::startSecureSession();
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function setSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header("Content-Security-Policy: default-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; img-src 'self' data:; font-src 'self' https://cdnjs.cloudflare.com");
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    public static function sanitizeFilename(string $filename): string
    {
        // Remove any path traversal attempts and special characters
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        return $filename;
    }

    public static function validateIcaoCode(string $code): bool
    {
        return preg_match('/^[A-Z]{4}$/', $code) === 1;
    }

    public static function validateRegistration(string $reg): bool
    {
        // Allow common registration formats: G-XXXX, N12345, etc.
        return preg_match('/^[A-Z0-9-]{2,10}$/', $reg) === 1;
    }
}
