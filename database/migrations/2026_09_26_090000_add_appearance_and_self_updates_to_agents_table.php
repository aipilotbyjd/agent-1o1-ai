<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('icon', 50)->nullable()->after('description');
            $table->string('color', 20)->nullable()->after('icon');
            $table->boolean('allow_self_updates')->default(false)->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn(['icon', 'color', 'allow_self_updates']);
        });
    }
};
