<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the model picker tells people about a model beyond its name: a short
 * description, what it's good at, how fast and how costly it is relative to
 * the rest of the catalog, and — once it's being retired — when, and which
 * entry replaces it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('model_catalog', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('brand');
            $table->json('strengths')->nullable()->after('description');
            $table->string('speed')->nullable()->after('strengths');
            $table->unsignedTinyInteger('cost_tier')->nullable()->after('speed');
            $table->timestamp('retires_at')->nullable()->after('sort_order');
            $table->string('replacement_slug')->nullable()->after('retires_at');
        });
    }

    public function down(): void
    {
        Schema::table('model_catalog', function (Blueprint $table) {
            $table->dropColumn(['description', 'strengths', 'speed', 'cost_tier', 'retires_at', 'replacement_slug']);
        });
    }
};
