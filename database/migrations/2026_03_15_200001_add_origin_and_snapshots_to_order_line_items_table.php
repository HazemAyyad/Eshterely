<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft order origin tracking and snapshot integrity for order line items.
     * Supports cart restoration: draft_order_item_id links back to draft.
     */
    public function up(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->foreignId('draft_order_item_id')->nullable()->after('order_shipment_id')->constrained()->nullOnDelete();
            $table->string('source_type', 50)->nullable()->after('draft_order_item_id');
            $table->foreignId('cart_item_id')->nullable()->after('source_type')->constrained()->nullOnDelete();
            $table->foreignId('imported_product_id')->nullable()->after('cart_item_id')->constrained()->nullOnDelete();
            $table->json('product_snapshot')->nullable()->after('image_url');
            $table->json('pricing_snapshot')->nullable()->after('product_snapshot');
            $table->json('review_metadata')->nullable()->after('pricing_snapshot');
            $table->boolean('estimated')->default(false)->after('review_metadata');
            $table->json('missing_fields')->nullable()->after('estimated');
        });
    }

    public function down(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            $table->dropForeign(['draft_order_item_id']);
            $table->dropForeign(['cart_item_id']);
            $table->dropForeign(['imported_product_id']);
            $table->dropColumn([
                'source_type', 'product_snapshot', 'pricing_snapshot',
                'review_metadata', 'estimated', 'missing_fields',
            ]);
        });
    }
};
