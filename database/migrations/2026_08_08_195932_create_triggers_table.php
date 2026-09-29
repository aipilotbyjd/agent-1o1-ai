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
        Schema::create('triggers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuidMorphs('target');
            $table->string('type');
            $table->foreignUuid('preset_id')->nullable()->constrained('trigger_presets')->nullOnDelete();
            $table->json('config')->nullable();
            $table->string('token', 64)->nullable()->unique();
            $table->text('signing_secret')->nullable();
            $table->boolean('is_active')->default(true);

            // No FK yet — connector_credentials doesn't exist until Connectors ship
            // (docs/NODES_CATALOG.md build order). Column is real; constraint follows.
            $table->uuid('credential_id')->nullable();

            $table->json('poll_cursor')->nullable();
            $table->unsignedInteger('consecutive_failure_count')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('triggers');
    }
};
