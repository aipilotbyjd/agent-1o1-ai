<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suites re-run on their own when the agent's behavior changes (a new
 * `agent_versions` row), so a regression shows up when it is introduced
 * rather than whenever someone remembers to press Run.
 *
 * `trigger` tells a person's run from an automatic one. `regressed` marks a
 * completed run that passed a smaller share of cases than the suite's
 * previous completed run — the signal worth notifying someone about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_eval_suites', function (Blueprint $table) {
            $table->boolean('run_on_change')->default(true)->after('description');
        });

        Schema::table('agent_eval_runs', function (Blueprint $table) {
            $table->string('trigger')->default('manual')->after('agent_version_id');
            $table->boolean('regressed')->default(false)->after('failed');
        });
    }

    public function down(): void
    {
        Schema::table('agent_eval_runs', function (Blueprint $table) {
            $table->dropColumn(['trigger', 'regressed']);
        });

        Schema::table('agent_eval_suites', function (Blueprint $table) {
            $table->dropColumn('run_on_change');
        });
    }
};
