<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('wallet_refund_requests');
    }

    public function down(): void
    {
        // Previous table recreated only if needed for rollback; prefer restoring from backup in production.
    }
};
