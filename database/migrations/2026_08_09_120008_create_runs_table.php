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
        Schema::create('runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->morphs('runnable');
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->cascadeOnDelete();
            $table->foreignId('workflow_version_id')->nullable()->constrained('workflow_versions')->nullOnDelete();
            $table->foreignId('parent_run_id')->nullable()->constrained('runs')->nullOnDelete();

            // FK to node_runs added once that table exists — see
            // create_node_runs_table / the parent_node_id foreign-key follow-up
            // migration, same "column now, constraint later" pattern the
            // triggers table already uses for credential_id.
            $table->unsignedBigInteger('parent_node_id')->nullable();

            $table->unsignedInteger('loop_index')->nullable();
            $table->unsignedBigInteger('environment_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('trigger_type')->default('manual');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
            $table->index(['parent_node_id', 'loop_index']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('runs');
    }
};
