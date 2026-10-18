<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Billing\UseCases\SendSubscriptionReminders;
use Illuminate\Console\Command;

final class RemindSubscriptions extends Command
{
    protected $signature = 'subscription:remind {--date= : Tanggal acuan Y-m-d (default hari ini)}';

    protected $description = 'Pengingat langganan H-7/H-3/H-1/H-0 ke Pemilik dan tandai yang sudah berakhir jadi read-only';

    public function handle(SendSubscriptionReminders $useCase): int
    {
        $date = $this->option('date');
        $result = $useCase(is_string($date) && $date !== '' ? $date : null);

        $this->info("Pengingat terkirim: {$result['reminded']}; ditandai berakhir: {$result['expired']}.");

        return self::SUCCESS;
    }
}
