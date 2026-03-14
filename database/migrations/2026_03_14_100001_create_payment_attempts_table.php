<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->unsignedTinyInteger('attempt_no');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->string('status', 30);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_attempts', function (Blueprint $table) {
            $table->index(['payment_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
