<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scratch bookkeeping for engine-driven nodes that stay "in progress" across
 * multiple settle events instead of a single `execute()` call — `LoopCoordinator`
 * (items/next_index/results/errors) and, later, any other multi-step
 * coordinator. Ordinary `NodeContract` nodes never use this column.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('node_runs', function (Blueprint $table) {
            $table->json('state')->nullable()->after('usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('node_runs', function (Blueprint $table) {
            $table->dropColumn('state');
        });
    }
};
