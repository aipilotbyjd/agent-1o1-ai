<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A channel belongs to the workspace and other members' notification
 * preferences point at it, so deleting the member who happened to create it
 * must not delete it too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('notification_channels', function (Blueprint $table) {
            $table->uuid('created_by')->nullable()->change();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notification_channels', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
        });

        Schema::table('notification_channels', function (Blueprint $table) {
            $table->uuid('created_by')->nullable(false)->change();
            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
