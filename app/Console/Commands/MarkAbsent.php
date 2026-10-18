<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Attendance\UseCases\MarkAbsentDaily;
use App\Models\Company;
use Illuminate\Console\Command;

class MarkAbsent extends Command
{
    protected $signature = 'attendance:mark-absent
        {--company= : ID perusahaan (kosong = semua perusahaan aktif)}
        {--date= : Tanggal Y-m-d (default kemarin)}';

    protected $description = 'Tandai alpa karyawan yang seharusnya hadir';

    public function handle(MarkAbsentDaily $markAbsent): int
    {
        $date = $this->option('date') ?? now()->subDay()->toDateString();
        $companyId = $this->option('company');

        if ($companyId !== null) {
            $company = Company::query()->find($companyId);
            if ($company === null) {
                $this->error('Company tidak ditemukan.');

                return self::FAILURE;
            }

            $marked = $markAbsent($company, $date);
            $this->info("Alpa {$date} ditandai untuk {$marked} karyawan.");

            return self::SUCCESS;
        }

        $total = 0;
        Company::query()
            ->where('status', 'aktif')
            ->orderBy('id')
            ->each(function (Company $company) use ($markAbsent, $date, &$total): void {
                $total += $markAbsent($company, $date);
            });

        $this->info("Alpa {$date} ditandai untuk {$total} karyawan di semua perusahaan.");

        return self::SUCCESS;
    }
}
