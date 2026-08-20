<?php

use App\Enums\Billing\BillingInterval;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes "is this interval for sale" an explicit switch rather than something
 * inferred from a Stripe price being present. Before this, the only way to
 * stop selling lifetime was to blank `stripe_price_id_lifetime` — which
 * throws away the configuration you'd need to turn it back on, and reads as
 * "misconfigured" rather than "deliberately withdrawn".
 *
 * Null means every interval is enabled, so rows predating this column keep
 * behaving exactly as they did. Existing rows are backfilled explicitly
 * anyway, so the catalog is readable without knowing that rule.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->json('enabled_intervals')->nullable()->after('stripe_price_id_lifetime');
        });

        DB::table('plans')->update([
            'enabled_intervals' => json_encode(array_column(BillingInterval::cases(), 'value')),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('enabled_intervals');
        });
    }
};
