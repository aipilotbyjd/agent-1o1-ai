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
            // Bumped whenever either draft changes, so a builder saving over
            // a draft someone else changed since it loaded is refused.
            $table->unsignedInteger('draft_revision')->default(0)->after('variant_split');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_pages', function (Blueprint $table) {
            $table->dropColumn('draft_revision');
        });
    }
};
