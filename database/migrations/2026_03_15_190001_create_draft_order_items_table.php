<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft order line items; snapshots copied from cart items (no recalculation).
     */
    public function up(): void
    {
        Schema::create('draft_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cart_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('imported_product_id')->nullable()->constrained()->nullOnDelete();
            $table->json('product_snapshot')->nullable();
            $table->json('shipping_snapshot')->nullable();
            $table->json('pricing_snapshot')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('review_metadata')->nullable();
            $table->boolean('estimated')->default(false);
            $table->json('missing_fields')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_order_items');
    }
};
