<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['name' => 'Gratis', 'max_branches' => 1, 'max_employees' => 5, 'features' => ['kasir', 'stok'], 'price' => 0],
            ['name' => 'Dasar', 'max_branches' => 3, 'max_employees' => 20, 'features' => ['kasir', 'stok', 'po', 'penggajian'], 'price' => 99000],
            ['name' => 'Pro', 'max_branches' => 100, 'max_employees' => 1000, 'features' => ['kasir', 'stok', 'po', 'penggajian', 'laporan'], 'price' => 299000],
        ] as $plan) {
            Plan::firstOrCreate(['name' => $plan['name']], $plan);
        }
    }
}
