<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lightweight audit trail for admin order operations.
     */
    public function up(): void
    {
        Schema::create('order_operation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('action', 80);
            $table->json('payload')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::table('order_operation_logs', function (Blueprint $table) {
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_operation_logs');
    }
};
