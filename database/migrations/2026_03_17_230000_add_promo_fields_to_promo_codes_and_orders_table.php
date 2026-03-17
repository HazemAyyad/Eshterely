<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('promo_codes', 'description')) {
                $table->text('description')->nullable()->after('code');
            }
            if (! Schema::hasColumn('promo_codes', 'min_order_amount')) {
                $table->decimal('min_order_amount', 12, 2)->nullable()->after('discount_value');
            }
            if (! Schema::hasColumn('promo_codes', 'max_discount_amount')) {
                $table->decimal('max_discount_amount', 12, 2)->nullable()->after('min_order_amount');
            }
            if (! Schema::hasColumn('promo_codes', 'max_usage_total')) {
                $table->unsignedInteger('max_usage_total')->nullable()->after('max_discount_amount');
            }
            if (! Schema::hasColumn('promo_codes', 'max_usage_per_user')) {
                $table->unsignedInteger('max_usage_per_user')->nullable()->after('max_usage_total');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'promo_code_id')) {
                $table->foreignId('promo_code_id')->nullable()->after('shipping_total_snapshot')->constrained('promo_codes')->nullOnDelete();
            }
            if (! Schema::hasColumn('orders', 'promo_code')) {
                $table->string('promo_code', 50)->nullable()->after('promo_code_id');
            }
            if (! Schema::hasColumn('orders', 'promo_discount_amount')) {
                $table->decimal('promo_discount_amount', 12, 2)->default(0)->after('promo_code');
            }
            if (! Schema::hasColumn('orders', 'wallet_applied_amount')) {
                $table->decimal('wallet_applied_amount', 12, 2)->default(0)->after('promo_discount_amount');
            }
            if (! Schema::hasColumn('orders', 'amount_due_now')) {
                $table->decimal('amount_due_now', 12, 2)->default(0)->after('wallet_applied_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'amount_due_now')) {
                $table->dropColumn('amount_due_now');
            }
            if (Schema::hasColumn('orders', 'wallet_applied_amount')) {
                $table->dropColumn('wallet_applied_amount');
            }
            if (Schema::hasColumn('orders', 'promo_discount_amount')) {
                $table->dropColumn('promo_discount_amount');
            }
            if (Schema::hasColumn('orders', 'promo_code')) {
                $table->dropColumn('promo_code');
            }
            if (Schema::hasColumn('orders', 'promo_code_id')) {
                $table->dropConstrainedForeignId('promo_code_id');
            }
        });

        Schema::table('promo_codes', function (Blueprint $table) {
            if (Schema::hasColumn('promo_codes', 'max_usage_per_user')) {
                $table->dropColumn('max_usage_per_user');
            }
            if (Schema::hasColumn('promo_codes', 'max_usage_total')) {
                $table->dropColumn('max_usage_total');
            }
            if (Schema::hasColumn('promo_codes', 'max_discount_amount')) {
                $table->dropColumn('max_discount_amount');
            }
            if (Schema::hasColumn('promo_codes', 'min_order_amount')) {
                $table->dropColumn('min_order_amount');
            }
            if (Schema::hasColumn('promo_codes', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
