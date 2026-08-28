<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('po_receipt_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('po_receipt_items', 'material_color')) {
                $table->string('material_color')->nullable()->after('material_code');
            }
            if (! Schema::hasColumn('po_receipt_items', 'material_size')) {
                $table->string('material_size')->nullable()->after('material_color');
            }
        });
    }

    public function down(): void
    {
        Schema::table('po_receipt_items', function (Blueprint $table): void {
            $columns = array_filter(['material_color', 'material_size'], fn (string $column) => Schema::hasColumn('po_receipt_items', $column));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
