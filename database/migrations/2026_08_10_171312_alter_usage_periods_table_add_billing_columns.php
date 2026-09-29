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
        Schema::table('usage_periods', function (Blueprint $table) {
            $table->foreignUuid('plan_id')->nullable()->after('workspace_id')->constrained()->nullOnDelete();
            $table->foreignUuid('subscription_id')->nullable()->after('plan_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usage_periods', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->dropConstrainedForeignId('plan_id');
        });
    }
};
