<?php

declare(strict_types=1);

namespace OTR\Api;

use setasign\Fpdf\Fpdf;

final class PdfGenerator
{
    /**
     * Generate a PDF from METAR and TAF data
     * 
     * @param array $airfields Array of airfield data with METAR/TAF
     * @param string $outputPath Path where PDF should be saved
     */
    public static function generateMetarTafPdf(array $airfields, string $outputPath): void
    {
        $pdf = new Fpdf();
        $pdf->SetTitle('METAR & TAF');
        $pdf->SetAuthor('CheckWX API');
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 14);
        $pdf->Cell(0, 10, 'METAR & TAF', 0, 1, 'C');
        $pdf->Ln(5);

        foreach ($airfields as $icao => $data) {
            $pdf->SetFont('Courier', 'B', 12);
            $pdf->Cell(0, 8, strtoupper($icao), 0, 1);
            $pdf->SetFont('Courier', '', 10);
            
            // METAR
            if (isset($data['metar']) && is_array($data['metar']) 
                && !empty($data['metar']['data']) && is_array($data['metar']['data'])
                && isset($data['metar']['data'][0])) {
                $pdf->SetFont('Courier', 'B', 10);
                $pdf->Cell(0, 6, 'METAR:', 0, 1);
                $pdf->SetFont('Courier', '', 9);
                
                $metarData = $data['metar']['data'][0];
                $rawText = $metarData['raw_text'] ?? 'No METAR available';
                $pdf->MultiCell(0, 5, $rawText);
                $pdf->Ln(2);
            }
            
            // TAF
            if (isset($data['taf']) && is_array($data['taf']) 
                && !empty($data['taf']['data']) && is_array($data['taf']['data'])
                && isset($data['taf']['data'][0])) {
                $pdf->SetFont('Courier', 'B', 10);
                $pdf->Cell(0, 6, 'TAF:', 0, 1);
                $pdf->SetFont('Courier', '', 9);
                
                $tafData = $data['taf']['data'][0];
                $rawText = $tafData['raw_text'] ?? 'No TAF available';
                $pdf->MultiCell(0, 5, $rawText);
                $pdf->Ln(2);
            }
            
            $pdf->Ln(5);
        }

        self::ensureDir(dirname($outputPath));
        $pdf->Output('F', $outputPath);
        chmod($outputPath, 0640);
    }

    /**
     * Generate a PDF from SIGMET data
     * 
     * @param array $airfields Array of airfield data with SIGMET
     * @param string $outputPath Path where PDF should be saved
     */
    public static function generateSigmetPdf(array $airfields, string $outputPath): void
    {
        $pdf = new Fpdf();
        $pdf->SetTitle('SIGMET');
        $pdf->SetAuthor('CheckWX API');
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 14);
        $pdf->Cell(0, 10, 'SIGMET', 0, 1, 'C');
        $pdf->Ln(5);

        $hasSigmet = false;
        foreach ($airfields as $icao => $data) {
            if (isset($data['sigmet']) && is_array($data['sigmet']) && !empty($data['sigmet']['data'])) {
                $hasSigmet = true;
                $pdf->SetFont('Courier', 'B', 12);
                $pdf->Cell(0, 8, strtoupper($icao), 0, 1);
                $pdf->SetFont('Courier', '', 9);
                
                foreach ($data['sigmet']['data'] as $sigmet) {
                    $rawText = $sigmet['raw_text'] ?? $sigmet['text'] ?? 'No SIGMET text available';
                    $pdf->MultiCell(0, 5, $rawText);
                    $pdf->Ln(2);
                }
                $pdf->Ln(5);
            }
        }

        if (!$hasSigmet) {
            $pdf->SetFont('Courier', '', 10);
            $pdf->Cell(0, 10, 'No SIGMET data available for the specified airfields.', 0, 1);
        }

        self::ensureDir(dirname($outputPath));
        $pdf->Output('F', $outputPath);
        chmod($outputPath, 0640);
    }

    /**
     * Generate a PDF from NOTAM data
     * 
     * @param array $airfields Array of airfield data with NOTAMs
     * @param string $outputPath Path where PDF should be saved
     */
    public static function generateNotamPdf(array $airfields, string $outputPath): void
    {
        $pdf = new Fpdf();
        $pdf->SetTitle('NOTAMs');
        $pdf->SetAuthor('Notamify API');
        $pdf->AddPage();
        $pdf->SetFont('Courier', 'B', 14);
        $pdf->Cell(0, 10, 'NOTAMs', 0, 1, 'C');
        $pdf->Ln(5);

        foreach ($airfields as $icao => $data) {
            $pdf->SetFont('Courier', 'B', 12);
            $pdf->Cell(0, 8, strtoupper($icao), 0, 1);
            $pdf->SetFont('Courier', '', 9);
            
            if (isset($data['notams']) && is_array($data['notams']) && !empty($data['notams'])) {
                foreach ($data['notams'] as $notam) {
                    $notamText = $notam['all'] ?? $notam['text'] ?? json_encode($notam);
                    $pdf->MultiCell(0, 5, $notamText);
                    $pdf->Ln(3);
                }
            } else {
                $pdf->Cell(0, 6, 'No NOTAMs available', 0, 1);
            }
            
            $pdf->Ln(5);
        }

        self::ensureDir(dirname($outputPath));
        $pdf->Output('F', $outputPath);
        chmod($outputPath, 0640);
    }

    /**
     * Save the first chart PDF from AIP España data
     * 
     * Note: Currently only saves the first chart. Future enhancement could
     * merge multiple PDFs using FPDI library.
     * 
     * @param array $chartsData Array of chart data from AIP España
     * @param string $outputPath Path where merged PDF should be saved
     */
    public static function mergeChartPdfs(array $chartsData, string $outputPath): void
    {
        if (empty($chartsData)) {
            throw new \RuntimeException('No charts data provided');
        }

        // For now, save the first chart PDF directly
        // Future enhancement: merge multiple PDFs using FPDI
        $firstChart = reset($chartsData);
        if (!isset($firstChart['content'])) {
            throw new \RuntimeException('Invalid chart data: missing content');
        }

        self::ensureDir(dirname($outputPath));
        
        // Save the PDF content
        $written = file_put_contents($outputPath, $firstChart['content']);
        if ($written === false) {
            throw new \RuntimeException('Failed to write chart PDF file');
        }
        
        chmod($outputPath, 0640);
    }

    /**
     * Ensure directory exists
     * 
     * @param string $dir Directory path
     */
    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }
    }
}
