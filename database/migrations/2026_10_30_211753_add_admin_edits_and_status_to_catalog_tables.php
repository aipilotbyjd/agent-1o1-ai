<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `admin_edited_fields` lists what an admin changed by hand, so a re-seed
     * leaves those alone. `status` is a note for the editor: an app that is
     * degraded or under maintenance says so where people pick it.
     */
    public function up(): void
    {
        Schema::table('node_categories', function (Blueprint $table) {
            $table->json('admin_edited_fields')->nullable()->after('vendor');
            $table->string('status')->default('operational')->after('admin_edited_fields');
            $table->string('status_note', 300)->nullable()->after('status');
        });

        Schema::table('connectors', function (Blueprint $table) {
            $table->json('admin_edited_fields')->nullable()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('connectors', function (Blueprint $table) {
            $table->dropColumn('admin_edited_fields');
        });

        Schema::table('node_categories', function (Blueprint $table) {
            $table->dropColumn(['admin_edited_fields', 'status', 'status_note']);
        });
    }
};
