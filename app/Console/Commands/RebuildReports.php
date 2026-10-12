<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Report\UseCases\RebuildDailySummary;
use App\Models\Branch;
use App\Models\Company;
use Illuminate\Console\Command;

class RebuildReports extends Command
{
    protected $signature = 'reports:rebuild
        {--company= : ID perusahaan}
        {--branch= : ID cabang (opsional, semua bila kosong)}
        {--date= : Tanggal Y-m-d (default hari ini)}';

    protected $description = 'Bangun ulang ringkasan harian cabang';

    public function handle(RebuildDailySummary $rebuild): int
    {
        $company = Company::query()->find($this->option('company'));
        if ($company === null) {
            $this->error('Company tidak ditemukan.');

            return self::FAILURE;
        }

        $date = $this->option('date') ?? now()->toDateString();
        $query = Branch::query()->where('company_id', $company->id);

        if ($this->option('branch') !== null) {
            $query->whereKey((int) $this->option('branch'));
        }

        $count = 0;
        foreach ($query->get() as $branch) {
            $rebuild($branch, $date);
            $count++;
        }

        $this->info("Ringkasan {$date} dibangun untuk {$count} cabang.");

        return self::SUCCESS;
    }
}
