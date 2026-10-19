<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an attached collection is searched on demand or put in the
 * agent's prompt on every turn: see `App\Enums\Agents\KnowledgeMode`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_knowledge_collections', function (Blueprint $table) {
            $table->string('mode')->default('search')->after('collection');
        });
    }

    public function down(): void
    {
        Schema::table('agent_knowledge_collections', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
