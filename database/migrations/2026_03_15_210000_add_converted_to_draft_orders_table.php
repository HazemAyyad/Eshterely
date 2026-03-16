<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Post-conversion state: when draft is converted to order, mark status and store order reference.
     */
    public function up(): void
    {
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->foreignId('converted_order_id')->nullable()->after('warnings')->constrained('orders')->nullOnDelete();
            $table->timestamp('converted_at')->nullable()->after('converted_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('draft_orders', function (Blueprint $table) {
            $table->dropForeign(['converted_order_id']);
            $table->dropColumn('converted_at');
        });
    }
};
