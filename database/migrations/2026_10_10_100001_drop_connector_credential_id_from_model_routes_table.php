<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The placeholder this column was is replaced by `ai_provider_credentials`:
 * whose key runs a call is decided per workspace at call time, never per
 * platform-wide route.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_routes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('connector_credential_id');
        });
    }

    public function down(): void
    {
        Schema::table('model_routes', function (Blueprint $table) {
            $table->foreignUuid('connector_credential_id')->nullable()->after('execution_model_id')->constrained('connector_credentials')->nullOnDelete();
        });
    }
};
