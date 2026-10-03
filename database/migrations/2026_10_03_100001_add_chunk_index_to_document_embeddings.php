<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `chunk_index` is a chunk's position inside its document, so a document is
 * reassembled in reading order without relying on UUID ordering (rows
 * created within the same millisecond have no guaranteed `id` order). The
 * `(workspace_id, source)` index backs `KnowledgeBase::readDocument()` and
 * replace-on-reingest, which both look chunks up by source.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_embeddings', function (Blueprint $table) {
            $table->unsignedInteger('chunk_index')->default(0)->after('source');

            $table->index(['workspace_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('document_embeddings', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'source']);
            $table->dropColumn('chunk_index');
        });
    }
};
