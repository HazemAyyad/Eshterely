<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_line_items', 'fulfillment_status')) {
                $table->string('fulfillment_status', 40)->default('paid')->after('quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('order_line_items', function (Blueprint $table) {
            if (Schema::hasColumn('order_line_items', 'fulfillment_status')) {
                $table->dropColumn('fulfillment_status');
            }
        });
    }
};
