<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_top_up_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50)->default('square');
            $table->string('currency', 10)->default('USD');
            $table->decimal('amount', 12, 2);
            $table->string('status', 30)->default('pending'); // pending|processing|paid|failed|cancelled
            $table->string('provider_payment_id')->nullable();
            $table->string('provider_order_id')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->string('reference')->unique();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('wallet_top_up_payments', function (Blueprint $table) {
            $table->index('idempotency_key');
            $table->index('status');
            $table->index(['wallet_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_top_up_payments');
    }
};

