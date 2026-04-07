<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destination_address_id')->constrained('addresses')->cascadeOnDelete();
            $table->string('status', 30)->default('draft'); // draft, awaiting_payment, paid, packed, shipped, delivered
            $table->string('carrier', 80)->nullable();
            $table->string('tracking_number')->nullable();
            $table->decimal('final_weight', 12, 4)->nullable();
            $table->decimal('final_length', 12, 4)->nullable();
            $table->decimal('final_width', 12, 4)->nullable();
            $table->decimal('final_height', 12, 4)->nullable();
            $table->string('final_box_image')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->decimal('shipping_cost', 12, 2)->default(0);
            $table->decimal('additional_fees_total', 12, 2)->default(0);
            $table->decimal('total_shipping_payment', 12, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->json('pricing_breakdown')->nullable();
            $table->timestamps();
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_line_item_id')->constrained('order_line_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('order_line_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
