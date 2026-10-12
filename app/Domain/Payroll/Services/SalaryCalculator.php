<?php

declare(strict_types=1);

namespace App\Domain\Payroll\Services;

/** Matematika gaji: murni, bisa di-unit-test langsung. */
final class SalaryCalculator
{
    /**
     * @param  list<array{name: string, calc_type: string, deductible: bool, amount: float}>  $components
     * @return list<array{label: string, amount: float, component_id: int|null}>
     */
    public function calculate(
        array $components,
        int $presentDays,
        int $absentDeductibleDays,
        int $divisor,
        float $commissionTotal,
    ): array {
        $lines = [];

        foreach ($components as $component) {
            $built = match ($component['calc_type']) {
                'monthly_fixed' => $this->monthlyFixed($component, $absentDeductibleDays, $divisor),
                'daily_rate' => [[
                    'label' => $component['name'],
                    'amount' => round($component['amount'] * $presentDays, 2),
                    'component_id' => $component['id'] ?? null,
                ]],
                'commission' => [[
                    'label' => $component['name'],
                    'amount' => round($commissionTotal, 2),
                    'component_id' => $component['id'] ?? null,
                ]],
                default => [],
            };

            array_push($lines, ...$built);
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
