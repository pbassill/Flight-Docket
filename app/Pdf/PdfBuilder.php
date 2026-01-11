<?php

declare(strict_types=1);

namespace OTR\Pdf;

use setasign\Fpdi\Fpdi;

final class PdfBuilder
{
    public static function build(string $outputPath, string $operator, string $logoPath, array $flight, array $orderedPdfPaths): void
    {
        $pdf = new Fpdi();
        $pdf->SetTitle($operator . ' Flight Docket');

        BrandPages::addCover($pdf, $operator, $logoPath, $flight);

        $presence = [];
        foreach ($orderedPdfPaths as $label => $path) {
            $presence[$label] = is_string($path) && is_file($path);
        }
        BrandPages::addIndex($pdf, $presence);
        BrandPages::addFlightSummary($pdf, $flight);

        foreach ($orderedPdfPaths as $label => $path) {
            if (!is_string($path) || !is_file($path)) {
                continue;
            }
            self::appendPdf($pdf, $path);
        }

        $dir = dirname($outputPath);
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create output directory: {$dir}");
        }

        $pdf->Output('F', $outputPath);
        chmod($outputPath, 0640);
    }

    private static function appendPdf(Fpdi $pdf, string $path): void
    {
        $pageCount = $pdf->setSourceFile($path);
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $tplId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($tplId);

            $orientation = ($size['width'] > $size['height']) ? 'L' : 'P';
            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($tplId);
        }
    }
}
