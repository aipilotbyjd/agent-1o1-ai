<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an agent chat turn was actually sent: the final system prompt (base
 * instructions plus everything injected into them), the model it ran on and
 * the tools it was offered. None of it can be rebuilt later — skills,
 * memories and knowledge change after the turn — and it is what answers
 * "why did the agent do that?" when inspecting a run.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->json('agent_context')->nullable()->after('output');
        });
    }

    public function down(): void
    {
        Schema::table('runs', function (Blueprint $table) {
            $table->dropColumn('agent_context');
        });
    }
};
