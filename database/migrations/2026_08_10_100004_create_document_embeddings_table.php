<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `embedding` is stored as JSON, deliberately not a native `vector` column
 * — Laravel's native vector support (`$table->vector()`,
 * `whereVectorSimilarTo()`) is Postgres/pgvector-only, and this project's
 * tests run against sqlite. `SearchKnowledgeTool` ranks by cosine similarity
 * computed in PHP instead. See docs/AGENTS_PLAN.md's "Knowledge / RAG"
 * section for the documented follow-up path to a native column once
 * pgvector is approved as a dependency.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('document_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('collection')->default('default');
            $table->string('source')->nullable();
            $table->longText('chunk_text');
            $table->json('embedding');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'collection']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_embeddings');
    }
};
