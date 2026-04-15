<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_payment_methods', function (Blueprint $table) {
            $table->timestamp('blocked_at')->nullable()->after('verified_at');
            $table->string('blocked_reason', 500)->nullable()->after('blocked_at');
        });

        // Failed attempts are only counted on mismatches; verified cards do not need a stored attempt count.
        DB::table('saved_payment_methods')
            ->where('verification_status', 'verified')
            ->update(['verification_attempts' => 0]);

        // Normalize legacy "too many attempts" failures to blocked + timestamp where missing.
        $legacyBlocked = DB::table('saved_payment_methods')
            ->where('verification_status', 'failed_verification')
            ->where('verification_attempts', '>=', 3)
            ->get(['id', 'updated_at', 'created_at']);

        foreach ($legacyBlocked as $row) {
            $ts = $row->updated_at ?? $row->created_at ?? now();
            DB::table('saved_payment_methods')->where('id', $row->id)->update([
                'verification_status' => 'blocked',
                'blocked_at' => $ts,
                'blocked_reason' => 'Too many failed verification attempts.',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('saved_payment_methods', function (Blueprint $table) {
            $table->dropColumn(['blocked_at', 'blocked_reason']);
        });
    }
};
