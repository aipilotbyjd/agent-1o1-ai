<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting an artifact is a soft delete: the stored bytes and row survive,
 * so a later re-export with the same filename continues the same group's
 * version history instead of starting over. `general_access` is the sharing
 * tier the whole group is visible at — see `App\Enums\Artifacts\ArtifactGeneralAccess`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('general_access')->default('restricted')->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('general_access');
        });
    }
};
