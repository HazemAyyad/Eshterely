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
            if (! Schema::hasColumn('payment_gateway_settings', 'zelle_instruction_text')) {
                $table->text('zelle_instruction_text')->nullable()->after('zelle_receiver_qr_image');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payment_gateway_settings')) {
            return;
        }

        Schema::table('payment_gateway_settings', function (Blueprint $table) {
            if (Schema::hasColumn('payment_gateway_settings', 'zelle_instruction_text')) {
                $table->dropColumn('zelle_instruction_text');
            }
        });
    }
};
