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
        Schema::table('node_categories', function (Blueprint $table) {
            $table->json('functions')->nullable()->after('kind');
            $table->string('vendor')->nullable()->after('functions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('node_categories', function (Blueprint $table) {
            $table->dropColumn(['functions', 'vendor']);
        });
    }
};
