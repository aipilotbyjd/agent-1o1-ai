<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The chat transcript for a `WorkflowBuilderSession` — distinct from
 * `agent_messages` (Stage 6), since this transcript is scoped to editing one
 * workflow draft, not a general-purpose `Agent` conversation.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('workflow_builder_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('workflow_builder_sessions')->cascadeOnDelete();
            $table->foreignId('draft_version_id')->nullable()
                ->constrained('workflow_builder_draft_versions')->nullOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('actions')->nullable();
            $table->string('processing_status', 20)->default('completed');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_builder_messages');
    }
};
