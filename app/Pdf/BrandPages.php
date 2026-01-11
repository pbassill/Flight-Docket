<?php

declare(strict_types=1);

namespace OTR\Pdf;

use setasign\Fpdi\Fpdi;

final class BrandPages
{
    public static function addCover(Fpdi $pdf, string $operator, string $logoPath, array $flight): void
    {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);

        if (is_file($logoPath)) {
            $pdf->Image($logoPath, 15, 15, 40);
        }

        $pdf->SetFont('Helvetica', 'B', 22);
        $pdf->SetXY(15, 60);
        $pdf->Cell(0, 12, "{$operator} Flight Docket", 0, 1);

        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Ln(4);

        $route = ($flight['departure'] ?? '----') . ' → ' . ($flight['destination'] ?? '----');
        $pdf->Cell(0, 7, "Route: {$route}", 0, 1);
        $pdf->Cell(0, 7, "Aircraft: " . ($flight['aircraft_type'] ?? '----') . " / " . ($flight['registration'] ?? '----'), 0, 1);
        $pdf->Cell(0, 7, "Generated: " . (new \DateTimeImmutable('now'))->format('Y-m-d H:i T'), 0, 1);

        $pdf->Ln(10);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5, "This document is an assembled flight docket containing operational planning artefacts, briefings, and charts. Verify currency and applicability of all included material before flight.");
    }

    public static function addIndex(Fpdi $pdf, array $presence): void
    {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);

        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, "Contents and Checklist", 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Helvetica', '', 11);

        foreach ($presence as $label => $present) {
            $mark = $present ? 'Included' : 'Missing';
            $pdf->Cell(0, 7, "{$label}: {$mark}", 0, 1);
        }
    }

    public static function addFlightSummary(Fpdi $pdf, array $flight): void
    {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 20);

        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, "Flight Summary", 0, 1);
        $pdf->Ln(2);

        $pdf->SetFont('Helvetica', '', 11);

        $pairs = [
            'Aircraft Type' => $flight['aircraft_type'] ?? '',
            'Registration'  => $flight['registration'] ?? '',
            'Callsign'      => $flight['callsign'] ?? '',
            'Departure'     => $flight['departure'] ?? '',
            'Destination'   => $flight['destination'] ?? '',
            'Alternates'    => isset($flight['alternates']) ? implode(', ', $flight['alternates']) : '',
            'ETD (Local)'   => $flight['etd_local'] ?? '',
        ];

        foreach ($pairs as $k => $v) {
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(45, 7, "{$k}:", 0, 0);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->Cell(0, 7, (string)$v, 0, 1);
        }
    }
}
