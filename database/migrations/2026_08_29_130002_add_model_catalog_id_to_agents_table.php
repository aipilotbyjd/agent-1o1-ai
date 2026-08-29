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
        Schema::table('agents', function (Blueprint $table) {
            // Nullable and additive: an agent with no catalog entry keeps
            // using its plain `provider`/`model` columns unchanged (see
            // `AgentRunner::openTurn()`/`ask()`). Only opting an agent into
            // `model_catalog_id` switches it onto the resolved, potentially
            // multi-backend failover chain.
            $table->foreignId('model_catalog_id')->nullable()->after('model')
                ->constrained('model_catalog')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('model_catalog_id');
        });
    }
};
