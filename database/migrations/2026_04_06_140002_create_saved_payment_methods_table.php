<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_customer_id');
            $table->string('stripe_payment_method_id');
            $table->string('brand', 32)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('verification_status', 40)->default('pending_verification');
            $table->decimal('verification_charge_amount', 12, 2)->nullable();
            $table->unsignedTinyInteger('verification_attempts')->default(0);
            $table->string('stripe_verification_payment_intent_id')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'stripe_payment_method_id']);
            $table->index(['user_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_payment_methods');
    }
};
