<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A version number is allocated by reading the group's latest one, so two
 * concurrent stores can pick the same number. Uniqueness makes the database
 * reject the loser (`StoreArtifactAction` re-reads and retries) instead of
 * letting two rows share a version — and a storage path.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropIndex(['group_id', 'version']);
            $table->unique(['group_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropUnique(['group_id', 'version']);
            $table->index(['group_id', 'version']);
        });
    }
};
