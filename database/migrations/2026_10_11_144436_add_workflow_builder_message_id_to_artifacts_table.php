<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Files a member attached to one workflow-builder chat message: see
 * `App\Models\Workflows\Builder\WorkflowBuilderMessage::attachments()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->foreignUuid('workflow_builder_message_id')->nullable()->after('agent_message_id')->constrained('workflow_builder_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('artifacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_builder_message_id');
        });
    }
};
