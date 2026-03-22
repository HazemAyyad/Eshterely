<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('featured_stores') || ! Schema::hasColumn('featured_stores', 'country_code')) {
            return;
        }
        foreach (DB::table('featured_stores')->select('id', 'country_code')->get() as $row) {
            $code = $row->country_code ?? null;
            if ($code === null || $code === '') {
                continue;
            }
            $upper = strtoupper((string) $code);
            if ($upper !== (string) $code) {
                DB::table('featured_stores')->where('id', $row->id)->update(['country_code' => $upper]);
            }
        }
    }

    public function down(): void
    {
        // Irreversible normalization.
    }
};
