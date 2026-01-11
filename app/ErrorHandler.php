<?php

declare(strict_types=1);

namespace OTR;

final class ErrorHandler
{
    public static function register(string $logPath): void
    {
        // Set error reporting
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', $logPath);

        // Custom error handler
        set_error_handler([self::class, 'handleError']);
        
        // Custom exception handler
        set_exception_handler([self::class, 'handleException']);
        
        // Shutdown function for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): bool {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorType = match($errno) {
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_NOTICE => 'NOTICE',
            E_DEPRECATED => 'DEPRECATED',
            default => 'UNKNOWN',
        };

        error_log("[$errorType] $errstr in $errfile:$errline");
        
        // Display user-friendly error
        if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            self::displayUserError('An error occurred. Please try again.');
            exit(1);
        }

        return true;
    }

    public static function handleException(\Throwable $exception): void
    {
        error_log(sprintf(
            "Uncaught exception: %s in %s:%d\nStack trace:\n%s",
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));

        self::displayUserError('An unexpected error occurred. Please try again.');
        exit(1);
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
            error_log(sprintf(
                "Fatal error: %s in %s:%d",
                $error['message'],
                $error['file'],
                $error['line']
            ));

            self::displayUserError('A critical error occurred. Please contact support.');
        }
    }

    private static function displayUserError(string $message): void
    {
        if (headers_sent()) {
            echo $message;
            return;
        }

        http_response_code(500);
        
        // If this is an AJAX/API request, return JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message]);
            return;
        }

        // Otherwise return HTML
        echo "<!DOCTYPE html>\n";
        echo "<html><head><meta charset=\"utf-8\"><title>Error</title></head>\n";
        echo "<body><h1>Error</h1><p>" . htmlspecialchars($message) . "</p></body></html>";
    }
}
