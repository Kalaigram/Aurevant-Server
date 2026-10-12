<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Services;

/** Matematika gaji: murni, bisa di-unit-test langsung. */
final class SalaryCalculator
{
    /**
     * @param  list<array{name: string, calc_type: string, deductible: bool, amount: float, taxable?: bool, bpjs_base?: bool}>  $components
     * @return list<array{label: string, amount: float, component_id: int|null}>
     */
    public function calculate(
        array $components,
        int $presentDays,
        int $absentDeductibleDays,
        int $divisor,
        float $commissionTotal,
        string $ptkpStatus = 'TK/0',
    ): array {
        $lines = [];
        $bpjsBase = 0.0;
        $taxBase = 0.0;
        $hasTaxable = false;

        foreach ($components as $component) {
            $earned = match ($component['calc_type']) {
                'monthly_fixed' => (float) $component['amount'],
                'daily_rate' => round((float) $component['amount'] * $presentDays, 2),
                'commission' => round($commissionTotal, 2),
                default => 0.0,
            };

            if (($component['bpjs_base'] ?? false) && $earned > 0) {
                $bpjsBase = round($bpjsBase + $earned, 2);
            }

            if ($component['taxable'] ?? false) {
                $hasTaxable = true;
                if ($earned > 0) {
                    $taxBase = round($taxBase + $earned, 2);
                }
            }

            $built = match ($component['calc_type']) {
                'monthly_fixed' => $this->monthlyFixed($component, $absentDeductibleDays, $divisor),
                'daily_rate' => [[
                    'label' => $component['name'],
                    'amount' => $earned,
                    'component_id' => $component['id'] ?? null,
                ]],
                'commission' => [[
                    'label' => $component['name'],
                    'amount' => $earned,
                    'component_id' => $component['id'] ?? null,
                ]],
                default => [],
            };

            array_push($lines, ...$built);
        }

        // Iuran karyawan: 1% Kes + 2% JHT + 1% JP dari komponen bertanda bpjs_base.
        $bpjs = 0.0;
        if ($bpjsBase > 0) {
            $bpjs = round($bpjsBase * 0.04, 2);
            $lines[] = [
                'label' => 'BPJS karyawan (1% Kes + 2% JHT + 1% JP)',
                'amount' => -$bpjs,
                'component_id' => null,
            ];
        }

        // PPh 21 annualisasi: (dasar kena pajak - BPJS) disetahunkan, PTKP, progresif, /12.
        if ($hasTaxable) {
            $pph = PtkpTable::monthlyTax($taxBase - $bpjs, $ptkpStatus);
            if ($pph > 0) {
                $lines[] = [
                    'label' => 'PPh 21 (annualisasi)',
                    'amount' => -$pph,
                    'component_id' => null,
                ];
            }
        }

        return $lines;
    }

    /** @param array{name: string, amount: float, deductible: bool, id?: int} $component */
    private function monthlyFixed(array $component, int $absentDays, int $divisor): array
    {
        $lines = [[
            'label' => $component['name'],
            'amount' => round($component['amount'], 2),
            'component_id' => $component['id'] ?? null,
        ]];

        if (($component['deductible'] ?? true) && $absentDays > 0 && $divisor > 0) {
            $lines[] = [
                'label' => 'Potongan alpa - '.$component['name'],
                'amount' => -round($component['amount'] / $divisor * $absentDays, 2),
                'component_id' => $component['id'] ?? null,
            ];
        }

        return $lines;
    }
}
