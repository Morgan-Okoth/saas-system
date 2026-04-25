<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subscriptions Table Migration
 * 
 * Tracks subscription lifecycle for each school.
 * Links to School model via school_id foreign key.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained()->cascadeOnDelete();
            $table->string('plan_type')->default('trial'); // trial, basic, premium, enterprise
            $table->string('status')->default('trial'); // trial, active, expired, cancelled, past_due
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_customer_id')->nullable();
            $table->string('mpesa_checkout_id')->nullable();
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('currency')->default('KES');
            $table->string('payment_method')->nullable(); // stripe, mpesa, manual
            $table->boolean('auto_renew')->default(true);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['school_id', 'status']);
            $table->index(['school_id', 'plan_type']);
            $table->index(['school_id', 'ends_at']);
            $table->unique(['school_id', 'stripe_subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
