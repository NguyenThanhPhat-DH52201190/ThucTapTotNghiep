<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MRPController extends Controller
{
    private function materialIdForBom(object $bom): int
    {
        if (!empty($bom->material_id)) return (int) $bom->material_id;

        $material = DB::table('materials')->where('internal_code', $bom->material_code)->first();
        $id = $material?->id ?? DB::table('materials')->insertGetId([
            'internal_code' => $bom->material_code,
            'material_name' => $bom->material_name,
            'color' => $bom->colour,
            'size' => $bom->size,
            'unit' => $bom->unit,
            'material_type' => $bom->material_type,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('bom_items')->where('id', $bom->id)->update(['material_id' => $id, 'updated_at' => now()]);
        return (int) $id;
    }
    /**
     * Danh sách các đợt tính MRP
     */
    public function index(Request $request)
    {
        $mrps = DB::table('mrp_headers')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.mrp.index', compact('mrps'));
    }

    /**
     * Hiển thị form tính MRP mới
     */
    public function create()
    {
        // Lấy danh sách MTP đang active để chọn
        $mtpRecords = DB::table('mtp')
            ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
            ->where('mtp.Qty_dis', '>', 0)
            ->whereIn('mtp.mps_status', ['planned', 'in_production'])
            ->select('mtp.id', 'mtp.CU', 'mtp.Line', 'mtp.Qty_dis', 'mtp.mps_status',
                     'ocs.SNo as Style', 'ocs.Sname as StyleName',
                     'ocs.bom_header_id')
            ->orderBy('mtp.Line')
            ->get();

        // Lấy tồn kho hiện tại
        $inventory = DB::table('inventory_balances')
            ->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
            ->select('materials.internal_code as material_code', 'inventory_balances.material_color', DB::raw('SUM(inventory_balances.balance_qty - inventory_balances.reserved_qty) as total_available'))
            ->groupBy('materials.internal_code', 'inventory_balances.material_color')
            ->get()
            ->keyBy('material_code');

        return view('admin.mrp.create', compact('mtpRecords', 'inventory'));
    }

    /**
     * Tính toán MRP
     */
    public function calculate(Request $request)
    {
        $request->validate([
            'period_from' => 'nullable|date',
            'period_to' => 'nullable|date',
            'selected_mtp' => 'nullable|array',
            'selected_mtp.*' => 'integer|exists:mtp,id',
        ]);

        set_time_limit(300); // 5 phút cho tính toán lớn

        $lock = Cache::lock('mrp:calculate', 300);
        if (!$lock->get()) return back()->with('error', 'An MRP calculation is already running. Please wait for it to finish.');
        try {
            DB::beginTransaction();

            // Tạo MRP header
            $code = 'MRP-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));

            $headerId = DB::table('mrp_headers')->insertGetId([
                'mrp_code' => $code,
                'period_from' => $request->period_from,
                'period_to' => $request->period_to,
                'status' => 'draft',
                'notes' => $request->notes,
                'created_by' => $request->user()?->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Lấy MTP records
            $mtpQuery = DB::table('mtp')
                ->leftJoin('ocs', 'mtp.CU', '=', 'ocs.CS')
                ->where('mtp.Qty_dis', '>', 0)
                ->whereIn('mtp.mps_status', ['planned', 'in_production'])
                ->select('mtp.id', 'mtp.CU', 'mtp.Qty_dis', 'mtp.planned_cut_start', 'ocs.id as cutsheet_id',
                         'ocs.bom_header_id', 'ocs.SNo as Style', 'ocs.Color as garment_color');

            if ($request->filled('selected_mtp')) {
                $mtpQuery->whereIn('mtp.id', $request->selected_mtp);
            }

            $mtpRecords = $mtpQuery->get();
            $totalMaterials = 0;
            $totalCost = 0;

            // Lấy inventory hiện tại
            $inventory = DB::table('inventory_balances')
                ->join('materials', 'inventory_balances.material_id', '=', 'materials.id')
                ->select('materials.internal_code as material_code', 'inventory_balances.material_color',
                    DB::raw('SUM(inventory_balances.balance_qty) as total_on_hand'),
                    DB::raw('SUM(inventory_balances.reserved_qty) as total_reserved'))
                ->groupBy('materials.internal_code', 'inventory_balances.material_color')
                ->get();
            $inventory = $inventory->keyBy(fn ($row) => $row->material_code . '|' . ($row->material_color ?? ''));

            // Lấy PO đang mở (chưa received) để tính on_order_stock
            $openPOItems = DB::table('po_items')
                ->join('purchase_orders', 'po_items.po_id', '=', 'purchase_orders.id')
                ->whereIn('purchase_orders.status', ['draft', 'sent', 'confirmed', 'partial'])
                ->select('po_items.material_code', 'po_items.color',
                    DB::raw('SUM(po_items.quantity - po_items.received_qty) as pending_qty'))
                ->groupBy('po_items.material_code', 'po_items.color')
                ->get();
            $openPOItems = $openPOItems->keyBy(fn ($row) => $row->material_code . '|' . ($row->color ?? ''));

            // Map material_code -> tổng nhu cầu
            $materialRequirements = [];
            $skippedMtp = [];

            foreach ($mtpRecords as $mtp) {
                if (!$mtp->bom_header_id) {
                    $skippedMtp[] = $mtp->CU;
                    continue;
                }

                $bomItems = DB::table('bom_items')
                    ->where('bom_header_id', $mtp->bom_header_id)
                    ->get();

                foreach ($bomItems as $bom) {
                    $qty = $mtp->Qty_dis ?? 0;
                    $required = $qty * $bom->consumption_rate;
                    // Add waste
                    if ($bom->waste_percent > 0) {
                        $required *= (1 + $bom->waste_percent / 100);
                    }

                    $materialColor = DB::table('bom_colorways')->where('bom_item_id', $bom->id)
                        ->where('garment_color', $mtp->garment_color)->value('material_color') ?? $bom->colour;
                    $key = $bom->material_code . '|' . ($materialColor ?? '');

                    if (!isset($materialRequirements[$key])) {
                        $materialRequirements[$key] = [
                            'material_code' => $bom->material_code,
                            'material_color' => $materialColor,
                            'material_name' => $bom->material_name,
                            'material_type' => $bom->material_type,
                            'unit' => $bom->unit,
                            'gross_requirement' => 0,
                            'lead_time_days' => 0,
                            'unit_cost' => $bom->unit_cost,
                            'sources' => [],
                        ];
                    }

                    $materialRequirements[$key]['gross_requirement'] += $required;
                    $materialRequirements[$key]['sources'][] = [
                        'mtp_id' => $mtp->id,
                        'cutsheet_id' => $mtp->cutsheet_id,
                        'cu' => $mtp->CU,
                        'bom_item_id' => $bom->id,
                        'colour' => $materialColor,
                        'consumption_rate' => $bom->consumption_rate,
                        'required_qty' => $required,
                        'planned_cut_start' => $mtp->planned_cut_start,
                    ];
                }
            }

            // Tạo MRP items
            foreach ($materialRequirements as $key => $req) {
                $onHand = (float)($inventory[$key]->total_on_hand ?? 0);
                $reserved = (float)($inventory[$key]->total_reserved ?? 0);
                $onOrder = (float)($openPOItems[$key]->pending_qty ?? 0);

                $gross = $req['gross_requirement'];
                // Available stock is the only stock MRP can consume:
                // on-hand - allocated/reserved + quantity already on purchase orders.
                $available = max(0, $onHand - $reserved + $onOrder);
                $netRequirement = max(0, $gross - $available);

                // Safety stock: 10% của net requirement
                $safetyStock = round($netRequirement * 0.1, 4);
                $plannedOrder = $netRequirement + $safetyStock;

                // Lead time (giả định 14 ngày cho fabric, 7 ngày cho trim, 3 ngày cho local)
                $leadTime = match($req['material_type']) {
                    'fabric', 'lining' => 14,
                    'zipper', 'label' => 7,
                    default => 3,
                };

                $cutDates = collect($req['sources'])->pluck('planned_cut_start')->filter()->sort();
                $receiptDate = ($cutDates->first() ? \Carbon\Carbon::parse($cutDates->first()) : now()->addDays($leadTime))->format('Y-m-d');
                $releaseDate = \Carbon\Carbon::parse($receiptDate)->subDays($leadTime)->format('Y-m-d');

                // Backfill a master material for legacy BOM rows on first use.
                $material = DB::table('materials')->where('internal_code', $req['material_code'])->first();
                if (!$material) {
                    $materialId = DB::table('materials')->insertGetId([
                        'internal_code' => $req['material_code'], 'material_name' => $req['material_name'],
                        'unit' => $req['unit'], 'material_type' => $req['material_type'],
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                } else {
                    $materialId = $material->id;
                }

                $itemId = DB::table('mrp_items')->insertGetId([
                    'mrp_header_id' => $headerId,
                    'material_code' => $req['material_code'],
                    'material_name' => $req['material_name'],
                    'material_type' => $req['material_type'],
                    'material_id' => $materialId,
                    'material_color' => $req['material_color'],
                    'unit' => $req['unit'],
                    'gross_requirement' => $gross,
                    'on_hand_stock' => $onHand,
                    'on_order_stock' => $onOrder,
                    'allocated_stock' => $reserved,
                    'net_requirement' => $netRequirement,
                    'safety_stock' => $safetyStock,
                    'planned_order_qty' => $plannedOrder,
                    'lead_time_days' => $leadTime,
                    'order_release_date' => $releaseDate,
                    'order_receipt_date' => $receiptDate,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Persist one suggestion per cut sheet, allocating available stock only once.
                $availableForSources = $available;
                $lotBalances = DB::table('inventory_balances')->where('material_id', $materialId)
                    ->when($req['material_color'] !== null, fn ($q) => $q->where('material_color', $req['material_color']))
                    ->whereRaw('balance_qty > reserved_qty')->orderBy('id')->lockForUpdate()->get()
                    ->map(fn ($balance) => (object) ['id' => $balance->id, 'remaining' => (float) $balance->balance_qty - (float) $balance->reserved_qty]);
                foreach ($req['sources'] as $src) {
                    DB::table('mrp_source_details')->insert([
                        'mrp_item_id' => $itemId,
                        'mtp_id' => $src['mtp_id'],
                        'bom_item_id' => $src['bom_item_id'],
                        'cu' => $src['cu'],
                        'required_qty' => $src['required_qty'],
                        'consumption_rate' => $src['consumption_rate'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $sourceAvailable = min($src['required_qty'], $availableForSources);
                    $sourceNet = max(0, $src['required_qty'] - $sourceAvailable);
                    $availableForSources -= $sourceAvailable;
                    $suggestionId = DB::table('mrp_suggestions')->insertGetId([
                        'mrp_header_id' => $headerId, 'cutsheet_id' => $src['cutsheet_id'],
                        'material_id' => $materialId, 'material_color' => $src['colour'],
                        'gross_qty' => $src['required_qty'], 'available_qty' => $sourceAvailable,
                        'net_qty' => $sourceNet,
                        'required_date' => ($src['planned_cut_start'] ? \Carbon\Carbon::parse($src['planned_cut_start'])->format('Y-m-d') : $receiptDate),
                        'status' => $sourceNet > 0 ? 'pending' : 'covered',
                        'created_at' => now(), 'updated_at' => now(),
                    ]);
                    // This is a planning proposal only. It is revalidated and
                    // converted to a real reservation when the order is released.
                    $lotQtyToAllocate = min($sourceAvailable, $lotBalances->sum('remaining'));
                    foreach ($lotBalances as $lot) {
                        if ($lotQtyToAllocate <= 0) break;
                        $qty = min($lotQtyToAllocate, $lot->remaining);
                        if ($qty <= 0) continue;
                        DB::table('mrp_lot_allocations')->insert([
                            'mrp_suggestion_id' => $suggestionId, 'inventory_balance_id' => $lot->id,
                            'allocated_qty' => $qty, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $lot->remaining -= $qty;
                        $lotQtyToAllocate -= $qty;
                    }
                }

                $totalMaterials++;
                $totalCost += $plannedOrder * ($req['unit_cost'] ?? 0);
            }

            // Update header
            DB::table('mrp_headers')->where('id', $headerId)->update([
                'total_materials' => $totalMaterials,
                'total_cost' => $totalCost,
                'status' => 'calculated',
                'updated_at' => now(),
            ]);

            // Kiểm tra có materials không
            if ($totalMaterials === 0) {
                throw new \RuntimeException('No materials to plan! No BOM found for selected MTP records.');
            }

            DB::commit();

            $msg = "MRP calculated: $totalMaterials materials, total cost: " . number_format($totalCost) . " đ";
            if (!empty($skippedMtp)) {
                $msg .= ' | Skipped ' . count($skippedMtp) . ' MTP(s) without BOM: ' . implode(', ', array_slice($skippedMtp, 0, 5));
                if (count($skippedMtp) > 5) $msg .= '...';
            }

            return redirect()->route('admin.mrp.show', $headerId)
                ->with('success', $msg);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MRP calculation failed: ' . $e->getMessage());
            if ($request->boolean('throw_on_failure')) throw $e;
            return back()->with('error', 'MRP calculation failed: ' . $e->getMessage());
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Xem chi tiết MRP
     */
    public function show($id)
    {
        $mrp = DB::table('mrp_headers')->find($id);
        if (!$mrp) abort(404);

        $items = DB::table('mrp_items')
            ->where('mrp_header_id', $id)
            ->orderBy('net_requirement', 'desc')
            ->get();

        $lotAllocations = DB::table('mrp_lot_allocations')
            ->join('mrp_suggestions', 'mrp_lot_allocations.mrp_suggestion_id', '=', 'mrp_suggestions.id')
            ->join('inventory_balances', 'mrp_lot_allocations.inventory_balance_id', '=', 'inventory_balances.id')
            ->where('mrp_suggestions.mrp_header_id', $id)
            ->select('mrp_suggestions.material_id', 'mrp_suggestions.material_color', 'inventory_balances.lot_roll_no',
                'inventory_balances.location', 'mrp_lot_allocations.allocated_qty')->get();

        // Thống kê theo loại vật tư
        $summaryByType = $items->groupBy('material_type')->map(function ($group) {
            return [
                'count' => $group->count(),
                'gross' => $group->sum('gross_requirement'),
                'net' => $group->sum('net_requirement'),
                'planned' => $group->sum('planned_order_qty'),
            ];
        });

        return view('admin.mrp.show', compact('mrp', 'items', 'summaryByType', 'lotAllocations'));
    }

    /**
     * Tạo PO từ MRP items
     */
    public function createPoFromMrp($id)
    {
        $mrp = DB::table('mrp_headers')->find($id);
        if (!$mrp) abort(404);

        $items = DB::table('mrp_items')
            ->where('mrp_header_id', $id)
            ->where('planned_order_qty', '>', 0)
            ->get();

        // Redirect to Procurement with pre-filled data
        return redirect()->route('admin.procurement.create-from-mrp', $id)
            ->with('info', 'Create POs from MRP items below');
    }

    public function destroy($id)
    {
        DB::table('mrp_headers')->where('id', $id)->delete();
        return redirect()->route('admin.mrp.index')
            ->with('success', 'MRP deleted');
    }
}
