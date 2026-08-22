<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a `CustomNode` actually *does* when the engine reaches it. The table
 * shipped with schemas only (`config_schema`/`input_schema`/`output_schema`),
 * which described a custom node's interface but left it unrunnable —
 * `NodeRegistry::has()` answered false for every `custom:{id}` as a result.
 *
 * Nullable because every row that already exists predates execution: those
 * nodes stay definition-only and keep failing with the same message they do
 * today, rather than being retro-fitted with an implementation nobody wrote.
 * See `App\Nodes\Custom\CustomHttpNode` for the shape this column holds.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_nodes', function (Blueprint $table) {
            $table->json('implementation')->nullable()->after('config_schema');
        });
    }

    public function down(): void
    {
        Schema::table('custom_nodes', function (Blueprint $table) {
            $table->dropColumn('implementation');
        });
    }
};
