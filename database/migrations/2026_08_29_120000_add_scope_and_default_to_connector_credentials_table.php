<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `scope` splits a credential into Gumloop's Personal (`created_by`-only,
 * private even from other workspace members) vs Team (workspace-shared,
 * this table's original behavior) — see
 * docs/gumloop/output/raw/core-concepts/credentials.md. `is_default` is
 * which credential a node/agent gets when nothing pins a `credential_id` —
 * see `ResolvesConnectorCredential`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connector_credentials', function (Blueprint $table) {
            $table->string('scope')->default('team')->after('connector_id');
            $table->boolean('is_default')->default(false)->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('connector_credentials', function (Blueprint $table) {
            $table->dropColumn(['scope', 'is_default']);
        });
    }
};
