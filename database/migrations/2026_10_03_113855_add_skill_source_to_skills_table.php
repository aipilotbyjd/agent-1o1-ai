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
        // A synced skill points at its source and the repository folder it
        // came from; a skill people write in the app has neither.
        Schema::table('skills', function (Blueprint $table) {
            $table->foreignUuid('skill_source_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->string('source_path')->nullable()->after('skill_source_id');

            $table->index(['skill_source_id', 'source_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skills', function (Blueprint $table) {
            $table->dropIndex(['skill_source_id', 'source_path']);
            $table->dropConstrainedForeignId('skill_source_id');
            $table->dropColumn('source_path');
        });
    }
};
