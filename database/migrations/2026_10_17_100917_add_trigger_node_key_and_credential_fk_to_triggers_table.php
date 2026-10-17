<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            // The canvas node that owns this row (`TriggerNodeSync`). Null for a
            // trigger authored directly through the API: agent targets and
            // standalone workflow triggers.
            $table->string('trigger_node_key')->nullable()->after('target_id');
            $table->unique(['target_type', 'target_id', 'trigger_node_key']);
        });

        // The FK the original migration deferred until connector_credentials
        // existed. Any id left pointing at nothing is cleared first, or the
        // constraint can't be added.
        DB::table('triggers')
            ->whereNotNull('credential_id')
            ->whereNotIn('credential_id', DB::table('connector_credentials')->select('id'))
            ->update(['credential_id' => null]);

        Schema::table('triggers', function (Blueprint $table) {
            $table->foreign('credential_id')->references('id')->on('connector_credentials')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('triggers', function (Blueprint $table) {
            $table->dropForeign(['credential_id']);
            $table->dropUnique(['target_type', 'target_id', 'trigger_node_key']);
            $table->dropColumn('trigger_node_key');
        });
    }
};
