<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lineage for `RetryRunAction`: a retry is a brand-new `Run` (its own
     * node_runs, its own credit ledger entries) rather than a mutation of the
     * original, so the only thing tying the two together is this pointer.
     * `nullOnDelete` — losing the original run should not delete its retries.
     */
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->foreignId('retried_from_run_id')->nullable()->after('parent_run_id')->constrained('runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('retried_from_run_id');
        });
    }
};
