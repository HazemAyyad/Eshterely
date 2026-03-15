<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Draft orders created from cart. Snapshot-based; no recalculation.
     */
    public function up(): void
    {
        Schema::create('draft_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 30)->default('draft');
            $table->string('currency', 10)->default('USD');
            $table->decimal('subtotal_snapshot', 14, 2)->default(0);
            $table->decimal('shipping_total_snapshot', 14, 2)->default(0);
            $table->decimal('service_fee_total_snapshot', 14, 2)->default(0);
            $table->decimal('final_total_snapshot', 14, 2)->default(0);
            $table->boolean('estimated')->default(false);
            $table->boolean('needs_review')->default(false);
            /** @see Part 3: future needs_admin_review, needs_reprice, needs_shipping_completion */
            $table->json('review_state')->nullable();
            $table->json('notes')->nullable();
            $table->json('warnings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('draft_orders');
    }
};
