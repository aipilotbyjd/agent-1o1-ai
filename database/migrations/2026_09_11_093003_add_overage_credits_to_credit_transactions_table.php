<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How much of a single charge was served by overage rather than by the plan
 * allowance or the top-up pool. Sits alongside `topup_credits` so one ledger
 * row says which pool each credit came from, which is what the credit-log screen
 * renders and what an invoice line has to be reconcilable against.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->unsignedInteger('overage_credits')->default(0)->after('topup_credits');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropColumn('overage_credits');
        });
    }
};
