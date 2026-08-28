<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up(): void {
Schema::table('sewing_lines', function(Blueprint $t){ if(!Schema::hasColumn('sewing_lines','working_hours_per_day')) $t->decimal('working_hours_per_day',5,2)->default(8); if(!Schema::hasColumn('sewing_lines','labor_rate')) $t->decimal('labor_rate',12,2)->default(0); });
Schema::table('work_orders', function(Blueprint $t){ if(!Schema::hasColumn('work_orders','planned_qty')) $t->unsignedInteger('planned_qty')->default(0); if(!Schema::hasColumn('work_orders','smv')) $t->decimal('smv',8,2)->default(0); });
}
    public function down(): void {}
};
