<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_key', 50);
            $table->string('source_label')->nullable();
            $table->string('title');
            $table->decimal('price', 12, 2);
            $table->string('currency', 10)->default('USD');
            $table->decimal('price_drop', 12, 2)->nullable();
            $table->boolean('tracking_on')->default(true);
            $table->string('stock_status', 20)->default('in_stock'); // in_stock, low_stock, out_of_stock
            $table->string('stock_label')->nullable();
            $table->string('image_url')->nullable();
            $table->string('product_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
    }
};
