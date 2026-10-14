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
        Schema::table('workflow_pages', function (Blueprint $table) {
            // An A/B test: version B's draft and live content, and the share
            // of visitors (0–100) who get B while it's live.
            $table->json('variant_content')->nullable()->after('published_content');
            $table->json('published_variant_content')->nullable()->after('variant_content');
            $table->unsignedTinyInteger('variant_split')->default(50)->after('published_variant_content');
        });

        Schema::table('workflow_page_submissions', function (Blueprint $table) {
            $table->char('variant', 1)->default('a')->after('is_test');
            // The values submitted, kept with the submission so its history
            // outlives the run's.
            $table->json('input')->nullable()->after('variant');
        });

        Schema::create('workflow_page_events', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('workflow_page_id')->constrained('workflow_pages')->cascadeOnDelete();
            $table->char('variant', 1)->default('a');
            $table->string('type', 16);
            $table->string('visitor_hash', 64);
            $table->date('day');
            $table->timestamp('created_at')->nullable();

            // One of each kind per visitor per day, so counts are unique visitors.
            $table->unique(['workflow_page_id', 'type', 'variant', 'visitor_hash', 'day'], 'workflow_page_events_unique_visit');
            $table->index(['workflow_page_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_page_events');

        Schema::table('workflow_page_submissions', function (Blueprint $table) {
            $table->dropColumn(['variant', 'input']);
        });

        Schema::table('workflow_pages', function (Blueprint $table) {
            $table->dropColumn(['variant_content', 'published_variant_content', 'variant_split']);
        });
    }
};
