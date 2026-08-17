<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files an agent produces during a session — versioned by `group_id` so a
 * re-generated file (e.g. an updated report) becomes a new row sharing the
 * same group rather than overwriting history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agent_session_id')->nullable()->constrained('agent_sessions')->nullOnDelete();
            $table->foreignId('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('group_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
            $table->index(['agent_session_id', 'filename']);
            $table->index(['group_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
