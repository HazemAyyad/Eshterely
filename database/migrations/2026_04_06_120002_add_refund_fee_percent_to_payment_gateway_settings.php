<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return;
        }
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('payment_gateway_settings', 'refund_fee_percent')) {
                $table->decimal('refund_fee_percent', 8, 4)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return;
        }
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_settings', 'refund_fee_percent')) {
                $table->dropColumn('refund_fee_percent');
            }
        });
    }
};
