<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks a failing subscription's dunning cycle so the product can explain
 * itself. Entitlement is already decided by `stripe_status` — this adds no
 * grace period and grants no access; it records *why* access stopped, since
 * "your workflows stopped running" with no in-product explanation is the
 * worst version of a failed payment.
 *
 * `dunning_invoice_id` is what the billing screen links to so a customer can
 * pay the outstanding invoice directly rather than hunting for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('dunning_started_at')->nullable()->after('ends_at');
            $table->string('dunning_invoice_id')->nullable()->after('dunning_started_at');
            $table->unsignedSmallInteger('dunning_attempts')->default(0)->after('dunning_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['dunning_started_at', 'dunning_invoice_id', 'dunning_attempts']);
        });
    }
};
