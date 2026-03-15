<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Copy shipping snapshot from draft to order shipment for audit and integrity.
     */
    public function up(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->json('shipping_snapshot')->nullable()->after('status_tags');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->dropColumn('shipping_snapshot');
        });
    }
};
