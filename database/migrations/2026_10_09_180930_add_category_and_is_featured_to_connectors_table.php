<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves catalog grouping and the "Recommended" picks out of the frontend:
 * `category` is a `ConnectorCategory`, `is_featured` marks the connectors
 * suggested to a workspace that hasn't connected them yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->string('category')->default('other')->after('color');
            $table->boolean('is_featured')->default(false)->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->dropColumn(['category', 'is_featured']);
        });
    }
};
