<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A node that has settled but whose successors have not been created yet is
 * still work in flight. Without this flag the run can look finished in the
 * gap between a node settling and its `DispatchNextNodesJob` running, while
 * a sibling branch's completion check races ahead of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('node_runs', function (Blueprint $table) {
            $table->boolean('pending_advance')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('node_runs', function (Blueprint $table) {
            $table->dropColumn('pending_advance');
        });
    }
};
