<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shipment tracking and lifecycle: carrier, tracking number, status, dates, source_type, notes.
     */
    public function up(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->string('carrier', 50)->nullable()->after('shipping_method');
            $table->string('tracking_number')->nullable()->after('carrier');
            $table->string('shipment_status', 50)->nullable()->after('tracking_number');
            $table->timestamp('estimated_delivery_at')->nullable()->after('eta');
            $table->timestamp('shipped_at')->nullable()->after('estimated_delivery_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->string('source_type', 50)->nullable()->after('delivered_at');
            $table->text('notes')->nullable()->after('shipping_override_at');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'carrier', 'tracking_number', 'shipment_status',
                'estimated_delivery_at', 'shipped_at', 'delivered_at',
                'source_type', 'notes',
            ]);
        });
    }
};
