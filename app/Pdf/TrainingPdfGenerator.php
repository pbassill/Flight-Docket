<?php

declare(strict_types=1);

namespace OTR\Pdf;

final class TrainingPdfGenerator
{
    private const FUEL_DENSITY = 0.72; // kg/L for AVGAS
    private const OIL_WEIGHT = 8.0; // kg for full oil (included in Basic Empty Weight)
    
    /**
     * Generate a training configuration PDF with Mass & Balance and Performance data
     * 
     * @param array $aircraft Aircraft configuration data
     * @param string $outputPath Path where PDF should be saved
     */
    public static function generateTrainingPdf(array $aircraft, string $outputPath): void
    {
        $pdf = new \FPDF();
        $pdf->SetTitle('Training Configuration - ' . ($aircraft['type_code'] ?? 'Aircraft'));
        $pdf->SetAuthor('OTR Aviation Flight Docket');
        
        // Add Mass & Balance page
        self::addMassAndBalancePage($pdf, $aircraft);
        
        // Add Performance page
        self::addPerformancePage($pdf, $aircraft);
        
        self::ensureDir(dirname($outputPath));
        $pdf->Output('F', $outputPath);
        chmod($outputPath, 0640);
    }
    
    /**
     * Add Mass & Balance calculation page
     */
    private static function addMassAndBalancePage(\FPDF $pdf, array $aircraft): void
    {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);
        
        // Title
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Mass & Balance Sheet - Training Configuration', 0, 1, 'C');
        $pdf->Ln(2);
        
        // Aircraft info
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Aircraft: ' . ($aircraft['type_code'] ?? '') . ' - ' . ($aircraft['name'] ?? ''), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i T'), 0, 1);
        $pdf->Ln(3);
        
        $massBalance = $aircraft['mass_balance'] ?? [];
        
        // Training configuration parameters
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Training Configuration:', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, '  • Left seat pilot: 105 kg', 0, 1);
        $pdf->Cell(0, 5, '  • Right seat pilot: 90 kg', 0, 1);
        $pdf->Cell(0, 5, '  • Baggage: 4 kg', 0, 1);
        $pdf->Cell(0, 5, '  • Full fuel', 0, 1);
        $pdf->Ln(4);
        
        // Calculate values
        $emptyWeight = (float)($massBalance['empty_weight'] ?? 0);
        $emptyArm = (float)($massBalance['empty_moment_arm'] ?? 0);
        $pilotArm = (float)($massBalance['pilot_moment_arm'] ?? 0);
        $passengerArm = (float)($massBalance['passenger_moment_arm'] ?? 0);
        $baggageArm = (float)($massBalance['baggage_moment_arm'] ?? 0);
        $fuelArm = (float)($massBalance['fuel_moment_arm'] ?? 0);
        $maxFuel = (float)($massBalance['max_fuel_capacity'] ?? 0);
        
        $pilotLeftWeight = 105.0;
        $pilotRightWeight = 90.0;
        $baggageWeight = 4.0;
        $fuelWeight = $maxFuel * self::FUEL_DENSITY;
        
        // Build loading table
        $items = [
            ['Item' => 'Basic Empty Weight', 'Weight (kg)' => $emptyWeight, 'Arm (m)' => $emptyArm],
            ['Item' => 'Pilot (Left Seat)', 'Weight (kg)' => $pilotLeftWeight, 'Arm (m)' => $pilotArm],
            ['Item' => 'Pilot (Right Seat)', 'Weight (kg)' => $pilotRightWeight, 'Arm (m)' => $passengerArm],
            ['Item' => 'Baggage', 'Weight (kg)' => $baggageWeight, 'Arm (m)' => $baggageArm],
            ['Item' => 'Fuel (Full)', 'Weight (kg)' => $fuelWeight, 'Arm (m)' => $fuelArm],
        ];
        
        // Calculate moments and totals
        $totalWeight = 0;
        $totalMoment = 0;
        
        foreach ($items as &$item) {
            $moment = $item['Weight (kg)'] * $item['Arm (m)'];
            $item['Moment (kg·m)'] = $moment;
            $totalWeight += $item['Weight (kg)'];
            $totalMoment += $moment;
        }
        
        $cgPosition = $totalWeight > 0 ? $totalMoment / $totalWeight : 0;
        
        // Draw table
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(220, 220, 220);
        
        $colWidths = [70, 35, 35, 40];
        $pdf->Cell($colWidths[0], 7, 'Item', 1, 0, 'L', true);
        $pdf->Cell($colWidths[1], 7, 'Weight (kg)', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 7, 'Arm (m)', 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 7, 'Moment (kg·m)', 1, 1, 'C', true);
        
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($items as $item) {
            $pdf->Cell($colWidths[0], 6, $item['Item'], 1, 0, 'L');
            $pdf->Cell($colWidths[1], 6, number_format($item['Weight (kg)'], 1), 1, 0, 'R');
            $pdf->Cell($colWidths[2], 6, number_format($item['Arm (m)'], 2), 1, 0, 'R');
            $pdf->Cell($colWidths[3], 6, number_format($item['Moment (kg·m)'], 2), 1, 1, 'R');
        }
        
        // Calculate zero fuel weight and moment
        $zeroFuelWeight = $emptyWeight + $pilotLeftWeight + $pilotRightWeight + $baggageWeight;
        $zeroFuelMoment = ($emptyWeight * $emptyArm) + ($pilotLeftWeight * $pilotArm) + 
                          ($pilotRightWeight * $passengerArm) + ($baggageWeight * $baggageArm);
        $zeroFuelCG = $zeroFuelWeight > 0 ? $zeroFuelMoment / $zeroFuelWeight : 0;
        
        // Zero Fuel Weight row
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell($colWidths[0], 7, 'TOTAL (Zero Fuel)', 1, 0, 'L', true);
        $pdf->Cell($colWidths[1], 7, number_format($zeroFuelWeight, 1), 1, 0, 'R', true);
        $pdf->Cell($colWidths[2], 7, number_format($zeroFuelCG, 3), 1, 0, 'R', true);
        $pdf->Cell($colWidths[3], 7, number_format($zeroFuelMoment, 2), 1, 1, 'R', true);
        
        // Ramp/Takeoff Weight row (includes fuel)
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell($colWidths[0], 7, 'TOTAL (Ramp Weight)', 1, 0, 'L', true);
        $pdf->Cell($colWidths[1], 7, number_format($totalWeight, 1), 1, 0, 'R', true);
        $pdf->Cell($colWidths[2], 7, number_format($cgPosition, 3), 1, 0, 'R', true);
        $pdf->Cell($colWidths[3], 7, number_format($totalMoment, 2), 1, 1, 'R', true);
        
        $pdf->Ln(4);
        
        // Summary
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Mass & Balance Summary:', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        
        $maxTakeoffWeight = (float)($massBalance['max_takeoff_weight'] ?? 0);
        $weightMargin = $maxTakeoffWeight - $totalWeight;
        
        $pdf->Cell(80, 6, 'Zero Fuel Weight:', 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, number_format($zeroFuelWeight, 1) . ' kg', 0, 1);
        
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(80, 6, 'Zero Fuel CG Position:', 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, number_format($zeroFuelCG, 3) . ' m', 0, 1);
        
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(80, 6, 'Ramp Weight (with fuel):', 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, number_format($totalWeight, 1) . ' kg', 0, 1);
        
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(80, 6, 'Ramp CG Position:', 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, number_format($cgPosition, 3) . ' m', 0, 1);
        
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(80, 6, 'Maximum Takeoff Weight:', 0, 0);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, number_format($maxTakeoffWeight, 1) . ' kg', 0, 1);
        
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(80, 6, 'Weight Margin (MTOW - Ramp):', 0, 0);
        $color = $weightMargin >= 0 ? [0, 128, 0] : [255, 0, 0];
        $pdf->SetTextColor($color[0], $color[1], $color[2]);
        $pdf->SetFont('Helvetica', 'B', 10);
        $prefix = $weightMargin < 0 ? 'OVERWEIGHT by ' : '';
        $pdf->Cell(0, 6, $prefix . number_format(abs($weightMargin), 1) . ' kg', 0, 1);
        $pdf->SetTextColor(0, 0, 0);
        
        // Show fuel to remove if overweight
        if ($weightMargin < 0) {
            $pdf->SetFont('Helvetica', '', 10);
            $fuelToRemove = abs($weightMargin);
            $fuelToRemoveLiters = $fuelToRemove / self::FUEL_DENSITY;
            $pdf->Cell(80, 6, 'Fuel to remove to reach MTOW:', 0, 0);
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(0, 6, sprintf('%.1f kg (%.1f L)', $fuelToRemove, $fuelToRemoveLiters), 0, 1);
        }
        
        $pdf->Ln(3);
        
        // CG limits check
        if (isset($massBalance['cg_limits']) && !empty($massBalance['cg_limits'])) {
            $cgLimitsJson = $massBalance['cg_limits'];
            $cgLimits = json_decode($cgLimitsJson, true);
            
            if (is_array($cgLimits) && !empty($cgLimits)) {
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->Cell(0, 7, 'CG Envelope Check:', 0, 1);
                $pdf->SetFont('Helvetica', '', 9);
                
                // Find applicable CG limits for current weight
                $withinLimits = false;
                foreach ($cgLimits as $limit) {
                    if (isset($limit['weight'], $limit['cg_forward'], $limit['cg_aft'])) {
                        $limitWeight = (float)$limit['weight'];
                        if ($totalWeight <= $limitWeight) {
                            $cgForward = (float)$limit['cg_forward'];
                            $cgAft = (float)$limit['cg_aft'];
                            
                            $withinLimits = ($cgPosition >= $cgForward && $cgPosition <= $cgAft);
                            
                            $pdf->Cell(0, 5, sprintf('At %.0f kg: CG limits %.2f m - %.2f m', 
                                $limitWeight, $cgForward, $cgAft), 0, 1);
                            $pdf->Cell(0, 5, sprintf('Current CG %.3f m is %s', 
                                $cgPosition, 
                                $withinLimits ? 'WITHIN LIMITS' : 'OUT OF LIMITS'), 0, 1);
                            break;
                        }
                    }
                }
                
                if (!$withinLimits) {
                    $pdf->SetTextColor(255, 0, 0);
                    $pdf->SetFont('Helvetica', 'B', 10);
                    $pdf->Cell(0, 6, 'WARNING: CG is outside approved limits!', 0, 1);
                    $pdf->SetTextColor(0, 0, 0);
                }
            }
        }
        
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->MultiCell(0, 4, 'Note: This is a training configuration. Always verify actual weights and balance before flight. Fuel density assumes AVGAS at 0.72 kg/L. Oil weight is included in Basic Empty Weight.');
    }
    
    /**
     * Add Performance data page
     */
    private static function addPerformancePage(\FPDF $pdf, array $aircraft): void
    {
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 15);
        
        // Title
        $pdf->SetFont('Helvetica', 'B', 16);
        $pdf->Cell(0, 10, 'Performance Data - Training Configuration', 0, 1, 'C');
        $pdf->Ln(2);
        
        // Aircraft info
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 7, 'Aircraft: ' . ($aircraft['type_code'] ?? '') . ' - ' . ($aircraft['name'] ?? ''), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 6, 'Generated: ' . date('Y-m-d H:i T'), 0, 1);
        $pdf->Ln(5);
        
        $performance = $aircraft['performance'] ?? [];
        $massBalance = $aircraft['mass_balance'] ?? [];
        
        // Performance data table
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Aircraft Performance Specifications:', 0, 1);
        $pdf->Ln(2);
        
        $performanceData = [
            ['Parameter', 'Value', 'Notes'],
            ['Cruise Speed', ($performance['cruise_speed'] ?? '-') . ' kts', 'At cruise power'],
            ['Stall Speed', ($performance['stall_speed'] ?? '-') . ' kts', 'Clean configuration'],
            ['Climb Rate', ($performance['climb_rate'] ?? '-') . ' ft/min', 'Sea level, ISA'],
            ['Service Ceiling', ($performance['service_ceiling'] ?? '-') . ' ft', ''],
            ['Takeoff Distance', ($performance['takeoff_distance'] ?? '-') . ' m', 'Ground roll, SL, ISA'],
            ['Landing Distance', ($performance['landing_distance'] ?? '-') . ' m', 'Ground roll, SL, ISA'],
            ['Fuel Consumption', ($performance['fuel_consumption'] ?? '-') . ' L/hr', 'Cruise power'],
            ['Max Fuel Capacity', ($massBalance['max_fuel_capacity'] ?? '-') . ' L', ''],
            ['Range', ($performance['range'] ?? '-') . ' nm', 'With reserves'],
            ['Endurance', ($performance['endurance'] ?? '-') . ' hours', ''],
        ];
        
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->SetFillColor(220, 220, 220);
        
        $colWidths = [60, 40, 90];
        $pdf->Cell($colWidths[0], 7, 'Parameter', 1, 0, 'L', true);
        $pdf->Cell($colWidths[1], 7, 'Value', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 7, 'Notes', 1, 1, 'L', true);
        
        $pdf->SetFont('Helvetica', '', 9);
        $first = true;
        foreach ($performanceData as $row) {
            if ($first) {
                $first = false;
                continue; // Skip header row
            }
            $pdf->Cell($colWidths[0], 6, $row[0], 1, 0, 'L');
            $pdf->Cell($colWidths[1], 6, $row[1], 1, 0, 'R');
            $pdf->Cell($colWidths[2], 6, $row[2], 1, 1, 'L');
        }
        
        $pdf->Ln(5);
        
        // Performance notes
        if (isset($performance['notes']) && !empty($performance['notes'])) {
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(0, 7, 'Performance Notes:', 0, 1);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->MultiCell(0, 5, $performance['notes']);
            $pdf->Ln(3);
        }
        
        // Training configuration impact
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 7, 'Training Configuration Impact:', 0, 1);
        $pdf->SetFont('Helvetica', '', 9);
        
        $massBalance = $aircraft['mass_balance'] ?? [];
        $emptyWeight = (float)($massBalance['empty_weight'] ?? 0);
        $maxFuel = (float)($massBalance['max_fuel_capacity'] ?? 0);
        $fuelWeight = $maxFuel * self::FUEL_DENSITY;
        $trainingWeight = $emptyWeight + 105.0 + 90.0 + 4.0 + $fuelWeight;
        
        $pdf->MultiCell(0, 5, sprintf(
            'With training configuration (2 pilots: 195 kg, baggage: 4 kg, full fuel: %.1f kg), ' .
            'the aircraft operates at approximately %.0f kg. This affects takeoff/landing distances, ' .
            'climb performance, and overall aircraft handling. Always consult the POH for weight-adjusted ' .
            'performance data.',
            $fuelWeight,
            $trainingWeight
        ));
        
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'I', 8);
        $pdf->MultiCell(0, 4, 'IMPORTANT: Performance data shown are baseline values at sea level and standard atmosphere. ' .
            'Actual performance will vary with weight, altitude, temperature, wind, and runway conditions. ' .
            'Always refer to the Pilot Operating Handbook (POH) for detailed performance charts and corrections.');
    }
    
    /**
     * Ensure directory exists
     * 
     * @param string $dir Directory path
     */
    private static function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }
        
        if (!mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create directory: {$dir}");
        }
    }
}
