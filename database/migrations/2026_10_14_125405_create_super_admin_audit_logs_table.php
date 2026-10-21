<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only record of super admin sign-ins and the changes made from the
 * super admin panel. Separate from `admin_audit_logs` (platform-admin API).
 * `subject_type` is a plain label rather than a morph type, so recording a
 * change never depends on the enforced morph map knowing the model.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('super_admin_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('super_admin_id')->nullable()->constrained('super_admins')->nullOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('super_admin_audit_logs');
    }
};
