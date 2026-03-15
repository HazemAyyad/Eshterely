<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payment-safe order creation from draft: store snapshot totals so payment uses only snapshots.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('draft_order_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->decimal('order_total_snapshot', 14, 2)->nullable()->after('total_amount');
            $table->decimal('shipping_total_snapshot', 14, 2)->nullable()->after('order_total_snapshot');
            $table->decimal('service_fee_snapshot', 14, 2)->nullable()->after('shipping_total_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['draft_order_id']);
            $table->dropColumn(['order_total_snapshot', 'shipping_total_snapshot', 'service_fee_snapshot']);
        });
    }
};
