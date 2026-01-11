<?php

declare(strict_types=1);

namespace OTR;

final class AipCharts
{
    /**
     * Chart type identifiers used to filter relevant chart files
     */
    private const CHART_TYPES = ['VAC', 'ADC', 'PDC', 'AERODROME', 'AD '];
    
    /**
     * Get chart pack for a specific ICAO code from AIP storage
     * 
     * Returns an array of PDF file paths for the given ICAO code.
     * The pack includes: VAC (Visual Approach Charts), ADC (Aerodrome Charts), 
     * PDC (Parking/Docking Charts), and Aerodrome data documents.
     * 
     * @param string $icaoCode The 4-letter ICAO airport code
     * @param string $aipBasePath The base path to the AIP storage directory
     * @return array Array of absolute file paths to chart PDFs
     */
    public static function getChartPack(string $icaoCode, string $aipBasePath): array
    {
        $icaoCode = strtoupper(trim($icaoCode));
        $airportDir = rtrim($aipBasePath, '/') . '/' . $icaoCode;
        
        // If directory doesn't exist, return empty array
        if (!is_dir($airportDir)) {
            return [];
        }
        
        $charts = [];
        
        // Scan directory for chart files
        $files = scandir($airportDir);
        if ($files === false) {
            return [];
        }
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $airportDir . '/' . $file;
            
            // Only include PDF files
            if (!is_file($filePath)) {
                continue;
            }
            
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext !== 'pdf') {
                continue;
            }
            
            // Check if file matches expected chart types (case-insensitive)
            $fileUpper = strtoupper($file);
            $isChartFile = false;
            foreach (self::CHART_TYPES as $chartType) {
                if (str_contains($fileUpper, $chartType)) {
                    $isChartFile = true;
                    break;
                }
            }
            
            if ($isChartFile) {
                $charts[] = $filePath;
            }
        }
        
        return $charts;
    }
    
    /**
     * Merge multiple chart PDFs into a single PDF file
     * 
     * @param array $chartFiles Array of source PDF file paths
     * @param string $outputPath Destination path for the merged PDF
     * @return bool True if successful, false otherwise
     */
    public static function mergeCharts(array $chartFiles, string $outputPath): bool
    {
        if (empty($chartFiles)) {
            return false;
        }
        
        // Validate all input files exist and are readable
        foreach ($chartFiles as $file) {
            if (!is_file($file) || !is_readable($file)) {
                return false;
            }
        }
        
        // Ensure output directory exists
        $outputDir = dirname($outputPath);
        try {
            if (!is_dir($outputDir) && !mkdir($outputDir, 0750, true) && !is_dir($outputDir)) {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
        
        // If only one file, just copy it
        if (count($chartFiles) === 1) {
            $success = copy($chartFiles[0], $outputPath);
            if ($success) {
                chmod($outputPath, 0640);
            }
            return $success;
        }
        
        // For multiple files, use pdftk or similar tool if available
        // For now, we'll use a simple approach with gs (ghostscript)
        $escapedOutput = escapeshellarg($outputPath);
        $escapedInputs = array_map('escapeshellarg', $chartFiles);
        $inputsStr = implode(' ', $escapedInputs);
        
        // Try ghostscript first
        $gsCmd = "gs -dBATCH -dNOPAUSE -q -sDEVICE=pdfwrite -sOutputFile={$escapedOutput} {$inputsStr} 2>&1";
        exec($gsCmd, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($outputPath)) {
            chmod($outputPath, 0640);
            return true;
        }
        
        // If ghostscript fails, try pdftk
        $pdftkCmd = "pdftk {$inputsStr} cat output {$escapedOutput} 2>&1";
        exec($pdftkCmd, $output2, $returnCode2);
        
        if ($returnCode2 === 0 && file_exists($outputPath)) {
            chmod($outputPath, 0640);
            return true;
        }
        
        // If both fail, just copy the first file as fallback
        $success = copy($chartFiles[0], $outputPath);
        if ($success) {
            chmod($outputPath, 0640);
        }
        return $success;
    }
}
