<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The builder never used `laravel/ai`'s `RemembersConversations` trail —
 * its history is the session's own `workflow_builder_messages` — so the
 * column only ever held nulls.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_builder_sessions', function (Blueprint $table) {
            $table->dropIndex(['conversation_id']);
            $table->dropColumn('conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_builder_sessions', function (Blueprint $table) {
            $table->string('conversation_id')->nullable()->index()->after('workflow_id');
        });
    }
};
