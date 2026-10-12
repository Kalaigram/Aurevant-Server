<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Billing\UseCases\ManageInvoices;
use Illuminate\Console\Command;

class GenerateInvoices extends Command
{
    protected $signature = 'billing:generate';

    protected $description = 'Terbitkan tagihan langganan yang masa aktifnya habis <= 7 hari';

    public function handle(ManageInvoices $invoices): int
    {
        $count = $invoices->generateDue();

        $this->info("Tagihan diterbitkan: {$count}.");

        return self::SUCCESS;
    }
}
