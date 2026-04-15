<?php

use App\Support\PurchaseAssistantStoreDisplayName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_assistant_requests', function (Blueprint $table) {
            $table->string('store_display_name', 120)->nullable()->after('source_domain');
        });

        if (Schema::hasTable('purchase_assistant_requests')) {
            foreach (DB::table('purchase_assistant_requests')->select('id', 'source_domain')->cursor() as $row) {
                $host = $row->source_domain ?? null;
                $name = PurchaseAssistantStoreDisplayName::fromHost(is_string($host) ? $host : null);
                DB::table('purchase_assistant_requests')->where('id', $row->id)->update([
                    'store_display_name' => $name,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('purchase_assistant_requests', function (Blueprint $table) {
            $table->dropColumn('store_display_name');
        });
    }
};
