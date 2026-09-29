<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Carries the requested `ConnectorCredentialScope` through the OAuth
 * round-trip, so `OAuthConnectorFlowService::handleCallback()` knows
 * whether to create a personal or team credential — the provider callback
 * has no request body of its own to read it from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oauth_connector_states', function (Blueprint $table) {
            $table->string('scope')->default('team')->after('connector_id');
        });
    }

    public function down(): void
    {
        Schema::table('oauth_connector_states', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
