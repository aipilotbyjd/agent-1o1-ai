<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The skill a person picked for a message (typing `/` in the chat). Its
 * instructions are added to that turn's prompt, so the agent follows it
 * without having to decide to load it. Kept on the message so the chat can
 * show which skill was asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->foreignUuid('skill_id')->nullable()->after('content')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('skill_id');
        });
    }
};
