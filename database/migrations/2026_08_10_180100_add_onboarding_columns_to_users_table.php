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
        Schema::table('users', function (Blueprint $table) {
            $table->string('onboarding_current_step')->nullable()->default('profile_picture')->after('current_workspace_id');
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_current_step');
            $table->timestamp('onboarding_dismissed_at')->nullable()->after('onboarding_completed_at');
            $table->string('job_role')->nullable()->after('onboarding_dismissed_at');
            $table->string('discovery_source')->nullable()->after('job_role');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_current_step', 'onboarding_completed_at', 'onboarding_dismissed_at', 'job_role', 'discovery_source']);
        });
    }
};
