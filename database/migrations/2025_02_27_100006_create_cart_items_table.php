<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_url');
            $table->string('name');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('currency', 10)->default('USD');
            $table->string('image_url')->nullable();
            $table->string('store_key', 50)->nullable();
            $table->string('store_name')->nullable();
            $table->string('product_id', 100)->nullable();
            $table->string('country', 50)->nullable();
            $table->decimal('weight', 10, 4)->nullable();
            $table->string('weight_unit', 10)->nullable();
            $table->decimal('length', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->string('dimension_unit', 10)->nullable();
            $table->string('source', 20)->default('paste_link'); // webview, paste_link
            $table->string('review_status', 20)->default('pending_review'); // pending_review, reviewed, rejected
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
