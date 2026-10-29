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
        // Why the circuit breaker or storm detector switched a trigger off
        // (TriggerDisabledReason), so its status can say so. Null while on, or
        // when someone paused it.
        Schema::table('triggers', function (Blueprint $table) {
            $table->string('disabled_reason', 20)->nullable()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropColumn('disabled_reason');
        });
    }
};
