<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reflection_runs', function (Blueprint $table) {
            $table->json('usage')->nullable()->after('skip_reason');
        });
    }

    public function down(): void
    {
        Schema::table('reflection_runs', function (Blueprint $table) {
            $table->dropColumn('usage');
        });
    }
};
