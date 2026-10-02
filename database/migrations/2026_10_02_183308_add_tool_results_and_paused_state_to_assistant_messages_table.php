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
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->json('tool_results')->nullable()->after('tool_call_id');
            $table->json('paused_state')->nullable()->after('usage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_messages', function (Blueprint $table) {
            $table->dropColumn(['tool_results', 'paused_state']);
        });
    }
};
