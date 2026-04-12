<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wallet_transactions MODIFY type VARCHAR(50) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_transactions ALTER COLUMN type TYPE VARCHAR(50)');
        } else {
            // sqlite / others: widen via table rebuild when doctrine/dbal not present
            Schema::table('wallet_transactions', function ($table) {
                $table->string('type', 50)->change();
            });
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE wallet_transactions MODIFY type VARCHAR(20) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE wallet_transactions ALTER COLUMN type TYPE VARCHAR(20)');
        } else {
            Schema::table('wallet_transactions', function ($table) {
                $table->string('type', 20)->change();
            });
        }
    }
};
