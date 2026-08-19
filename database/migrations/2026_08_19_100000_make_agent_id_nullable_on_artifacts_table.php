<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Artifacts were agent-produced only (`ExportArtifactTool`), so `agent_id`
 * was required. A workspace member can now upload one directly, and an
 * upload has no agent behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable(false)->change();
        });
    }
};
