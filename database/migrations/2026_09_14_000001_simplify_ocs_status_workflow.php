<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the meaning of existing orders while moving to the four-step flow.
        DB::table('ocs')->where('status', 'released')->update(['status' => 'confirmed']);
        DB::table('ocs')->where('status', 'closed')->update(['status' => 'completed']);
    }

    public function down(): void
    {
        // The former intermediate states cannot be reconstructed unambiguously.
    }
};
