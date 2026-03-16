<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Operational review classification and admin notes (extensible for needs_admin_review, needs_reprice, needs_shipping_completion).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->json('review_state')->nullable()->after('needs_review');
            $table->text('admin_notes')->nullable()->after('review_state');
            $table->timestamp('reviewed_at')->nullable()->after('admin_notes');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['review_state', 'admin_notes', 'reviewed_at']);
        });
    }
};
