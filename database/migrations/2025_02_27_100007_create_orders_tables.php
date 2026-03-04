<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->string('origin', 30)->default('multi_origin'); // multi_origin, turkey, usa
            $table->string('status', 30)->default('in_transit'); // in_transit, delivered, cancelled
            $table->timestamp('placed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('refund_status')->nullable();
            $table->string('estimated_delivery')->nullable();
            $table->foreignId('shipping_address_id')->nullable()->constrained('addresses')->nullOnDelete();
            $table->decimal('consolidation_savings', 12, 2)->nullable();
            $table->string('payment_method_label')->nullable();
            $table->string('payment_method_last_four', 4)->nullable();
            $table->date('invoice_issue_date')->nullable();
            $table->string('transaction_id')->nullable();
            $table->text('shipping_address_text')->nullable();
            $table->timestamps();
        });

        Schema::create('order_shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('country_code', 10);
            $table->string('country_label');
            $table->string('shipping_method')->nullable();
            $table->string('eta')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('shipping_fee', 12, 2)->nullable();
            $table->decimal('customs_duties', 12, 2)->nullable();
            $table->decimal('gross_weight_kg', 10, 4)->nullable();
            $table->string('dimensions')->nullable();
            $table->boolean('insurance_confirmed')->default(false);
            $table->json('status_tags')->nullable();
            $table->timestamps();
        });

        Schema::create('order_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_shipment_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('store_name')->nullable();
            $table->string('sku', 50)->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('image_url')->nullable();
            $table->json('badges')->nullable();
            $table->decimal('weight_kg', 10, 4)->nullable();
            $table->string('dimensions')->nullable();
            $table->string('shipping_method')->nullable();
            $table->timestamps();
        });

        Schema::create('order_tracking_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_shipment_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('order_price_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('amount');
            $table->boolean('is_discount')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_price_lines');
        Schema::dropIfExists('order_tracking_events');
        Schema::dropIfExists('order_line_items');
        Schema::dropIfExists('order_shipments');
        Schema::dropIfExists('orders');
    }
};
