<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Legacy tables were created as MyISAM on some installations. Stock
        // reservations require InnoDB row locks and foreign keys.
        DB::statement('ALTER TABLE warehouses ENGINE=InnoDB');
        if (!Schema::hasTable('bom_colorways')) {
            Schema::create('bom_colorways', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->id();
                $table->foreignId('bom_item_id')->constrained('bom_items')->cascadeOnDelete();
                $table->string('garment_color', 100);
                $table->string('material_color', 100);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['bom_item_id', 'garment_color'], 'bom_colorway_garment_unique');
                $table->index(['garment_color', 'material_color']);
            });
        } else {
            // A previous failed migration can leave this first DDL statement behind on MySQL.
            DB::statement('ALTER TABLE bom_colorways ENGINE=InnoDB');
        }

        if (!Schema::hasTable('locations')) Schema::create('locations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses')->cascadeOnDelete();
            $table->string('location_code', 100);
            $table->string('location_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['warehouse_id', 'location_code']);
        }); else DB::statement('ALTER TABLE locations ENGINE=InnoDB');

        if (!Schema::hasTable('inventory_reservations')) Schema::create('inventory_reservations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('requisition_item_id')->constrained('requisition_items')->cascadeOnDelete();
            $table->foreignId('inventory_balance_id')->constrained('inventory_balances')->restrictOnDelete();
            $table->decimal('reserved_qty', 14, 4)->default(0);
            $table->decimal('consumed_qty', 14, 4)->default(0);
            $table->string('status', 20)->default('active');
            $table->timestamps();
            $table->unique(['requisition_item_id', 'inventory_balance_id'], 'reservation_item_balance_unique');
        }); else DB::statement('ALTER TABLE inventory_reservations ENGINE=InnoDB');

        Schema::table('inventory_balances', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_balances', 'warehouse_id')) $table->foreignId('warehouse_id')->nullable()->after('material_id')->constrained('warehouses')->nullOnDelete();
            if (!Schema::hasColumn('inventory_balances', 'location_id')) $table->foreignId('location_id')->nullable()->after('warehouse_id')->constrained('locations')->nullOnDelete();
            if (!Schema::hasColumn('inventory_balances', 'reserved_qty')) $table->decimal('reserved_qty', 14, 4)->default(0)->after('balance_qty');
        });

        Schema::table('inventory_transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('inventory_transactions', 'location_id')) $table->foreignId('location_id')->nullable()->after('location')->constrained('locations')->nullOnDelete();
        });
        Schema::table('issue_items', function (Blueprint $table) {
            if (!Schema::hasColumn('issue_items', 'location_id')) $table->foreignId('location_id')->nullable()->after('location')->constrained('locations')->nullOnDelete();
        });
        Schema::table('po_receipt_items', function (Blueprint $table) {
            if (!Schema::hasColumn('po_receipt_items', 'location_id')) $table->foreignId('location_id')->nullable()->after('warehouse_id')->constrained('locations')->nullOnDelete();
        });

        Schema::table('daily_production_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('daily_production_logs', 'labor_rate')) $table->decimal('labor_rate', 12, 2)->default(0)->after('working_hours');
            if (!Schema::hasColumn('daily_production_logs', 'actual_labor_cost')) $table->decimal('actual_labor_cost', 14, 2)->default(0)->after('labor_rate');
        });
        Schema::table('order_costings', function (Blueprint $table) {
            if (!Schema::hasColumn('order_costings', 'revenue')) $table->decimal('revenue', 14, 2)->default(0)->after('actual_labor_cost');
            if (!Schema::hasColumn('order_costings', 'material_variance')) $table->decimal('material_variance', 14, 2)->default(0)->after('revenue');
            if (!Schema::hasColumn('order_costings', 'labor_variance')) $table->decimal('labor_variance', 14, 2)->default(0)->after('material_variance');
            if (!Schema::hasColumn('order_costings', 'total_variance')) $table->decimal('total_variance', 14, 2)->default(0)->after('labor_variance');
        });
    }

    public function down(): void
    {
        Schema::table('order_costings', function (Blueprint $table) {
            $table->dropColumn(['revenue', 'material_variance', 'labor_variance', 'total_variance']);
        });
        Schema::table('daily_production_logs', function (Blueprint $table) {
            $table->dropColumn(['labor_rate', 'actual_labor_cost']);
        });
        Schema::table('po_receipt_items', function (Blueprint $table) { $table->dropConstrainedForeignId('location_id'); });
        Schema::table('issue_items', function (Blueprint $table) { $table->dropConstrainedForeignId('location_id'); });
        Schema::table('inventory_transactions', function (Blueprint $table) { $table->dropConstrainedForeignId('location_id'); });
        Schema::table('inventory_balances', function (Blueprint $table) {
            $table->dropIndex('balance_material_warehouse_location');
            $table->dropColumn('reserved_qty');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('warehouse_id');
        });
        Schema::dropIfExists('inventory_reservations');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('bom_colorways');
    }
};
