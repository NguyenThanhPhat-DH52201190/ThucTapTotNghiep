<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('ocs', function (Blueprint $table) {
            if (!Schema::hasColumn('ocs', 'order_type')) $table->string('order_type', 10)->default('cmt')->after('CMT');
            if (!Schema::hasColumn('ocs', 'material_ownership')) $table->string('material_ownership', 15)->default('factory')->after('order_type');
            if (!Schema::hasColumn('ocs', 'unit_price')) $table->decimal('unit_price', 14, 2)->default(0)->after('material_ownership');
        });
    }
    public function down(): void {
        Schema::table('ocs', function (Blueprint $table) { $table->dropColumn(['order_type', 'material_ownership', 'unit_price']); });
    }
};
