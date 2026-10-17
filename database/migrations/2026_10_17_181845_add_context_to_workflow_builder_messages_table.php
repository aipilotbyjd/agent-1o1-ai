<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The steps and connected accounts a user message referenced with `/` and
 * `@`: see `StoreWorkflowBuilderMessageRequest::context()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_builder_messages', function (Blueprint $table) {
            $table->json('context')->nullable()->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_builder_messages', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
