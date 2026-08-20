<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widens the plan catalog from monthly/yearly to the four intervals in
 * `Enums\Billing\BillingInterval`. Quarterly is another recurring Stripe
 * price; lifetime is a one-time price bought through a `mode=payment`
 * Checkout Session (see `Actions\Billing\CheckoutLifetimePlanAction`).
 *
 * A null `stripe_price_id_*` means the plan simply isn't sold on that
 * interval — `Plan::availableIntervals()` reads exactly that, so the Free
 * plan offering nothing needs no extra flag.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('price_quarterly')->default(0)->after('price_monthly');
            $table->unsignedInteger('price_lifetime')->default(0)->after('price_yearly');
            $table->string('stripe_price_id_quarterly')->nullable()->after('stripe_price_id_monthly');
            $table->string('stripe_price_id_lifetime')->nullable()->after('stripe_price_id_yearly');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'price_quarterly',
                'price_lifetime',
                'stripe_price_id_quarterly',
                'stripe_price_id_lifetime',
            ]);
        });
    }
};
