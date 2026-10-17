<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The draft exactly as the author left it when publishing: positions
     * included, loop-mode nodes not yet compiled. `graph` is what runs; this is
     * what a person previews on the canvas and restores into the draft.
     * Nullable: versions published before this column have no record of it.
     */
    public function up(): void
    {
        Schema::table('workflow_versions', function (Blueprint $table) {
            $table->json('editor_graph')->nullable()->after('graph');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_versions', function (Blueprint $table) {
            $table->dropColumn('editor_graph');
        });
    }
};
