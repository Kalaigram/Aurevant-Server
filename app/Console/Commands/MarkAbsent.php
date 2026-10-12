<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Attendance\UseCases\MarkAbsentDaily;
use App\Models\Company;
use Illuminate\Console\Command;

class MarkAbsent extends Command
{
    protected $signature = 'attendance:mark-absent
        {--company= : ID perusahaan}
        {--date= : Tanggal Y-m-d (default kemarin)}';

    protected $description = 'Tandai alpa karyawan yang seharusnya hadir';

    public function handle(MarkAbsentDaily $markAbsent): int
    {
        $company = Company::query()->find($this->option('company'));
        if ($company === null) {
            $this->error('Company tidak ditemukan.');

            return self::FAILURE;
        }

        $date = $this->option('date') ?? now()->subDay()->toDateString();
        $marked = $markAbsent($company, $date);

        $this->info("Alpa {$date} ditandai untuk {$marked} karyawan.");

        return self::SUCCESS;
    }
}
