<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Set when the OAuth round-trip is a reconnect: `handleCallback()` then
 * renews that credential's tokens in place instead of creating a second
 * account, so anything pinned to it keeps working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_connector_states', function (Blueprint $table) {
            $table->uuid('connector_credential_id')->nullable()->after('connector_id');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_connector_states', function (Blueprint $table) {
            $table->dropColumn('connector_credential_id');
        });
    }
};
