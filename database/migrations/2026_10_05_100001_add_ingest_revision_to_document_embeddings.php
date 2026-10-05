<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Queued ingests of the same source can finish out of order. The revision is
 * taken when the upload is accepted (microseconds), and a re-ingest that
 * carries an older revision than what is stored is dropped instead of
 * replacing newer content.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_embeddings', function (Blueprint $table) {
            $table->unsignedBigInteger('ingest_revision')->nullable()->after('chunk_index');
        });
    }

    public function down(): void
    {
        Schema::table('document_embeddings', function (Blueprint $table) {
            $table->dropColumn('ingest_revision');
        });
    }
};
