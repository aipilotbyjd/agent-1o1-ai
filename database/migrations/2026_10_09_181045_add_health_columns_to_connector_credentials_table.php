<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the provider last said about a connection: whose account it is
 * (`account_label`, e.g. an email or username) and whether the last check
 * passed. Written by `ConnectorCredentialTester`; never secret.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connector_credentials', function (Blueprint $table) {
            $table->string('account_label')->nullable()->after('name');
            $table->timestamp('last_tested_at')->nullable()->after('last_used_at');
            $table->boolean('last_test_ok')->nullable()->after('last_tested_at');
            $table->string('last_test_message', 500)->nullable()->after('last_test_ok');
        });
    }

    public function down(): void
    {
        Schema::table('connector_credentials', function (Blueprint $table) {
            $table->dropColumn(['account_label', 'last_tested_at', 'last_test_ok', 'last_test_message']);
        });
    }
};
