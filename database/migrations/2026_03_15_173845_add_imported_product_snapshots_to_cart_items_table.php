<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('imported_product_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->json('pricing_snapshot')->nullable()->after('shipping_cost');
            $table->json('shipping_snapshot')->nullable()->after('pricing_snapshot');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('imported_product_id');
            $table->dropColumn(['pricing_snapshot', 'shipping_snapshot']);
        });
    }
};
