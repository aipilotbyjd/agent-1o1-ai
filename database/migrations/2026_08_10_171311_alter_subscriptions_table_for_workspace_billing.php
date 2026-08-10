<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier's published migration hardcodes `user_id` — this app's Billable is
 * `Workspace`, not `User` (see docs/STRUCTURE.md). Renamed here rather than
 * editing the original migration since it has already run.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'stripe_status']);
            $table->renameColumn('user_id', 'workspace_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->index(['workspace_id', 'stripe_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropColumn('plan_id');
            $table->dropIndex(['workspace_id', 'stripe_status']);
            $table->dropForeign(['workspace_id']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->renameColumn('workspace_id', 'user_id');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['user_id', 'stripe_status']);
        });
    }
};
