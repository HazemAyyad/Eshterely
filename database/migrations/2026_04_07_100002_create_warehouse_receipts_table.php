<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouse_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_line_item_id')->constrained('order_line_items')->cascadeOnDelete();
            $table->timestamp('received_at');
            $table->decimal('received_weight', 12, 4)->nullable();
            $table->decimal('received_length', 12, 4)->nullable();
            $table->decimal('received_width', 12, 4)->nullable();
            $table->decimal('received_height', 12, 4)->nullable();
            $table->json('images')->nullable();
            $table->text('condition_notes')->nullable();
            $table->string('special_handling_type', 50)->nullable();
            $table->decimal('additional_fee_amount', 12, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_receipts');
    }
};
