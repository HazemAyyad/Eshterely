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
            if (! Schema::hasColumn('payment_gateway_settings', 'zelle_receiver_name')) {
                $table->string('zelle_receiver_name')->nullable()->after('stripe_webhook_secret');
            }
            if (! Schema::hasColumn('payment_gateway_settings', 'zelle_receiver_email')) {
                $table->string('zelle_receiver_email')->nullable();
            }
            if (! Schema::hasColumn('payment_gateway_settings', 'zelle_receiver_phone')) {
                $table->string('zelle_receiver_phone')->nullable();
            }
            if (! Schema::hasColumn('payment_gateway_settings', 'zelle_receiver_qr_image')) {
                $table->string('zelle_receiver_qr_image')->nullable();
            }
            if (! Schema::hasColumn('payment_gateway_settings', 'wire_transfer_instructions')) {
                $table->text('wire_transfer_instructions')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return;
        }

        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            foreach ([
                'zelle_receiver_name',
                'zelle_receiver_email',
                'zelle_receiver_phone',
                'zelle_receiver_qr_image',
                'wire_transfer_instructions',
            ] as $col) {
                if (Schema::hasColumn('payment_gateway_settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
