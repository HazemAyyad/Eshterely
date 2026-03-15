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
        Schema::create('imported_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_url');
            $table->string('store_key', 50)->nullable();
            $table->string('store_name')->nullable();
            $table->string('country', 50)->nullable();
            $table->string('title');
            $table->string('image_url')->nullable();
            $table->decimal('product_price', 12, 2);
            $table->string('product_currency', 10)->default('USD');
            $table->json('package_info')->nullable(); // weight, dimensions, units, quantity
            $table->json('shipping_quote_snapshot')->nullable();
            $table->json('final_pricing_snapshot')->nullable();
            $table->string('carrier', 50)->nullable();
            $table->string('pricing_mode', 50)->nullable();
            $table->boolean('estimated')->default(false);
            $table->json('missing_fields')->nullable(); // list of missing field names
            $table->json('import_metadata')->nullable(); // extraction_source, etc.
            $table->string('status', 30)->default('draft'); // draft, added_to_cart, ordered, archived
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('imported_products');
    }
};
