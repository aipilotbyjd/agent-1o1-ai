<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a workflow as engine-managed plumbing rather than something a user
 * authored — same precedent as `model_catalog.is_internal`. Set by
 * `Services\Workflows\LoopModeCompiler` on the hidden, single-node child
 * workflow it auto-creates for a node with `config._loop` enabled, so it
 * never appears in workflow listings or counts toward a workspace's
 * `PlanLimit::Workflows` quota.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('is_internal');
        });
    }
};
