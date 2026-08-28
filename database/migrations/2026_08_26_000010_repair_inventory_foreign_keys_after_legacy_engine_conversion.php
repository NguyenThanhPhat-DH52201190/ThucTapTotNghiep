<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['bom_colorways', 'locations', 'inventory_reservations', 'inventory_balances', 'inventory_transactions', 'issue_items', 'po_receipt_items'] as $table) {
            DB::statement("ALTER TABLE `{$table}` ENGINE=InnoDB");
        }

        $keys = [
            ['bom_colorways', 'bom_colorways_bom_item_id_foreign', 'bom_item_id', 'bom_items', 'CASCADE'],
            ['locations', 'locations_warehouse_id_foreign', 'warehouse_id', 'warehouses', 'CASCADE'],
            ['inventory_reservations', 'inventory_reservations_requisition_item_id_foreign', 'requisition_item_id', 'requisition_items', 'CASCADE'],
            ['inventory_reservations', 'inventory_reservations_inventory_balance_id_foreign', 'inventory_balance_id', 'inventory_balances', 'RESTRICT'],
            ['inventory_balances', 'inventory_balances_warehouse_id_foreign', 'warehouse_id', 'warehouses', 'SET NULL'],
            ['inventory_balances', 'inventory_balances_location_id_foreign', 'location_id', 'locations', 'SET NULL'],
            ['inventory_transactions', 'inventory_transactions_location_id_foreign', 'location_id', 'locations', 'SET NULL'],
            ['issue_items', 'issue_items_location_id_foreign', 'location_id', 'locations', 'SET NULL'],
            ['po_receipt_items', 'po_receipt_items_location_id_foreign', 'location_id', 'locations', 'SET NULL'],
        ];
        foreach ($keys as [$table, $name, $column, $reference, $onDelete]) {
            if (!$this->foreignExists($name)) {
                DB::statement("ALTER TABLE `{$table}` ADD CONSTRAINT `{$name}` FOREIGN KEY (`{$column}`) REFERENCES `{$reference}` (`id`) ON DELETE {$onDelete}");
            }
        }
    }

    public function down(): void
    {
        foreach (['po_receipt_items_location_id_foreign', 'issue_items_location_id_foreign', 'inventory_transactions_location_id_foreign', 'inventory_balances_location_id_foreign', 'inventory_balances_warehouse_id_foreign', 'inventory_reservations_inventory_balance_id_foreign', 'inventory_reservations_requisition_item_id_foreign', 'locations_warehouse_id_foreign', 'bom_colorways_bom_item_id_foreign'] as $name) {
            $row = DB::selectOne('SELECT TABLE_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?', [$name]);
            if ($row) DB::statement("ALTER TABLE `{$row->TABLE_NAME}` DROP FOREIGN KEY `{$name}`");
        }
    }

    private function foreignExists(string $name): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::raw('DATABASE()'))->where('CONSTRAINT_NAME', $name)->exists();
    }
};
