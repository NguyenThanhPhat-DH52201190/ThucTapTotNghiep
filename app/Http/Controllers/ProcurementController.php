<?php

namespace App\Http\Controllers;

use App\Services\OutboxService;
use App\Services\InventoryLedgerService;
use App\Services\AuditTrailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProcurementController extends Controller
{
    public function updateEta(Request $request, int $id, OutboxService $outbox, AuditTrailService $audit)
    {
        $data = $request->validate(['items' => 'required|array|min:1', 'items.*.id' => 'required|exists:po_items,id', 'items.*.expected_date' => 'nullable|date']);
        $cutsheets = DB::transaction(function () use ($id, $data, $outbox, $audit, $request) {
            $po = DB::table('purchase_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
            $cutsheets = [];
            foreach ($data['items'] as $row) {
                $item = DB::table('po_items')->where('id', $row['id'])->where('po_id', $po->id)->lockForUpdate()->firstOrFail();
                if ($item->expected_date !== $row['expected_date']) {
                    DB::table('po_items')->where('id', $item->id)->update(['expected_date' => $row['expected_date'], 'updated_at' => now()]);
                    $outbox->record('po_item_eta_changed', 'po_item', $item->id, ['po_id' => $po->id, 'po_item_id' => $item->id, 'old_eta' => $item->expected_date, 'new_eta' => $row['expected_date']]);
                    $audit->record('eta_changed', 'po_item', (int) $item->id, $request->user()?->id, ['expected_date' => $item->expected_date], ['expected_date' => $row['expected_date']], 'PO #' . $po->po_number);
                }
                if ($item->mrp_suggestion_id) $cutsheets[] = DB::table('mrp_suggestions')->where('id', $item->mrp_suggestion_id)->value('cutsheet_id');
            }
            return $cutsheets;
        });
        $this->syncMaterialReadiness($cutsheets);
        return back()->with('success', 'ETA updated and material readiness recalculated.');
    }
    private function syncMaterialReadiness(array $cutsheetIds): void
    {
        foreach (array_unique(array_filter($cutsheetIds)) as $cutsheetId) {
            $pending = DB::table('mrp_suggestions')->where('cutsheet_id', $cutsheetId)
                ->whereIn('status', ['pending', 'ordered'])->get();
            $status = 'ready';
            foreach ($pending as $suggestion) {
                if ($suggestion->status === 'pending') { $status = 'waiting_material'; break; }
                $eta = DB::table('po_items')->where('mrp_suggestion_id', $suggestion->id)->value('expected_date');
                $start = DB::table('mps_schedules')->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
                    ->where('work_orders.cutsheet_id', $cutsheetId)->min('mps_schedules.planned_start_date');
                if (!$eta) { $status = 'waiting_material'; break; }
                if ($start && $eta > $start) $status = 'delayed';
            }
            DB::table('mps_schedules')->join('work_orders', 'mps_schedules.work_order_id', '=', 'work_orders.id')
                ->where('work_orders.cutsheet_id', $cutsheetId)
                ->update(['material_eta_status' => $status, 'updated_at' => now()]);
        }
    }

    public function createFromSuggestions(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:suppliers,id',
            'suggestion_ids' => 'required|array|min:1',
            'suggestion_ids.*' => 'integer|exists:mrp_suggestions,id',
        ]);
        $vendor = DB::table('suppliers')->where('id', $request->vendor_id)->where('status', 'active')->first();
        if (!$vendor) return back()->with('error', 'Vendor is not active.');

        try {
            $poId = DB::transaction(function () use ($request, $vendor) {
                $suggestions = DB::table('mrp_suggestions')->join('materials', 'mrp_suggestions.material_id', '=', 'materials.id')
                    ->whereIn('mrp_suggestions.id', $request->suggestion_ids)->where('mrp_suggestions.status', 'pending')
                    ->select('mrp_suggestions.*', 'materials.internal_code', 'materials.material_name', 'materials.unit')->lockForUpdate()->get();
                if ($suggestions->isEmpty()) throw new \RuntimeException('No pending MRP suggestions were selected.');
                $number = 'PO-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));
                $poId = DB::table('purchase_orders')->insertGetId([
                    'po_number' => $number, 'supplier_id' => $vendor->id, 'order_date' => today(),
                    'expected_delivery' => $suggestions->max('required_date'), 'status' => 'draft', 'currency' => 'VND',
                    'total_amount' => 0, 'created_by' => $request->user()?->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                foreach ($suggestions as $suggestion) {
                    $price = DB::table('material_vendors')->where('material_id', $suggestion->material_id)->where('vendor_id', $vendor->id)->value('unit_price') ?? 0;
                    DB::table('po_items')->insert([
                        'po_id' => $poId, 'mrp_suggestion_id' => $suggestion->id, 'material_id' => $suggestion->material_id,
                        'material_code' => $suggestion->internal_code, 'material_name' => $suggestion->material_name,
                        'color' => $suggestion->material_color, 'unit' => $suggestion->unit, 'quantity' => $suggestion->net_qty,
                        'unit_price' => $price, 'total_price' => $suggestion->net_qty * $price,
                        'expected_date' => $suggestion->required_date, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    DB::table('mrp_suggestions')->where('id', $suggestion->id)->update(['status' => 'ordered', 'updated_at' => now()]);
                }
                DB::table('purchase_orders')->where('id', $poId)->update(['total_amount' => DB::table('po_items')->where('po_id', $poId)->sum('total_price')]);
                return $poId;
            });
            $this->syncMaterialReadiness(DB::table('mrp_suggestions')->whereIn('id', $request->suggestion_ids)->pluck('cutsheet_id')->all());
            return redirect()->route('admin.procurement.show', $poId)->with('success', 'PO created from MRP suggestions.');
        } catch (\Throwable $e) {
            Log::error('Create PO from MRP suggestions failed', ['message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }
    // ============ SUPPLIERS ============
    public function suppliers(Request $request)
    {
        $suppliers = DB::table('suppliers')
            ->when($request->filled('search'), fn($q) => $q->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('code', 'like', '%'.$request->search.'%'))
            ->orderBy('name')
            ->paginate(15);

        return view('admin.procurement.suppliers', compact('suppliers'));
    }

    public function suppliersStore(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:suppliers,code',
            'name' => 'required',
            'phone' => 'nullable',
            'email' => 'nullable|email',
            'lead_time_days' => 'nullable|integer|min:0',
        ]);

        DB::table('suppliers')->insert([
            'code' => $request->code,
            'name' => $request->name,
            'contact_person' => $request->contact_person,
            'phone' => $request->phone,
            'email' => $request->email,
            'address' => $request->address,
            'payment_terms' => $request->payment_terms,
            'lead_time_days' => $request->lead_time_days ?? 0,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Supplier added');
    }

    public function supplierUpdate(Request $request, int $id)
    {
        $data = $request->validate(['code' => 'required|string|max:50|unique:suppliers,code,' . $id, 'name' => 'required|string|max:191', 'contact_person' => 'nullable|string|max:191', 'phone' => 'nullable|string|max:50', 'email' => 'nullable|email|max:191', 'address' => 'nullable|string', 'payment_terms' => 'nullable|string|max:100', 'lead_time_days' => 'nullable|integer|min:0', 'status' => 'required|in:active,inactive,blacklisted', 'notes' => 'nullable|string']);
        DB::table('suppliers')->where('id', $id)->update($data + ['lead_time_days' => $data['lead_time_days'] ?? 0, 'updated_at' => now()]);
        return back()->with('success', 'Supplier updated.');
    }

    // ============ PURCHASE ORDERS ============
    public function index(Request $request)
    {
        $pos = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code')
            ->when($request->filled('status'), fn($q) => $q->where('purchase_orders.status', $request->status))
            ->orderBy('purchase_orders.created_at', 'desc')
            ->paginate(15);

        return view('admin.procurement.index', compact('pos'));
    }

    public function create()
    {
        $suppliers = DB::table('suppliers')->where('status', 'active')->orderBy('name')->get();
        return view('admin.procurement.create', compact('suppliers'));
    }

    public function createFromMrp($mrpId)
    {
        $mrp = DB::table('mrp_headers')->find($mrpId);
        if (!$mrp) abort(404);

        $items = DB::table('mrp_items')
            ->where('mrp_header_id', $mrpId)
            ->where('planned_order_qty', '>', 0)
            ->get();

        $suggestions = DB::table('mrp_suggestions')
            ->where('mrp_header_id', $mrpId)->where('status', 'pending')->get()->keyBy('material_id');

        $suppliers = DB::table('suppliers')->where('status', 'active')->orderBy('name')->get();

        return view('admin.procurement.create-from-mrp', compact('mrp', 'items', 'suppliers', 'suggestions'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'order_date' => 'required|date',
            'expected_delivery' => 'nullable|date|after_or_equal:order_date',
            'items' => 'required|array|min:1',
            'items.*.material_code' => 'required',
            'items.*.material_name' => 'required',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.mrp_suggestion_id' => 'nullable|exists:mrp_suggestions,id',
            'items.*.material_id' => 'nullable|exists:materials,id',
        ]);

        // Check supplier is active
        $supplier = DB::table('suppliers')->find($request->supplier_id);
        if (!$supplier || $supplier->status !== 'active') {
            return back()->with('error', 'Supplier is not active!')->withInput();
        }

        try {
            DB::beginTransaction();

            $poNumber = 'PO-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));

            $totalAmount = 0;
            $poItems = [];
            foreach ($request->items as $item) {
                $totalPrice = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $totalAmount += $totalPrice;
                $poItems[] = [
                    'material_code' => $item['material_code'],
                    'material_name' => $item['material_name'],
                    'unit' => $item['unit'] ?? 'M',
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $totalPrice,
                    'expected_date' => $item['expected_date'] ?? null,
                    'notes' => $item['notes'] ?? null,
                    'mrp_suggestion_id' => $item['mrp_suggestion_id'] ?? null,
                    'material_id' => $item['material_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'order_date' => $request->order_date,
                'expected_delivery' => $request->expected_delivery,
                'status' => 'draft',
                'total_amount' => $totalAmount,
                'notes' => $request->notes,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($poItems as &$item) {
                $item['po_id'] = $poId;
            }
            DB::table('po_items')->insert($poItems);
            $suggestionIds = collect($poItems)->pluck('mrp_suggestion_id')->filter()->all();
            if ($suggestionIds) DB::table('mrp_suggestions')->whereIn('id', $suggestionIds)->update(['status' => 'ordered', 'updated_at' => now()]);
            $this->syncMaterialReadiness(DB::table('mrp_suggestions')->whereIn('id', $suggestionIds)->pluck('cutsheet_id')->all());

            DB::commit();

            return redirect()->route('admin.procurement.show', $poId)
                ->with('success', "PO $poNumber created successfully");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PO creation failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to create PO: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $po = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code',
                     'suppliers.contact_person', 'suppliers.phone', 'suppliers.email')
            ->where('purchase_orders.id', $id)
            ->first();
        if (!$po) abort(404);

        $items = DB::table('po_items')->where('po_id', $id)->get();
        $receipts = DB::table('po_receipts')->where('po_id', $id)->orderBy('received_date', 'desc')->get();
        $warehouses = DB::table('warehouses')->where('is_active', 1)->orderBy('name')->get();
        $locations = DB::table('locations')->where('is_active', 1)->orderBy('location_code')->get();

        return view('admin.procurement.show', compact('po', 'items', 'receipts', 'warehouses', 'locations'));
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:draft,sent,confirmed,received,partial,cancelled',
        ]);

        $allowedTransitions = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['confirmed', 'cancelled'],
            'confirmed' => ['received', 'cancelled'],
            'partial' => ['received', 'cancelled'],
            'received' => [],
            'cancelled' => [],
        ];

        $newStatus = $request->status;
        try {
            DB::transaction(function () use ($id, $newStatus, $allowedTransitions) {
                $po = DB::table('purchase_orders')->where('id', $id)->lockForUpdate()->first();
                if (!$po) abort(404);
                if (!in_array($newStatus, $allowedTransitions[$po->status] ?? [], true)) {
                    throw new \RuntimeException("Cannot change status from '{$po->status}' to '{$newStatus}'.");
                }
                DB::table('purchase_orders')->where('id', $id)->update(['status' => $newStatus, 'updated_at' => now()]);
                if ($newStatus === 'received') $this->receiveToInventory($id);
            });
        } catch (\Throwable $e) {
            Log::warning('PO status update rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }

        $this->syncMaterialReadiness(DB::table('mrp_suggestions')->join('po_items', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')
            ->where('po_items.po_id', $id)->pluck('mrp_suggestions.cutsheet_id')->all());

        return back()->with('success', "PO status updated to {$newStatus}");
    }

    public function receive(Request $request, $id, AuditTrailService $audit)
    {
        $data = $request->validate([
            'received_date' => 'required|date', 'reference_number' => 'nullable|string|max:191', 'notes' => 'nullable|string',
            'warehouse_id' => 'required|exists:warehouses,id', 'location_id' => 'nullable|exists:locations,id',
            'items' => 'required|array|min:1', 'items.*.po_item_id' => 'required|exists:po_items,id',
            'items.*.quantity' => 'required|numeric|gt:0', 'items.*.lot_roll_no' => 'nullable|string|max:100',
        ]);
        try {
            DB::transaction(function () use ($id, $data) {
                $po = DB::table('purchase_orders')->where('id', $id)->lockForUpdate()->first();
                if (!$po || !in_array($po->status, ['confirmed', 'partial'], true)) {
                    throw new \RuntimeException('Only confirmed or partially received POs can receive goods.');
                }
                if (!empty($data['location_id']) && !DB::table('locations')->where('id', $data['location_id'])->where('warehouse_id', $data['warehouse_id'])->exists()) {
                    throw new \RuntimeException('The selected location does not belong to the selected warehouse.');
                }
                $quantities = collect($data['items'])->keyBy('po_item_id')->map(fn ($item) => (float) $item['quantity'])->all();
                $this->receiveToInventory($id, $quantities, $data);
            });
            $this->syncMaterialReadiness(DB::table('mrp_suggestions')->join('po_items', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')
                ->where('po_items.po_id', $id)->pluck('mrp_suggestions.cutsheet_id')->all());
            $audit->record('goods_received', 'purchase_order', (int) $id, $request->user()?->id, [], ['items' => $data['items'], 'received_date' => $data['received_date']], $data['reference_number'] ?? null);
            return back()->with('success', 'Goods receipt posted to inventory.');
        } catch (\Throwable $e) {
            Log::warning('PO receipt rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Receive items to inventory without overriding PO status
     */
    private function receiveToInventory($poId, ?array $quantities = null, array $meta = [])
    {
        return DB::transaction(function () use ($poId, $quantities, $meta) {
        $po = DB::table('purchase_orders')->where('id', $poId)->lockForUpdate()->first();
        $items = DB::table('po_items')->where('po_id', $poId)->lockForUpdate()->get();
        $receiptEntries = collect($meta['items'] ?? [])->keyBy('po_item_id');

        // Check warehouse exists
        $warehouse = !empty($meta['warehouse_id']) ? DB::table('warehouses')->find($meta['warehouse_id']) : DB::table('warehouses')->where('type', 'raw_material')->first();
        if (!$warehouse) {
            Log::warning('Cannot receive PO to inventory: no raw_material warehouse found');
            return;
        }

        $receiptNo = 'RCP-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));
        $receiptId = DB::table('po_receipts')->insertGetId([
            'receipt_number' => $receiptNo,
            'po_id' => $poId,
            'received_date' => $meta['received_date'] ?? now()->format('Y-m-d'),
            'reference_number' => $meta['reference_number'] ?? null,
            'notes' => $meta['notes'] ?? 'Auto-received on PO status update',
            'created_by' => request()->user()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($items as $item) {
            $remaining = $item->quantity - $item->received_qty;
            if ($remaining <= 0) continue;

            $receiveQty = $quantities === null ? $remaining : ($quantities[$item->id] ?? 0);
            $receiptEntry = $receiptEntries->get($item->id);
            if ($receiveQty <= 0) continue;
            if ($receiveQty > $remaining) throw new \RuntimeException("Received quantity exceeds remaining quantity for {$item->material_code}.");

            DB::table('po_receipt_items')->insert([
                'po_receipt_id' => $receiptId,
                'po_item_id' => $item->id,
                'material_code' => $item->material_code,
                'quantity_received' => $receiveQty,
                'batch_no' => $receiptEntry['lot_roll_no'] ?? null,
                'warehouse_id' => $warehouse->id,
                'location_id' => $meta['location_id'] ?? null,
                'material_id' => $item->material_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('po_items')->where('id', $item->id)->update([
                'received_qty' => $item->received_qty + $receiveQty,
                'status' => ($item->received_qty + $receiveQty >= $item->quantity) ? 'received' : 'partial',
                'updated_at' => now(),
            ]);

            $materialId = $item->material_id;
            if (!$materialId) {
                $materialId = DB::table('materials')->where('internal_code', $item->material_code)->value('id')
                    ?? DB::table('materials')->insertGetId([
                        'internal_code' => $item->material_code, 'material_name' => $item->material_name,
                        'unit' => $item->unit, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                DB::table('po_items')->where('id', $item->id)->update(['material_id' => $materialId]);
                DB::table('po_receipt_items')->where('po_receipt_id', $receiptId)->where('po_item_id', $item->id)
                    ->update(['material_id' => $materialId]);
            }

            app(InventoryLedgerService::class)->receive([
                'reference_type' => 'PO_RECEIPT', 'reference_id' => $receiptId, 'reference_doc' => $receiptNo,
                'material_id' => $materialId, 'material_code' => $item->material_code, 'color' => $item->color,
                'quantity' => $receiveQty, 'unit' => $item->unit, 'warehouse_id' => $warehouse->id,
                'location_id' => $meta['location_id'] ?? null,
                'lot_roll_no' => $receiptEntry['lot_roll_no'] ?? null,
                'unit_cost' => $item->unit_price, 'notes' => "Received from PO #{$po->po_number}",
                'user_id' => request()->user()?->id,
            ]);
            if ($item->mrp_suggestion_id && $item->received_qty + $receiveQty >= $item->quantity) {
                DB::table('mrp_suggestions')->where('id', $item->mrp_suggestion_id)->update(['status' => 'received', 'updated_at' => now()]);
            }
        }

        $updatedStatus = DB::table('po_items')->where('po_id', $poId)
            ->where('status', '!=', 'received')->exists() ? 'partial' : 'received';

        // Only update PO status if different from what caller already set
        $currentPoStatus = DB::table('purchase_orders')->where('id', $poId)->value('status');
        if ($currentPoStatus !== $updatedStatus) {
            DB::table('purchase_orders')->where('id', $poId)->update([
                'status' => $updatedStatus,
                'updated_at' => now(),
            ]);
        }
        });
    }

}
