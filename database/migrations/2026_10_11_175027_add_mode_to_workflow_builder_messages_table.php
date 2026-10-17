<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a user message asked the builder for: see
 * `App\Enums\Workflows\BuilderMessageMode`. Ask mode used to be an
 * instruction the client wrote into the message itself; those messages are
 * moved onto the column, with the instruction taken back out of their text.
 */
return new class extends Migration
{
    private const string LEGACY_ASK_PREFIX = '[Ask mode: answer the question below about this workflow. Do not add, remove, or modify any nodes or connections, just explain.]';

    public function up(): void
    {
        Schema::table('workflow_builder_messages', function (Blueprint $table) {
            $table->string('mode', 16)->default('build')->after('role');
        });

        DB::table('workflow_builder_messages')
            ->where('role', 'user')
            ->where('content', 'like', self::LEGACY_ASK_PREFIX.'%')
            ->orderBy('id')
            ->each(function (object $message): void {
                DB::table('workflow_builder_messages')->where('id', $message->id)->update([
                    'mode' => 'ask',
                    'content' => ltrim(substr($message->content, strlen(self::LEGACY_ASK_PREFIX))),
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('workflow_builder_messages', function (Blueprint $table) {
            $table->dropColumn('mode');
        });
    }
};
