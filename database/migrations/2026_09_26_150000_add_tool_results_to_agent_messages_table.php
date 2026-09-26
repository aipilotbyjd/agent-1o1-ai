<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->json('tool_results')->nullable()->after('tool_calls');
        });
    }

    public function down(): void
    {
        Schema::table('agent_messages', function (Blueprint $table) {
            $table->dropColumn('tool_results');
        });
    }
};
