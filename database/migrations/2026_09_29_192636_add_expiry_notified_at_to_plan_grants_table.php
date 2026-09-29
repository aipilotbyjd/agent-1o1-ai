<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stamped once the workspace has been told a fixed-term grant (earned
 * referral plan time, today) is about to lapse, so the daily reminder
 * command never repeats itself.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plan_grants', function (Blueprint $table) {
            $table->timestamp('expiry_notified_at')->nullable()->after('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_grants', function (Blueprint $table) {
            $table->dropColumn('expiry_notified_at');
        });
    }
};
