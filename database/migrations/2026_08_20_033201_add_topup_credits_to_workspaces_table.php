<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The non-expiring pool credit-pack purchases land in. Packs used to raise
 * the *current* `usage_periods.credits_limit`, which meant purchased credits
 * were deleted at month rollover and clobbered outright the next time
 * `OpenUsagePeriodForSubscriptionAction` reset the limit to the plan's
 * allowance. Keeping them on the workspace decouples them from the billing
 * period entirely — see `ActivateCreditPackAction`.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->unsignedInteger('topup_credits')->default(0)->after('owner_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('topup_credits');
        });
    }
};
