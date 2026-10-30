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
        Schema::table('plans', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_active');
            $table->boolean('is_public')->default(true)->after('archived_at');
            $table->string('badge', 40)->nullable()->after('is_public');
            $table->uuid('updated_by')->nullable()->after('badge');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['archived_at', 'is_public', 'badge', 'updated_by']);
        });
    }
};
