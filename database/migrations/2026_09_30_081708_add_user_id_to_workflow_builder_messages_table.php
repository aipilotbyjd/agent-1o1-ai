<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who sent each user message. A builder session is shared across its
 * workspace, so the edits a turn makes are attributed to the member who
 * asked for them rather than to whoever created the session.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('workflow_builder_messages', function (Blueprint $table) {
            $table->foreignUuid('user_id')->nullable()->after('session_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('workflow_builder_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
