<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_subagents', function (Blueprint $table) {
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('subagent_id')->constrained('agents')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['agent_id', 'subagent_id']);
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->boolean('allow_self_clone')->default(true)->after('allow_skill_editing');
        });

        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->foreignUuid('parent_session_id')->nullable()->after('agent_version_id')
                ->constrained('agent_sessions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_session_id');
        });

        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('allow_self_clone');
        });

        Schema::dropIfExists('agent_subagents');
    }
};
