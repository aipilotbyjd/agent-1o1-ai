<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Static per-agent text/file/URL knowledge chunks, injected directly into
 * the agent's instructions (`SkillInjector`) — not retrieved via similarity
 * search, unlike `document_embeddings`/`SearchKnowledgeTool`. "Always know
 * this" vs. "look this up when relevant" — see docs/AGENTS_PLAN.md's
 * "Knowledge / RAG" section.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('agent_knowledge', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('source_type')->default('text');
            $table->string('source_url')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedInteger('tokens')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['agent_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agent_knowledge');
    }
};
