<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds review metadata for imported product cart items: estimated, missing_fields,
     * carrier, pricing_mode, needs_review. Used for operational cart review.
     */
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->boolean('estimated')->default(false)->after('shipping_snapshot');
            $table->json('missing_fields')->nullable()->after('estimated');
            $table->string('carrier', 50)->nullable()->after('missing_fields');
            $table->string('pricing_mode', 50)->nullable()->after('carrier');
            $table->boolean('needs_review')->default(false)->after('pricing_mode');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropColumn(['estimated', 'missing_fields', 'carrier', 'pricing_mode', 'needs_review']);
        });
    }
};
