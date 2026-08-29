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
        Schema::create('model_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('model_catalog_id')->constrained('model_catalog')->cascadeOnDelete();
            // A `laravel/ai` provider name — either a native `Lab` enum
            // value (anthropic, openai, bedrock, openrouter, ...) or a
            // custom `openai-compatible` provider key from config/ai.php
            // (fireworks, together, ...).
            $table->string('execution_provider');
            $table->string('execution_model_id');
            $table->foreignId('connector_credential_id')->nullable()->constrained('connector_credentials')->nullOnDelete();
            // Lower runs first — resolved into an ordered `provider` array
            // that `laravel/ai` fails over across on a transient error.
            $table->unsignedInteger('priority')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('options')->nullable();
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamps();

            $table->unique(['model_catalog_id', 'execution_provider', 'execution_model_id'], 'model_routes_unique_route');
            $table->index(['model_catalog_id', 'is_enabled', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('model_routes');
    }
};
