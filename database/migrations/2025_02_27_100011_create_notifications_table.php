<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->default('all'); // all, orders, shipments, promo
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->boolean('read')->default(false);
            $table->boolean('important')->default(false);
            $table->string('action_label')->nullable();
            $table->string('action_route')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
