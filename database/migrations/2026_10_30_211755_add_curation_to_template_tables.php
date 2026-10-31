<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['workflow_templates', 'agent_templates', 'page_templates'];

    /**
     * Featured templates come first in the gallery, then by `sort_order`, then newest.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('is_featured')->default(false)->after('visibility');
                $blueprint->unsignedInteger('sort_order')->default(0)->after('is_featured');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['is_featured', 'sort_order']);
            });
        }
    }
};
