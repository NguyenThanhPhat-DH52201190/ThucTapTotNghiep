<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('issue_items') || !Schema::hasColumn('issue_items', 'inventory_transaction_id')) return;

        foreach (DB::table('material_issues')->orderBy('id')->pluck('id') as $issueId) {
            $items = DB::table('issue_items')->where('issue_id', $issueId)->whereNull('inventory_transaction_id')->orderBy('id')->get();
            $transactions = DB::table('inventory_transactions')->where('reference_type', 'MATERIAL_ISSUE')
                ->where('reference_id', $issueId)->where('quantity', '<', 0)->orderBy('id')->get();
            $used = [];

            foreach ($items as $item) {
                $matches = $transactions->filter(function ($transaction) use ($item, $used) {
                    return !isset($used[$transaction->id])
                        && (int) $transaction->material_id === (int) $item->material_id
                        && abs(abs((float) $transaction->quantity) - (float) $item->issued_qty) < 0.00001
                        && $transaction->material_color === $item->material_color
                        && $transaction->material_size === $item->material_size
                        && $transaction->lot_roll_no === $item->lot_roll_no
                        && $transaction->location === $item->location
                        && (int) ($transaction->location_id ?? 0) === (int) ($item->location_id ?? 0);
                });

                // Only link a unique match; ambiguous history must not expose a destructive action.
                if ($matches->count() !== 1) continue;
                $transaction = $matches->first();
                DB::table('issue_items')->where('id', $item->id)->update(['inventory_transaction_id' => $transaction->id]);
                $used[$transaction->id] = true;
            }
        }
    }

    public function down(): void
    {
        // Keep backfilled links; they are required to trace historical inventory changes.
    }
};
