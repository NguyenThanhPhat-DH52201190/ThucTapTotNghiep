<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bom_headers', 'customer_id')) {
            Schema::table('bom_headers', function (Blueprint $table): void {
                $table->foreignId('customer_id')->nullable()->after('style_id')
                    ->constrained('customer_info')->nullOnDelete();
            });
        }

        DB::statement("UPDATE bom_headers b
            INNER JOIN customer_info c ON LOWER(TRIM(c.name)) = LOWER(TRIM(b.customer))
            SET b.customer_id = c.id
            WHERE b.customer_id IS NULL AND b.customer IS NOT NULL AND TRIM(b.customer) <> ''");
    }

    public function down(): void
    {
        if (Schema::hasColumn('bom_headers', 'customer_id')) {
            Schema::table('bom_headers', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_id');
            });
        }
    }
};
