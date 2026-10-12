<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Payroll\Services\SalaryCalculator;
use PHPUnit\Framework\TestCase;

class SalaryCalculatorTest extends TestCase
{
    public function test_contoh_spek_gaji_pokok_tunjangan_makan(): void
    {
        $calc = new SalaryCalculator;

        $lines = $calc->calculate(
            [
                ['id' => 1, 'name' => 'Gaji pokok', 'calc_type' => 'monthly_fixed', 'deductible' => true, 'amount' => 3000000],
                ['id' => 2, 'name' => 'Tunjangan tetap', 'calc_type' => 'monthly_fixed', 'deductible' => true, 'amount' => 600000],
                ['id' => 3, 'name' => 'Uang makan', 'calc_type' => 'daily_rate', 'deductible' => false, 'amount' => 25000],
            ],
            22,
            1,
            30,
            0.0,
        );

        $byLabel = [];
        foreach ($lines as $line) {
            $byLabel[$line['label']] = $line['amount'];
        }

        $this->assertEquals(3000000.0, $byLabel['Gaji pokok']);
        $this->assertEquals(-100000.0, $byLabel['Potongan alpa - Gaji pokok']);
        $this->assertEquals(600000.0, $byLabel['Tunjangan tetap']);
        $this->assertEquals(-20000.0, $byLabel['Potongan alpa - Tunjangan tetap']);
        $this->assertEquals(550000.0, $byLabel['Uang makan']);

        $net = array_sum(array_column($lines, 'amount'));
        $this->assertEquals(3000000 - 100000 + 600000 - 20000 + 550000, $net);
    }

    public function test_tanpa_alpa_tanpa_potongan_dan_komisi_otomatis(): void
    {
        $calc = new SalaryCalculator;

        $lines = $calc->calculate(
            [
                ['id' => 1, 'name' => 'Gaji pokok', 'calc_type' => 'monthly_fixed', 'deductible' => true, 'amount' => 3000000],
                ['id' => 4, 'name' => 'Komisi jasa', 'calc_type' => 'commission', 'deductible' => false, 'amount' => 0],
            ],
            25,
            0,
            30,
            350000.0,
        );

        $this->assertCount(2, $lines);
        $this->assertEquals(350000.0, $lines[1]['amount']);
    }

    public function test_bpjs_dan_pph21_dari_flag_komponen(): void
    {
        $calc = new SalaryCalculator;

        $lines = $calc->calculate(
            [
                ['id' => 1, 'name' => 'Gaji pokok', 'calc_type' => 'monthly_fixed', 'deductible' => true, 'amount' => 5000000, 'taxable' => true, 'bpjs_base' => true],
                ['id' => 2, 'name' => 'Tunjangan', 'calc_type' => 'monthly_fixed', 'deductible' => false, 'amount' => 1000000, 'taxable' => true, 'bpjs_base' => false],
            ],
            25,
            0,
            25,
            0.0,
        );

        $byLabel = [];
        foreach ($lines as $line) {
            $byLabel[$line['label']] = $line['amount'];
        }

        // BPJS = 4% x 5.000.000 = 200.000
        $this->assertEquals(-200000.0, $byLabel['BPJS karyawan (1% Kes + 2% JHT + 1% JP)']);
        // PPh annualisasi: (6.000.000-200.000)x12=69.600.000 - jabatan 3.480.000
        // - PTKP 54.000.000 = PKP 12.120.000 x 5% = 606.000 / 12 = 50.500
        $this->assertEquals(-50500.0, $byLabel['PPh 21 (annualisasi)']);
        $this->assertEquals(6000000 - 200000 - 50500, array_sum(array_column($lines, 'amount')));
    }

    public function test_pph21_progresif_lapis_ketiga(): void
    {
        $calc = new SalaryCalculator;

        $lines = $calc->calculate(
            [
                ['id' => 1, 'name' => 'Gaji pokok', 'calc_type' => 'monthly_fixed', 'deductible' => false, 'amount' => 30000000, 'taxable' => true, 'bpjs_base' => true],
            ],
            25,
            0,
            25,
            0.0,
            'TK/0',
        );

        $byLabel = [];
        foreach ($lines as $line) {
            $byLabel[$line['label']] = $line['amount'];
        }

        // BPJS 1.200.000; kena 28.800.000/bln -> PKP 285.600.000
        // 5%x60jt + 15%x190jt + 25%x35,6jt = 40.400.000 / 12
        $this->assertEquals(-1200000.0, $byLabel['BPJS karyawan (1% Kes + 2% JHT + 1% JP)']);
        $this->assertEquals(-3366666.67, $byLabel['PPh 21 (annualisasi)']);
    }
}
