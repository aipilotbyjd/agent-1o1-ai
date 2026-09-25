<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files a member attached to one specific chat message — see
 * `App\Models\Agents\AgentMessage::attachments()`. Null for every other
 * artifact (agent exports and standalone uploads).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignUuid('agent_message_id')->nullable()->after('agent_session_id')->constrained('agent_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_message_id');
        });
    }
};
