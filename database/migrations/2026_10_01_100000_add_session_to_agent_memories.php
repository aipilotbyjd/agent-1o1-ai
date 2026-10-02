<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which conversation a memory was saved in, so a person reviewing what an
 * agent remembers can see where each fact came from. Kept when the
 * conversation is deleted — the fact is still true, only its source is gone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->foreignUuid('agent_session_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_memories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agent_session_id');
        });
    }
};
