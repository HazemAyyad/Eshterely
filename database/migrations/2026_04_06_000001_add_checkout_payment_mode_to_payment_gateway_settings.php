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
            if (! Schema::hasColumn('payment_gateway_settings', 'checkout_payment_mode')) {
                $table->string('checkout_payment_mode', 32)->default('gateway_only');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return;
        }
        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_settings', 'checkout_payment_mode')) {
                $table->dropColumn('checkout_payment_mode');
            }
        });
    }
};
