<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Overage is metered per billing period, not per workspace: the cap resets
 * with the allowance, so the running total has to live beside it.
 *
 * `overage_credits_used` counts every credit this period that neither the
 * plan allowance nor the top-up pool could cover — it is a *subset* of
 * `credits_used`, not an addition to it, so the period's own totals stay
 * self-consistent. `overage_credits_billed` tracks how much of that has been
 * invoiced, which is what makes `BillOverageCreditsAction` safe to re-run.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('usage_periods', function (Blueprint $table) {
            $table->unsignedInteger('overage_credits_used')->default(0)->after('credits_limit');
            $table->unsignedInteger('overage_credits_billed')->default(0)->after('overage_credits_used');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_periods', function (Blueprint $table) {
            $table->dropColumn(['overage_credits_used', 'overage_credits_billed']);
        });
    }
};
