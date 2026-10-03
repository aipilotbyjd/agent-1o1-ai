<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // `owner_id` set = private to that member (their Brain); null = the
        // workspace's shared knowledge. `knowledge_source_id`/`external_id`
        // tie synced chunks to the document they came from.
        Schema::table('document_embeddings', function (Blueprint $table) {
            $table->foreignUuid('owner_id')->nullable()->after('workspace_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('knowledge_source_id')->nullable()->after('collection')->constrained()->cascadeOnDelete();
            $table->string('external_id')->nullable()->after('knowledge_source_id');

            $table->index(['workspace_id', 'owner_id']);
            $table->index(['knowledge_source_id', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_embeddings', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'owner_id']);
            $table->dropIndex(['knowledge_source_id', 'external_id']);
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('knowledge_source_id');
            $table->dropColumn('external_id');
        });
    }
};
