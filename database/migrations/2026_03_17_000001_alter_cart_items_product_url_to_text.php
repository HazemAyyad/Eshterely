<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL default string length (255) is too small for many product URLs (e.g. eBay tracking params).
        // Use TEXT to prevent truncation errors.
        DB::statement("ALTER TABLE `cart_items` MODIFY `product_url` TEXT NOT NULL");
    }

    public function down(): void
    {
        // Revert to VARCHAR(255) (original behavior).
        DB::statement("ALTER TABLE `cart_items` MODIFY `product_url` VARCHAR(255) NOT NULL");
    }
};

