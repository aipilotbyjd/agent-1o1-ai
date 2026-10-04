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
        Schema::table('skill_sources', function (Blueprint $table) {
            $table->boolean('two_way')->default(false);
            $table->json('sync_baseline')->nullable();
            $table->json('sync_conflicts')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skill_sources', function (Blueprint $table) {
            $table->dropColumn(['two_way', 'sync_baseline', 'sync_conflicts']);
        });
    }
};
