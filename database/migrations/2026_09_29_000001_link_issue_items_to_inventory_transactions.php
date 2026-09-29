<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issue_items', function (Blueprint $table) {
            $table->foreignId('inventory_transaction_id')->nullable()->after('issue_id')
                ->constrained('inventory_transactions')->nullOnDelete();
            $table->string('status', 20)->default('issued')->after('issued_qty');
        });

        foreach (DB::table('material_issues')->orderBy('id')->pluck('id') as $issueId) {
            $items = DB::table('issue_items')->where('issue_id', $issueId)->whereNull('inventory_transaction_id')->orderBy('id')->get();
            $transactions = DB::table('inventory_transactions')->where('reference_type', 'MATERIAL_ISSUE')
                ->where('reference_id', $issueId)->where('quantity', '<', 0)->orderBy('id')->get();
            $used = [];

            foreach ($items as $item) {
                foreach ($transactions as $transaction) {
                    if (isset($used[$transaction->id])) continue;
                    $matches = (int) $transaction->material_id === (int) $item->material_id
                    && abs(abs((float) $transaction->quantity) - (float) $item->issued_qty) < 0.00001
                        && $transaction->material_color === $item->material_color
                        && $transaction->material_size === $item->material_size
                        && $transaction->lot_roll_no === $item->lot_roll_no
                        && $transaction->location === $item->location
                        && (int) ($transaction->location_id ?? 0) === (int) ($item->location_id ?? 0);
                    if (!$matches) continue;

                    DB::table('issue_items')->where('id', $item->id)->update(['inventory_transaction_id' => $transaction->id]);
                    $used[$transaction->id] = true;
                    break;
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('issue_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_transaction_id');
            $table->dropColumn('status');
        });
    }
};
