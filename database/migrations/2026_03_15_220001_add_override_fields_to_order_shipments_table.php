<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin shipping override: record override without silently mutating original snapshot.
     */
    public function up(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->decimal('shipping_override_amount', 14, 2)->nullable()->after('shipping_snapshot');
            $table->string('shipping_override_carrier', 50)->nullable()->after('shipping_override_amount');
            $table->text('shipping_override_notes')->nullable()->after('shipping_override_carrier');
            $table->timestamp('shipping_override_at')->nullable()->after('shipping_override_notes');
        });
    }

    public function down(): void
    {
        Schema::table('order_shipments', function (Blueprint $table) {
            $table->dropColumn([
                'shipping_override_amount',
                'shipping_override_carrier',
                'shipping_override_notes',
                'shipping_override_at',
            ]);
        });
    }
};
