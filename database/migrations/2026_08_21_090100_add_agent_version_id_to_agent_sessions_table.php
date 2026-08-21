<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pins a conversation to the agent behavior it started with, the way a
     * `Run` pins to a `WorkflowVersion` — docs/AGENTS_PLAN.md's reason for
     * building `agent_versions` from day one ("an AgentSession mid-
     * conversation shouldn't have its behavior change out from under it").
     *
     * `nullOnDelete` and nullable: sessions created before versioning
     * existed have no pin and fall back to the live agent, which is exactly
     * the old behavior.
     */
    public function up(): void
    {
        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->foreignId('agent_version_id')->nullable()->after('agent_id')->constrained('agent_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_version_id');
        });
    }
};
