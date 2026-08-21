<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The workflow's declared input contract — the fields a person filling in
     * a form (or a caller reading the API) is expected to supply. Optional by
     * design: `WorkflowInterface` derives an equivalent contract from the
     * graph's own `{{ input.* }}` references when none is declared, so every
     * existing workflow has a usable interface without anyone authoring one.
     */
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->json('input_schema')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn('input_schema');
        });
    }
};
