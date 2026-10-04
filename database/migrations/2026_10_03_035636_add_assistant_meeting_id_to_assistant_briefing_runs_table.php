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
        Schema::table('assistant_briefing_runs', function (Blueprint $table) {
            $table->foreignUuid('assistant_meeting_id')->nullable()->after('assistant_briefing_config_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assistant_briefing_runs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assistant_meeting_id');
        });
    }
};
