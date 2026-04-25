<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->string('email')->unique()->index();
            $table->string('phone')->nullable();
            $table->string('county')->nullable()->index();
            $table->string('subscription_status')->default('trial'); // trial, active, expired, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->json('settings')->nullable(); // school-specific config
            $table->timestamps();
            $table->softDeletes();

            // Performance optimization for common lookups
            $table->index(['subscription_status', 'trial_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
