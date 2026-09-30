<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `Workflow::graphFingerprint()` of the session's workflow as the session
 * last saw it — when it was opened on that workflow, or last promoted to it.
 * Promoting compares it against the workflow's current graph, so edits made
 * in the regular editor since then aren't silently overwritten.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_builder_sessions', function (Blueprint $table) {
            $table->string('workflow_graph_hash', 64)->nullable()->after('workflow_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_builder_sessions', function (Blueprint $table) {
            $table->dropColumn('workflow_graph_hash');
        });
    }
};
