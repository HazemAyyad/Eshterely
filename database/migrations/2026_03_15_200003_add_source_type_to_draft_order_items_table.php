<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Origin tracking: source_type (e.g. imported, paste_link) for admin review and auditing.
     */
    public function up(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->string('source_type', 50)->nullable()->after('imported_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('draft_order_items', function (Blueprint $table) {
            $table->dropColumn('source_type');
        });
    }
};
