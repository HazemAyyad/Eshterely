<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_assistant_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('source_url');
            $table->string('source_domain', 255)->nullable();
            $table->string('title')->nullable();
            $table->text('details')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->text('variant_details')->nullable();
            $table->decimal('customer_estimated_price', 12, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->json('image_paths')->nullable();
            $table->decimal('admin_product_price', 12, 2)->nullable();
            $table->decimal('admin_service_fee', 12, 2)->nullable();
            $table->text('admin_notes')->nullable();
            $table->string('status', 40)->index();
            $table->string('origin', 40)->default('purchase_assistant');
            /** Linked after order is created (no FK: avoids circular dependency with orders.purchase_assistant_request_id). */
            $table->unsignedBigInteger('converted_order_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('purchase_assistant_request_id')
                ->nullable()
                ->after('draft_order_id')
                ->constrained('purchase_assistant_requests')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_assistant_request_id');
        });
        Schema::dropIfExists('purchase_assistant_requests');
    }

};
