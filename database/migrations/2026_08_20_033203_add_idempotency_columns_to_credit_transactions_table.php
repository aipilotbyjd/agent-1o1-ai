<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes the usage ledger safe to replay. `RecordRunCreditUsage` charges one
 * row per node run inside a queued job; if any single charge failed partway
 * through the loop the job retried and re-charged every node before it, so
 * `(source_type, source_id)` becomes unique — one `NodeRun`/`AgentMessage`
 * can be billed exactly once, enforced by the database rather than by the
 * caller remembering to check.
 *
 * `usage_period_id` records which period a charge was counted against (the
 * ledger previously had no way to reconcile against `credits_used`), and
 * `topup_credits` records how much of the charge was drawn from the
 * workspace's non-expiring pool rather than the plan allowance.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->foreignId('usage_period_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->unsignedInteger('topup_credits')->default(0)->after('credits');

            $table->unique(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credit_transactions', function (Blueprint $table) {
            $table->dropUnique(['source_type', 'source_id']);
            $table->dropColumn('topup_credits');
            $table->dropConstrainedForeignId('usage_period_id');
        });
    }
};
