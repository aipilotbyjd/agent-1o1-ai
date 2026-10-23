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
        });
    }
};
