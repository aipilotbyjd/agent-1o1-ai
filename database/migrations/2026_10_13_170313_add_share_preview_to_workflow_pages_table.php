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
            // What a link to the page shows when it's pasted into a chat or
            // a post. Null = worked out from the page; see PageSharePreview.
            $table->string('share_title', 120)->nullable()->after('max_runs_per_day');
            $table->string('share_description', 300)->nullable()->after('share_title');
            $table->string('share_image_url', 2048)->nullable()->after('share_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_pages', function (Blueprint $table) {
            $table->dropColumn(['share_title', 'share_description', 'share_image_url']);
        });
    }
};
