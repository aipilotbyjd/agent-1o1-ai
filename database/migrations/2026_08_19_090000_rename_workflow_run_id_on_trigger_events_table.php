<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The column was named when a trigger could only ever start a workflow
     * (docs/TRIGGERS_PLAN.md's own "follow-up needed" note). Now that
     * `TargetRunStarter` also starts agent sessions — whose turns are `runs`
     * rows too — the id it stores is polymorphic, so the name follows suit
     * and the deferred FK to `runs` can finally be added.
     */
    public function up(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->renameColumn('workflow_run_id', 'run_id');
        });

        Schema::table('trigger_events', function (Blueprint $table) {
            $table->foreign('run_id')->references('id')->on('runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trigger_events', function (Blueprint $table) {
            $table->dropForeign(['run_id']);
        });

        Schema::table('trigger_events', function (Blueprint $table) {
            $table->renameColumn('run_id', 'workflow_run_id');
        });
    }
};
