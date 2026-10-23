<?php

namespace Database\Seeders;

use App\Services\Ai\ModelPrices;
use Illuminate\Database\Seeder;

/**
 * Moves the prices that used to live only in `config/billing.php` into the
 * `model_prices` table, where a super admin can maintain them. Safe to run
 * again: it never overwrites a price that is already there.
 */
class ModelPriceSeeder extends Seeder
{
    public function run(): void
    {
        app(ModelPrices::class)->importFromConfig();
    }
}
