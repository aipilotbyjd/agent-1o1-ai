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
        Schema::table('skill_sources', function (Blueprint $table): void {
            $table->boolean('publish_once')->default(false);
            $table->boolean('repository_private')->nullable();
            $table->string('upstream_repo')->nullable();
            $table->string('upstream_branch')->nullable();
            $table->json('fork_request')->nullable();
        });
        Schema::table('skills', function (Blueprint $table): void {
            $table->text('origin_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skill_sources', fn (Blueprint $table) => $table->dropColumn(['publish_once', 'repository_private', 'upstream_repo', 'upstream_branch', 'fork_request']));
        Schema::table('skills', fn (Blueprint $table) => $table->dropColumn('origin_url'));
    }
};
