<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            // When a schedule trigger is next due, in UTC: the scheduler
            // queries this index instead of parsing every cron each minute,
            // and a tick missed while the scheduler was down is still caught.
            $table->timestamp('next_run_at')->nullable()->after('last_run_at');
            // Deliveries dropped in a row (out of credits, or still running
            // under the "skip" overlap policy): a separate breaker from
            // failures, see Trigger::recordSkip().
            $table->unsignedInteger('consecutive_skip_count')->default(0)->after('consecutive_failure_count');

            $table->index(['type', 'is_active', 'next_run_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropIndex(['type', 'is_active', 'next_run_at']);
            $table->dropColumn(['next_run_at', 'consecutive_skip_count']);
        });
    }
};
