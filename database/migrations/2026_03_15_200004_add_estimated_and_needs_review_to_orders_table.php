<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Expose estimated and needs_review on orders created from draft (for OrderResource).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('estimated')->default(false)->after('service_fee_snapshot');
            $table->boolean('needs_review')->default(false)->after('estimated');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['estimated', 'needs_review']);
        });
    }
};
