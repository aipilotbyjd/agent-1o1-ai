<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How freely an agent may act — see `Services\Agents\Approvals\ActionGate`.
 *
 * `autonomy_mode` lives on the agent with an optional per-session override
 * (a trigger or a person can make one conversation stricter). It is read
 * live, never pinned by `AgentSession::pinnedAgent()`: what an agent is
 * allowed to do is a safety setting, and tightening it must reach
 * conversations already in flight.
 *
 * `approval_policy` on the tool pivots is the per-tool rule (allow / ask /
 * deny, conditions, rate limit, approvers). `paused_state` holds what a turn
 * waiting on an approval needs to resume exactly where it stopped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('autonomy_mode')->default('ask')->after('allow_self_clone');
            $table->boolean('test_mode')->default(false)->after('autonomy_mode');
            $table->boolean('allow_web_fetch')->default(true)->after('test_mode');
        });

        // Agents that already exist ran every tool freely; switching them to
        // Ask would silently pause live automations. They keep running,
        // apart from destructive actions, which now ask. New agents start
        // on Ask.
        DB::table('agents')->update(['autonomy_mode' => 'autopilot']);

        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->string('autonomy_mode')->nullable()->after('parent_session_id');
            $table->boolean('test_mode')->nullable()->after('autonomy_mode');
        });

        Schema::table('agent_node', function (Blueprint $table) {
            $table->json('approval_policy')->nullable()->after('exposed_fields');
        });

        Schema::table('agent_workflow', function (Blueprint $table) {
            $table->json('approval_policy')->nullable();
        });

        Schema::table('agent_messages', function (Blueprint $table) {
            $table->json('paused_state')->nullable()->after('tool_results');
        });
    }

    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->dropColumn('paused_state');
        });

        Schema::table('agent_workflow', function (Blueprint $table) {
            $table->dropColumn('approval_policy');
        });

        Schema::table('agent_node', function (Blueprint $table) {
            $table->dropColumn('approval_policy');
        });

        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->dropColumn(['autonomy_mode', 'test_mode']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['autonomy_mode', 'test_mode', 'allow_web_fetch']);
        });
    }
};
