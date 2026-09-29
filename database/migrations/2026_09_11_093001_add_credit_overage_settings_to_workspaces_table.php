<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Credit overage — the opt-in that lets a workspace keep running once its
 * plan allowance and top-up pool are both spent, billed after the fact at
 * `config('billing.credit_value_usd')` per credit.
 *
 * Off by default: a workspace that never touches this setting is still hard
 * refused at zero by `CreditGate`, which is the behaviour every existing
 * workspace already has. `credit_overage_limit` is the workspace's own
 * ceiling on how many overage credits one billing period may accrue; `null`
 * means it hasn't set one and `config('billing.overage.default_limit')`
 * applies instead. A workspace may only ever lower that ceiling, never raise
 * it, so overage can't run away unbounded.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->boolean('credit_overage_enabled')->default(false)->after('topup_credits');
            $table->unsignedInteger('credit_overage_limit')->nullable()->after('credit_overage_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['credit_overage_enabled', 'credit_overage_limit']);
        });
    }
};
