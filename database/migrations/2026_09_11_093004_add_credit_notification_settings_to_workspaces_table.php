<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gumloop's Credit Notification Preferences: an "Out of Credits" toggle,
 * on by default, and a set of usage thresholds that default to 75% and 90%.
 *
 * Both are workspace-level switches over what is sent at all, which is a
 * different question from `notification_preferences`' per-user, per-event
 * choice of *how* to receive what is sent. A `null`
 * `credit_usage_notification_thresholds` means the workspace never chose,
 * so `config('billing.credit_notifications.default_thresholds')` applies;
 * an empty array is a deliberate "notify me at no threshold" and is left
 * alone.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->json('credit_usage_notification_thresholds')->nullable()->after('credit_overage_limit');
            $table->boolean('out_of_credits_notification_enabled')->default(true)->after('credit_usage_notification_thresholds');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['credit_usage_notification_thresholds', 'out_of_credits_notification_enabled']);
        });
    }
};
