<?php

namespace App\Http\Controllers;

use App\Services\OutboxService;
use App\Services\InventoryLedgerService;
use App\Services\AuditTrailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcurementController extends Controller
{
    public function updateEta(Request $request, int $id, OutboxService $outbox, AuditTrailService $audit)
    {
        $data = $request->validate(['items' => 'required|array|min:1', 'items.*.id' => 'required|exists:po_items,id', 'items.*.expected_date' => 'nullable|date']);
        $cutsheets = DB::transaction(function () use ($id, $data, $outbox, $audit, $request) {
            $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->firstOrFail();
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
                ->update([
                    'mps_schedules.material_eta_status' => $status,
                    'mps_schedules.updated_at' => now(),
                ]);
        }
    }

    public function createFromSuggestions(Request $request)
    {
        $request->validate([
            'vendor_id' => 'required|exists:suppliers,id',
            'suggestion_ids' => 'required|array|min:1',
            'suggestion_ids.*' => 'integer|exists:mrp_suggestions,id',
            'currency' => 'nullable|in:VND,USD',
            'exchange_rate' => 'nullable|numeric|decimal:0,6|gt:0',
            'vat_percent' => 'nullable|numeric|decimal:0,2|between:0,100',
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
                    'expected_delivery' => $suggestions->max('required_date'), 'status' => 'draft', 'currency' => $request->currency ?? 'USD',
                    'exchange_rate' => $request->exchange_rate ?? 1, 'vat_percent' => $request->vat_percent ?? 0,
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
        $direction = $request->input('sort', 'code_asc') === 'code_desc' ? 'desc' : 'asc';
        $search = trim((string) $request->input('search', ''));
        $suppliers = DB::table('suppliers')
            ->when($search !== '', fn ($q) => $q->where(fn ($sub) =>
                $sub->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('contact_person', 'like', '%'.$search.'%')))
            ->when($request->filled('code'), fn ($q) => $q->where('code', 'like', '%'.trim($request->input('code')).'%'))
            ->orderBy('code', $direction)
            ->orderBy('id')
            ->paginate(15)->withQueryString();

        return view('admin.procurement.suppliers', compact('suppliers'));
    }

    private function supplierBankData(Request $request): array
    {
        $data = $request->validate([
            'tax_code' => 'nullable|string|max:50',
            'bank_accounts' => 'nullable|array|max:50',
            'bank_accounts.*' => 'array:account_number,bank_name',
            'bank_accounts.*.account_number' => 'nullable|required_with:bank_accounts.*.bank_name|string|max:100',
            'bank_accounts.*.bank_name' => 'nullable|required_with:bank_accounts.*.account_number|string|max:191',
        ]);
        $accounts = collect($data['bank_accounts'] ?? [])
            ->filter(fn ($account) => !empty($account['account_number']) || !empty($account['bank_name']))
            ->values()->all();

        return ['tax_code' => $data['tax_code'] ?? null, 'bank_accounts' => json_encode($accounts, JSON_UNESCAPED_UNICODE)];
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

        $bankData = $this->supplierBankData($request);
        DB::table('suppliers')->insert($bankData + [
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
        $data = array_merge($data, $this->supplierBankData($request));
        DB::table('suppliers')->where('id', $id)->update($data + ['lead_time_days' => $data['lead_time_days'] ?? 0, 'updated_at' => now()]);
        return back()->with('success', 'Supplier updated.');
    }

    // ============ PURCHASE ORDERS ============
    public function index(Request $request)
    {
        $pos = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code')
            ->whereNull('purchase_orders.deleted_at')
            ->when($request->filled('status'), fn($q) => $q->where('purchase_orders.status', $request->status))
            ->orderBy('purchase_orders.created_at', 'desc')
            ->paginate(15);

        return view('admin.procurement.index', compact('pos'));
    }

    public function create()
    {
        $suppliers = DB::table('suppliers')->where('status', 'active')->orderBy('name')->get();
        $vendorMaterials = $this->vendorMaterials($suppliers->pluck('id')->all());
        $units = DB::table('materials')->whereNotNull('unit')->whereRaw("TRIM(unit) <> ''")->distinct()->orderBy('unit')->pluck('unit');
        return view('admin.procurement.create', compact('suppliers', 'vendorMaterials', 'units'));
    }

    public function edit($id)
    {
        $po = DB::table('purchase_orders')->whereNull('deleted_at')->find($id);
        if (!$po) abort(404);
        $items = DB::table('po_items')->where('po_id', $id)->orderBy('id')->get();
        $surcharges = DB::table('po_surcharges')->where('po_id', $id)->orderBy('id')->get();
        $suppliers = DB::table('suppliers')
            ->where(fn ($query) => $query->where('status', 'active')->orWhere('id', $po->supplier_id))
            ->orderBy('name')->get();
        $vendorMaterials = $this->vendorMaterials($suppliers->pluck('id')->all());
        $units = DB::table('materials')->whereNotNull('unit')->whereRaw("TRIM(unit) <> ''")->distinct()->orderBy('unit')->pluck('unit');

        return view('admin.procurement.create', compact('suppliers', 'vendorMaterials', 'units', 'po', 'items', 'surcharges'));
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
        $vendorPrices = DB::table('material_vendors')
            ->whereIn('material_id', $items->pluck('material_id')->filter()->unique())
            ->whereIn('vendor_id', $suppliers->pluck('id'))
            ->select('material_id', 'vendor_id', 'unit_price', 'lead_time_days')
            ->get()
            ->values();

        return view('admin.procurement.create-from-mrp', compact('mrp', 'items', 'suppliers', 'suggestions', 'vendorPrices'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'po_number' => 'required|string|max:50|unique:purchase_orders,po_number',
        ]);
        $data = $this->validatePo($request);

        // Check supplier is active
        $supplier = DB::table('suppliers')->find($request->supplier_id);
        if (!$supplier || $supplier->status !== 'active') {
            return back()->with('error', 'Supplier is not active!')->withInput();
        }

        try {
            DB::beginTransaction();

            $poNumber = $request->input('po_number');

            $totalAmount = 0;
            $poItems = [];
            foreach ($request->items as $item) {
                $material = DB::table('materials')->find($item['material_id']);
                if (!$material) throw new \RuntimeException('Selected material no longer exists in Material Master.');
                $totalPrice = ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0);
                $totalAmount += $totalPrice;
                $poItems[] = [
                    'material_code' => $material->internal_code,
                    'material_name' => $material->material_name,
                    'unit' => $material->unit,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'] ?? 0,
                    'total_price' => $totalPrice,
                    'expected_date' => !empty($item['mrp_suggestion_id'])
                        ? DB::table('mrp_suggestions')->where('id', $item['mrp_suggestion_id'])->value('required_date')
                        : null,
                    'notes' => $item['notes'] ?? null,
                    'mrp_suggestion_id' => $item['mrp_suggestion_id'] ?? null,
                    'material_id' => $item['material_id'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            [$poSurcharges, $surchargeTotal] = $this->preparePoSurcharges($data['surcharges'] ?? []);
            $totalAmount += $surchargeTotal;

            $poId = DB::table('purchase_orders')->insertGetId([
                'po_number' => $poNumber,
                'supplier_id' => $request->supplier_id,
                'order_date' => $request->order_date,
                'expected_delivery' => $request->expected_delivery,
                'status' => 'draft',
                'currency' => $data['currency'],
                'exchange_rate' => $data['exchange_rate'],
                'vat_percent' => $data['vat_percent'] ?? 0,
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
            foreach ($poSurcharges as &$surcharge) $surcharge['po_id'] = $poId;
            unset($surcharge);
            if ($poSurcharges) DB::table('po_surcharges')->insert($poSurcharges);
            $suggestionIds = collect($poItems)->pluck('mrp_suggestion_id')->filter()->all();
            if ($suggestionIds) DB::table('mrp_suggestions')->whereIn('id', $suggestionIds)->update(['status' => 'ordered', 'updated_at' => now()]);
            $this->syncMaterialReadiness(DB::table('mrp_suggestions')->whereIn('id', $suggestionIds)->pluck('cutsheet_id')->all());

            DB::commit();

            return redirect()->route('admin.procurement.show', $poId)
                ->with('success', "PO $poNumber created successfully");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PO creation failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create PO: ' . $e->getMessage());
        }
    }

    public function show($id)
    {
        $po = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.*', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code',
                     'suppliers.contact_person', 'suppliers.phone', 'suppliers.email', 'suppliers.payment_terms as supplier_payment_terms')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.id', $id)
            ->first();
        if (!$po) abort(404);

        $items = DB::table('po_items')->leftJoin('materials', 'po_items.material_id', '=', 'materials.id')
            ->where('po_items.po_id', $id)->select('po_items.*', 'materials.size as default_material_size', 'materials.color as default_material_color')->get();
        $surcharges = DB::table('po_surcharges')->where('po_id', $id)->orderBy('id')->get();
        $receipts = DB::table('po_receipts')->where('po_id', $id)->orderBy('received_date', 'desc')->get();
        $receiptItems = DB::table('po_receipt_items')->whereIn('po_receipt_id', $receipts->pluck('id'))
            ->orderBy('id')->get()->groupBy('po_receipt_id');
        $warehouses = DB::table('warehouses')->where('is_active', 1)->orderBy('name')->get();
        $locations = DB::table('locations')->where('is_active', 1)->orderBy('location_code')->get();

        return view('admin.procurement.show', compact('po', 'items', 'surcharges', 'receipts', 'receiptItems', 'warehouses', 'locations'));
    }

    public function receiptHistory($id, $receiptId)
    {
        $po = DB::table('purchase_orders')
            ->leftJoin('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->select('purchase_orders.id', 'purchase_orders.po_number', 'suppliers.name as supplier_name', 'suppliers.code as supplier_code')
            ->whereNull('purchase_orders.deleted_at')
            ->where('purchase_orders.id', $id)
            ->first();
        if (!$po) abort(404);

        $receipt = DB::table('po_receipts')
            ->where('po_id', $id)
            ->where('id', $receiptId)
            ->first();
        if (!$receipt) abort(404);

        $items = DB::table('po_receipt_items')
            ->where('po_receipt_id', $receiptId)
            ->orderBy('id')
            ->get();

        return view('admin.procurement.receipt-history', compact('po', 'receipt', 'items'));
    }

    public function updateReceiptHistory(Request $request, $id, $receiptId, AuditTrailService $audit)
    {
        $data = $request->validate([
            'received_date' => 'required|date',
            'customs_declaration_date' => 'nullable|date',
            'customs_declaration_number' => 'nullable|string|max:100',
            'contract_number' => 'nullable|string|max:100',
            'reference_number' => 'nullable|string|max:191',
            'notes' => 'nullable|string|max:5000',
            'items' => 'nullable|array',
            'items.*.id' => 'required|integer',
            'items.*.customs_material_code' => 'nullable|string|max:100',
            'items.*.customs_unit_price' => 'nullable|numeric|decimal:0,4|min:0',
            'items.*.quantity_received' => 'nullable|numeric|min:0|decimal:0,4',
            'quantity_correction_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $before = null;
            DB::transaction(function () use ($id, $receiptId, $data, &$before, $request) {
                $receipt = DB::table('po_receipts')->where('po_id', $id)->where('id', $receiptId)->lockForUpdate()->first();
                if (!$receipt) abort(404);
                $po = DB::table('purchase_orders')->where('id', $id)->lockForUpdate()->firstOrFail();
                $before = (array) $receipt;
                $before['items'] = DB::table('po_receipt_items')->where('po_receipt_id', $receiptId)->get()->toArray();

                DB::table('po_receipts')->where('id', $receiptId)->update([
                    'received_date' => $data['received_date'],
                    'customs_declaration_date' => $data['customs_declaration_date'] ?? null,
                    'customs_declaration_number' => $data['customs_declaration_number'] ?? null,
                    'contract_number' => $data['contract_number'] ?? null,
                    'reference_number' => $data['reference_number'] ?? null,
                    'notes' => $data['notes'] ?? null,
                    'updated_at' => now(),
                ]);

                foreach ($data['items'] ?? [] as $item) {
                    $receiptItem = DB::table('po_receipt_items')->where('po_receipt_id', $receiptId)
                        ->where('id', $item['id'])->lockForUpdate()->first();
                    if (!$receiptItem) throw new \RuntimeException('A receipt item does not belong to this receipt.');

                    $changes = [
                        'customs_material_code' => $item['customs_material_code'] ?? null,
                        'customs_unit_price' => $item['customs_unit_price'] ?? null,
                        'updated_at' => now(),
                    ];
                    if (array_key_exists('quantity_received', $item) && $item['quantity_received'] !== null) {
                        $newQuantity = (float) $item['quantity_received'];
                        $oldQuantity = (float) $receiptItem->quantity_received;
                        $difference = round($newQuantity - $oldQuantity, 4);
                        if (abs($difference) >= 0.00001) {
                            if (trim((string) ($data['quantity_correction_reason'] ?? '')) === '') {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    'quantity_correction_reason' => 'A reason is required when changing a received quantity.',
                                ]);
                            }
                            $poItem = DB::table('po_items')->where('id', $receiptItem->po_item_id)->lockForUpdate()->firstOrFail();
                            $receivedForPoItem = (float) DB::table('po_receipt_items')->where('po_item_id', $poItem->id)->sum('quantity_received');
                            if ($receivedForPoItem + $difference > (float) $poItem->quantity + 0.00001) {
                                throw \Illuminate\Validation\ValidationException::withMessages([
                                    "items.{$item['id']}.quantity_received" => 'Corrected received quantity cannot exceed the PO item quantity.',
                                ]);
                            }

                            $material = $receiptItem->material_id
                                ? DB::table('materials')->where('id', $receiptItem->material_id)->first()
                                : DB::table('materials')->where('internal_code', $receiptItem->material_code)->first();
                            if (!$material) throw new \RuntimeException('The material for this receipt line no longer exists.');
                            $sourceTransaction = DB::table('inventory_transactions')->where('reference_type', 'PO_RECEIPT')
                                ->where('reference_id', $receiptId)->where('material_id', $material->id)
                                ->where('material_color', $receiptItem->material_color)->where('material_size', $receiptItem->material_size)
                                ->where('lot_roll_no', $receiptItem->batch_no)->where('lot_no', $receiptItem->lot_no)
                                ->where('roll_no', $receiptItem->roll_no)->where('to_warehouse_id', $receiptItem->warehouse_id)
                                ->when($receiptItem->location_id === null, fn ($query) => $query->whereNull('location_id'), fn ($query) => $query->where('location_id', $receiptItem->location_id))
                                ->orderBy('id')->first();
                            $unitCost = (float) ($sourceTransaction->unit_cost ?? $poItem->unit_price);
                            if (!$sourceTransaction) {
                                $unitCost *= 1 + (float) ($po->vat_percent ?? 0) / 100;
                                if (strtoupper((string) ($po->currency ?? 'USD')) === 'VND') {
                                    $exchangeRate = (float) ($po->exchange_rate ?? 0);
                                    if ($exchangeRate <= 0) throw new \RuntimeException('A valid VND to USD exchange rate is required.');
                                    $unitCost /= $exchangeRate;
                                }
                            }
                            $materialColor = $receiptItem->material_color;
                            $materialSize = $receiptItem->material_size;
                            $lotRollNo = $receiptItem->batch_no;
                            app(InventoryLedgerService::class)->correctReceiptQuantity([
                                'receipt_item_id' => $receiptItem->id, 'reference_doc' => $receipt->receipt_number,
                                'transaction_date' => $receipt->received_date, 'material_id' => $material->id,
                                'material_code' => $receiptItem->material_code, 'color' => $materialColor,
                                'size' => $materialSize, 'quantity_delta' => $difference, 'unit' => $poItem->unit,
                                'warehouse_id' => $receiptItem->warehouse_id, 'location_id' => $receiptItem->location_id,
                                'lot_roll_no' => $lotRollNo, 'lot_no' => $receiptItem->lot_no,
                                'roll_no' => $receiptItem->roll_no, 'unit_cost' => round($unitCost, 4),
                                'notes' => 'PO receipt quantity correction. Reason: '.$data['quantity_correction_reason'],
                                'user_id' => $request->user()->id,
                            ]);
                            $changes['quantity_received'] = $newQuantity;

                            DB::table('po_items')->where('id', $poItem->id)->update([
                                'received_qty' => round($receivedForPoItem + $difference, 4),
                                'status' => $receivedForPoItem + $difference + 0.00001 >= (float) $poItem->quantity
                                    ? 'received' : (($receivedForPoItem + $difference) > 0 ? 'partial' : 'pending'),
                                'updated_at' => now(),
                            ]);
                        }
                    }
                    DB::table('po_receipt_items')->where('id', $receiptItem->id)->update($changes);
                }

                if (!in_array($po->status, ['closed', 'cancelled'], true)) {
                    $poItems = DB::table('po_items')->where('po_id', $id)->get();
                    $activeItems = $poItems->where('status', '!=', 'cancelled');
                    $status = $activeItems->isNotEmpty() && $activeItems->every(fn ($line) => $line->status === 'received')
                        ? 'received'
                        : ($activeItems->contains(fn ($line) => (float) $line->received_qty > 0) ? 'partial' : 'confirmed');
                    DB::table('purchase_orders')->where('id', $id)->update(['status' => $status, 'updated_at' => now()]);
                }
            });

            $after = DB::table('po_receipts')->where('id', $receiptId)->first();
            $afterData = (array) $after;
            $afterData['items'] = DB::table('po_receipt_items')->where('po_receipt_id', $receiptId)->get()->toArray();
            $audit->record('receipt_history_updated', 'po_receipt', (int) $receiptId, $request->user()?->id,
                $before ?? [], $afterData, $data['quantity_correction_reason'] ?? $after->reference_number ?? null);

            return redirect()->route('admin.procurement.receipts.show', [$id, $receiptId])->with('success', 'Receipt updated. Inventory corrections, PO received quantities, and PO status were synchronized.');
        } catch (\Throwable $e) {
            Log::warning('Receipt history update rejected', ['po_id' => $id, 'receipt_id' => $receiptId, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', 'Could not update receipt history: ' . $e->getMessage());
        }
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:draft,sent,confirmed,received,partial,cancelled',
        ]);

        $allowedTransitions = [
            'draft' => ['sent', 'cancelled'],
            'sent' => ['confirmed', 'cancelled'],
            // Goods are received only by the receipt form, which records the physical lot and location.
            'confirmed' => ['cancelled'],
            'partial' => ['cancelled'],
            'received' => [],
            'cancelled' => [],
        ];

        $newStatus = $request->status;
        try {
            DB::transaction(function () use ($id, $newStatus, $allowedTransitions) {
                $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->first();
                if (!$po) abort(404);
                if (!in_array($newStatus, $allowedTransitions[$po->status] ?? [], true)) {
                    throw new \RuntimeException("Cannot change status from '{$po->status}' to '{$newStatus}'.");
                }
                DB::table('purchase_orders')->where('id', $id)->update(['status' => $newStatus, 'updated_at' => now()]);
            });
        } catch (\Throwable $e) {
            Log::warning('PO status update rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }

        $this->syncMaterialReadiness(DB::table('mrp_suggestions')->join('po_items', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')
            ->where('po_items.po_id', $id)->pluck('mrp_suggestions.cutsheet_id')->all());

        return back()->with('success', "PO status updated to {$newStatus}");
    }

    public function close(int $id, Request $request, AuditTrailService $audit)
    {
        try {
            $beforeStatus = null;
            DB::transaction(function () use ($id, &$beforeStatus, $request) {
                $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->first();
                if (!$po) abort(404);
                if (!in_array($po->status, ['partial', 'received'], true)) {
                    throw new \RuntimeException('Only partially or fully received purchase orders can be closed.');
                }
                if (!DB::table('po_receipts')->where('po_id', $id)->exists()) {
                    throw new \RuntimeException('Receive goods at least once before closing this purchase order.');
                }
                $beforeStatus = $po->status;
                DB::table('purchase_orders')->where('id', $id)->update(['status' => 'closed', 'updated_at' => now()]);
            });
            $audit->record('purchase_order_closed', 'purchase_order', $id, $request->user()?->id,
                ['status' => $beforeStatus], ['status' => 'closed']);
            $this->syncMaterialReadiness(DB::table('mrp_suggestions')->join('po_items', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')
                ->where('po_items.po_id', $id)->pluck('mrp_suggestions.cutsheet_id')->all());
            return back()->with('success', 'Purchase order closed. Receipt history and received stock were preserved.');
        } catch (\Throwable $e) {
            Log::warning('PO close rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $id)
    {
        $data = $this->validatePo($request);
        $supplier = DB::table('suppliers')->find($data['supplier_id']);
        if (!$supplier || !in_array($supplier->status, ['active'], true)) {
            return back()->with('error', 'Supplier is not active!')->withInput();
        }

        try {
            $cutsheetIds = DB::transaction(function () use ($id, $data) {
                $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->first();
                if (!$po) abort(404);
                $oldSuggestionIds = DB::table('po_items')->where('po_id', $id)
                    ->pluck('mrp_suggestion_id')->filter()->all();
                $existingItems = DB::table('po_items')->where('po_id', $id)->get()->keyBy('id');
                $hasReceipts = $this->hasReceipts((int) $id);
                if ($hasReceipts) {
                    $receiptLinkedIds = DB::table('po_receipt_items')->whereIn('po_item_id', $existingItems->keys())->pluck('po_item_id')->map(fn ($itemId) => (int) $itemId)->unique();
                    $submittedIds = collect($data['items'])->pluck('po_item_id')->filter()->map(fn ($itemId) => (int) $itemId);
                    foreach ($existingItems as $existing) {
                        if (($receiptLinkedIds->contains((int) $existing->id) || (float) $existing->received_qty > 0) && !$submittedIds->contains((int) $existing->id)) {
                            throw new \RuntimeException("Received PO item {$existing->material_code} cannot be removed.");
                        }
                    }
                    $totalAmount = 0;
                    $keptIds = [];
                    foreach ($data['items'] as $entry) {
                        $itemId = (int) ($entry['po_item_id'] ?? 0);
                        $existing = $existingItems->get($itemId);
                        if ($itemId && !$existing) throw new \RuntimeException('Invalid PO item submitted.');
                        $material = DB::table('materials')->find($entry['material_id']);
                        if (!$material) throw new \RuntimeException('Selected material no longer exists in Material Master.');
                        if ($existing && ($receiptLinkedIds->contains((int) $existing->id) || (float) $existing->received_qty > 0)) {
                            if ((int) $existing->material_id !== (int) $material->id || $existing->unit !== $entry['unit']) {
                                throw new \RuntimeException("Material and unit cannot be changed for received PO item {$existing->material_code}.");
                            }
                            if ((float) $entry['quantity'] < (float) $existing->received_qty) {
                                throw new \RuntimeException("Quantity for {$existing->material_code} cannot be less than its received quantity ({$existing->received_qty}).");
                            }
                        }
                        $lineTotal = (float) $entry['quantity'] * (float) ($entry['unit_price'] ?? 0);
                        $totalAmount += $lineTotal;
                        $row = [
                            'material_code' => $material->internal_code, 'material_name' => $material->material_name,
                            'unit' => $entry['unit'], 'quantity' => $entry['quantity'],
                            'unit_price' => $entry['unit_price'] ?? 0, 'total_price' => $lineTotal,
                            'notes' => $entry['notes'] ?? null, 'material_id' => $material->id,
                            'mrp_suggestion_id' => $entry['mrp_suggestion_id'] ?? null, 'updated_at' => now(),
                        ];
                        if ($existing) {
                            DB::table('po_items')->where('id', $itemId)->update($row);
                            $keptIds[] = $itemId;
                        } else {
                            $row += ['po_id' => $id, 'received_qty' => 0, 'expected_date' => null, 'status' => 'pending', 'created_at' => now()];
                            $keptIds[] = DB::table('po_items')->insertGetId($row);
                        }
                    }
                    DB::table('po_items')->where('po_id', $id)->whereNotIn('id', $keptIds)->delete();
                } else {
                    DB::table('po_items')->where('po_id', $id)->delete();
                    [$items, $totalAmount] = $this->preparePoItems($data['items'], (int) $id);
                    DB::table('po_items')->insert($items);
                }
                [$surcharges, $surchargeTotal] = $this->preparePoSurcharges($data['surcharges'] ?? []);
                foreach ($surcharges as &$surcharge) $surcharge['po_id'] = $id;
                unset($surcharge);
                DB::table('po_surcharges')->where('po_id', $id)->delete();
                if ($surcharges) DB::table('po_surcharges')->insert($surcharges);
                $totalAmount += $surchargeTotal;
                DB::table('purchase_orders')->where('id', $id)->update([
                    'supplier_id' => $data['supplier_id'], 'order_date' => $data['order_date'],
                    'expected_delivery' => $data['expected_delivery'] ?? null,
                    'currency' => $data['currency'],
                    'exchange_rate' => $data['exchange_rate'],
                    'vat_percent' => $data['vat_percent'] ?? 0,
                    'total_amount' => $totalAmount, 'notes' => $data['notes'] ?? null, 'updated_at' => now(),
                ]);

                $newSuggestionIds = collect($items)->pluck('mrp_suggestion_id')->filter()->all();
                foreach (array_diff($oldSuggestionIds, $newSuggestionIds) as $suggestionId) {
                    if (!DB::table('po_items')->join('purchase_orders', 'purchase_orders.id', '=', 'po_items.po_id')->where('po_items.mrp_suggestion_id', $suggestionId)->whereNull('purchase_orders.deleted_at')->exists()) {
                        DB::table('mrp_suggestions')->where('id', $suggestionId)
                            ->update(['status' => 'pending', 'updated_at' => now()]);
                    }
                }
                if ($newSuggestionIds) DB::table('mrp_suggestions')->whereIn('id', $newSuggestionIds)
                    ->update(['status' => 'ordered', 'updated_at' => now()]);

                return DB::table('mrp_suggestions')->whereIn('id', array_unique(array_merge($oldSuggestionIds, $newSuggestionIds)))
                    ->pluck('cutsheet_id')->all();
            });
            $this->syncMaterialReadiness($cutsheetIds);
            return redirect()->route('admin.procurement.show', $id)->with('success', 'PO updated successfully.');
        } catch (\Throwable $e) {
            Log::warning('PO update rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage())->withInput();
        }
    }

    public function destroy($id)
    {
        try {
            $cutsheetIds = DB::transaction(function () use ($id) {
                $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->first();
                if (!$po) abort(404);
                $suggestionIds = DB::table('po_items')->where('po_id', $id)
                    ->pluck('mrp_suggestion_id')->filter()->all();
                $cutsheetIds = DB::table('mrp_suggestions')->whereIn('id', $suggestionIds)
                    ->pluck('cutsheet_id')->all();
                DB::table('purchase_orders')->where('id', $id)->update(['deleted_at' => now(), 'updated_at' => now()]);
                foreach ($suggestionIds as $suggestionId) {
                    if (!DB::table('po_items')->join('purchase_orders', 'purchase_orders.id', '=', 'po_items.po_id')->where('po_items.mrp_suggestion_id', $suggestionId)->whereNull('purchase_orders.deleted_at')->exists()) {
                        DB::table('mrp_suggestions')->where('id', $suggestionId)
                            ->update(['status' => 'pending', 'updated_at' => now()]);
                    }
                }
                return $cutsheetIds;
            });
            $this->syncMaterialReadiness($cutsheetIds);
            return redirect()->route('admin.procurement.index')->with('success', 'PO deleted. Its receipt history and inventory records have been preserved.');
        } catch (\Throwable $e) {
            Log::warning('PO delete rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->with('error', $e->getMessage());
        }
    }

    private function validatePo(Request $request): array
    {
        $data = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id', 'order_date' => 'required|date',
            'expected_delivery' => 'nullable|date|after_or_equal:order_date', 'notes' => 'nullable|string',
            'currency' => 'required|in:VND,USD',
            'exchange_rate' => 'required|numeric|decimal:0,6|gt:0',
            'vat_percent' => 'nullable|numeric|decimal:0,2|between:0,100',
            'items' => 'required|array|min:1', 'items.*.po_item_id' => 'nullable|integer', 'items.*.material_code' => 'required|string|max:100',
            'items.*.material_name' => 'required|string|max:191', 'items.*.unit' => 'required|string|max:20',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'nullable|numeric|decimal:0,4|min:0',
            'items.*.notes' => 'nullable|string|max:1000',
            'items.*.mrp_suggestion_id' => 'nullable|exists:mrp_suggestions,id',
            'items.*.material_id' => 'nullable|exists:materials,id',
            'surcharges' => 'nullable|array',
            'surcharges.*.description' => 'required|string|max:191',
            'surcharges.*.quantity' => 'required|numeric|min:0|decimal:0,4',
            'surcharges.*.unit' => 'required|string|max:20',
            'surcharges.*.unit_price' => 'required|numeric|min:0|decimal:0,4',
        ]);
        foreach ($data['items'] as $index => $item) {
            $mapped = !empty($item['material_id']) && DB::table('material_vendors')
                ->where('vendor_id', $data['supplier_id'])->where('material_id', $item['material_id'])->exists();
            if (!$mapped) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "items.$index.material_id" => 'Material must be mapped to the selected supplier.',
                ]);
            }
        }
        return $data;
    }

    private function preparePoItems(array $requestItems, int $poId): array
    {
        $totalAmount = 0;
        $items = [];
        foreach ($requestItems as $item) {
            $material = DB::table('materials')->find($item['material_id']);
            if (!$material) throw new \RuntimeException('Selected material no longer exists in Material Master.');
            $totalPrice = (float) $item['quantity'] * (float) ($item['unit_price'] ?? 0);
            $totalAmount += $totalPrice;
            $items[] = [
                'po_id' => $poId, 'material_code' => $material->internal_code,
                'material_name' => $material->material_name, 'unit' => $material->unit,
                'quantity' => $item['quantity'], 'received_qty' => 0,
                'unit_price' => $item['unit_price'] ?? 0, 'total_price' => $totalPrice,
                'expected_date' => !empty($item['mrp_suggestion_id'])
                    ? DB::table('mrp_suggestions')->where('id', $item['mrp_suggestion_id'])->value('required_date')
                    : null,
                'notes' => $item['notes'] ?? null,
                'mrp_suggestion_id' => $item['mrp_suggestion_id'] ?? null,
                'material_id' => $material->id, 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        return [$items, $totalAmount];
    }

    private function preparePoSurcharges(array $requestRows): array
    {
        $total = 0;
        $rows = [];
        foreach ($requestRows as $row) {
            $lineTotal = (float) $row['quantity'] * (float) $row['unit_price'];
            $total += $lineTotal;
            $rows[] = [
                'description' => trim($row['description']), 'quantity' => $row['quantity'],
                'unit' => trim($row['unit']), 'unit_price' => $row['unit_price'], 'total_price' => $lineTotal,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }
        return [$rows, $total];
    }

    private function hasReceipts(int $poId): bool
    {
        return DB::table('po_receipts')->where('po_id', $poId)->exists()
            || DB::table('po_items')->where('po_id', $poId)->where('received_qty', '>', 0)->exists();
    }

    private function vendorMaterials(array $supplierIds)
    {
        return DB::table('material_vendors')
            ->join('materials', 'material_vendors.material_id', '=', 'materials.id')
            ->whereIn('material_vendors.vendor_id', $supplierIds)
            ->select(
                'material_vendors.vendor_id', 'material_vendors.material_id', 'material_vendors.unit_price',
                'material_vendors.vendor_item_code', 'materials.internal_code', 'materials.material_name',
                'materials.unit', 'materials.color', 'materials.size'
            )->orderBy('materials.internal_code')->get();
    }

    public function receive(Request $request, $id, AuditTrailService $audit)
    {
        $data = $request->validate([
            'received_date' => 'required|date', 'reference_number' => 'nullable|string|max:191', 'notes' => 'nullable|string',
            'customs_declaration_date' => 'nullable|date', 'customs_declaration_number' => 'nullable|string|max:100',
            'contract_number' => 'nullable|string|max:100',
            'warehouse_id' => 'required|exists:warehouses,id', 'location_id' => 'required|exists:locations,id',
            'items' => 'required|array|min:1', 'items.*.po_item_id' => 'required|exists:po_items,id',
            'items.*.quantity' => 'required|numeric|decimal:0,4|gt:0',
            'items.*.customs_material_code' => 'nullable|string|max:100',
            'items.*.customs_unit_price' => 'nullable|numeric|decimal:0,4|min:0',
            'items.*.lot_no' => 'required_without:items.*.lot_roll_no|nullable|string|max:40',
            'items.*.roll_no' => 'required_without:items.*.lot_roll_no|nullable|string|max:40',
            'items.*.lot_roll_no' => 'nullable|string|max:100',
            'items.*.material_size' => 'required|string|max:100',
        ]);
        try {
            DB::transaction(function () use ($id, $data) {
                $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->lockForUpdate()->first();
                if (!$po || !in_array($po->status, ['confirmed', 'partial'], true)) {
                    throw new \RuntimeException('Only confirmed or partially received POs can receive goods.');
                }
                if (!empty($data['location_id']) && !DB::table('locations')->where('id', $data['location_id'])->where('warehouse_id', $data['warehouse_id'])->exists()) {
                    throw new \RuntimeException('The selected location does not belong to the selected warehouse.');
                }
                $this->receiveToInventory($id, $data);
            });
            $this->syncMaterialReadiness(DB::table('mrp_suggestions')->join('po_items', 'po_items.mrp_suggestion_id', '=', 'mrp_suggestions.id')
                ->where('po_items.po_id', $id)->pluck('mrp_suggestions.cutsheet_id')->all());
            $audit->record('goods_received', 'purchase_order', (int) $id, $request->user()?->id, [], [
                'items' => $data['items'], 'received_date' => $data['received_date'],
                'customs_declaration_date' => $data['customs_declaration_date'] ?? null,
                'customs_declaration_number' => $data['customs_declaration_number'] ?? null,
                'contract_number' => $data['contract_number'] ?? null,
            ], $data['reference_number'] ?? null);
            return back()->with('success', 'Goods receipt posted to inventory.');
        } catch (\Throwable $e) {
            Log::warning('PO receipt rejected', ['po_id' => $id, 'message' => $e->getMessage()]);
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function importReceiptRows(Request $request, int $id)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $po = DB::table('purchase_orders')->whereNull('deleted_at')->where('id', $id)->firstOrFail();
        if (!in_array($po->status, ['confirmed', 'partial'], true)) {
            return response()->json(['message' => 'Only confirmed or partially received POs can receive goods.'], 422);
        }

        try {
            $sheetRows = IOFactory::load($request->file('file')->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Could not read this spreadsheet. Save it as XLSX or CSV and try again.'], 422);
        }

        $normalize = static fn ($value) => preg_replace('/[^a-z0-9]+/', '', strtolower(Str::ascii(trim((string) $value))));
        $aliases = [
            'material_code' => ['materialcode', 'itemcode', 'code', 'itemno', 'partno', 'mavattu'],
            'quantity' => ['quantity', 'qty', 'receiveqty', 'receivedqty', 'actualqty', 'soluong', 'sl'],
            'material_size' => ['size', 'materialsize', 'sizename', 'kichthuoc'],
            'lot_no' => ['lot', 'lotno', 'lotnumber', 'batch', 'batchno'],
            'roll_no' => ['roll', 'rollno', 'rollnumber'],
            'lot_roll_no' => ['lotroll', 'lotrollno', 'batchroll', 'batchrollno'],
        ];
        $headerIndex = null;
        $columns = [];
        foreach (array_slice($sheetRows, 0, 10, true) as $rowIndex => $row) {
            $candidate = [];
            foreach (array_map($normalize, $row) as $columnIndex => $heading) {
                foreach ($aliases as $field => $names) {
                    if (in_array($heading, $names, true)) $candidate[$field] = $columnIndex;
                }
            }
            if (isset($candidate['material_code'], $candidate['quantity'])) {
                $headerIndex = $rowIndex;
                $columns = $candidate;
                break;
            }
        }
        if ($headerIndex === null) {
            return response()->json(['message' => 'Could not find a header row. Include Material Code (or Code) and Quantity (or Qty).'], 422);
        }

        $poItems = DB::table('po_items')->leftJoin('materials', 'po_items.material_id', '=', 'materials.id')
            ->where('po_items.po_id', $id)->select('po_items.id', 'po_items.material_code', 'po_items.quantity', 'po_items.received_qty', 'materials.size as material_size')->orderBy('po_items.id')->get();
        $itemsByCode = $poItems->groupBy(fn ($item) => strtolower(trim($item->material_code)));
        $imported = [];
        $errors = [];
        foreach (array_slice($sheetRows, $headerIndex + 1, 2000, true) as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $code = trim((string) ($row[$columns['material_code']] ?? ''));
            $rawQty = trim((string) ($row[$columns['quantity']] ?? ''));
            if ($code === '' && $rawQty === '') continue;
            $normalizedQty = str_replace([',', ' '], '', $rawQty);
            if ($code === '') { $errors[] = "Row {$excelRow}: material code is missing."; continue; }
            if (!is_numeric($normalizedQty) || (float) $normalizedQty <= 0) { $errors[] = "Row {$excelRow}: quantity must be a number greater than zero."; continue; }
            $matches = $itemsByCode->get(strtolower($code));
            if (!$matches || $matches->isEmpty()) { $errors[] = "Row {$excelRow}: material code {$code} is not on this PO."; continue; }
            $item = $matches->first(fn ($candidate) => (float) $candidate->received_qty < (float) $candidate->quantity) ?? $matches->first();
            $lot = trim((string) ($row[$columns['lot_no'] ?? -1] ?? ''));
            $roll = trim((string) ($row[$columns['roll_no'] ?? -1] ?? ''));
            $lotRoll = trim((string) ($row[$columns['lot_roll_no'] ?? -1] ?? ''));
            if ($lotRoll !== '' && ($lot === '' || $roll === '')) {
                $parts = preg_split('/\s*[\/-]\s*/', $lotRoll, 2);
                $lot = $lot ?: ($parts[0] ?? '');
                $roll = $roll ?: ($parts[1] ?? '');
            }
            $imported[] = [
                'po_item_id' => $item->id, 'material_size' => trim((string) ($row[$columns['material_size'] ?? -1] ?? '')) ?: ($item->material_size ?: '-'),
                'lot_no' => $lot ?: '-', 'roll_no' => $roll ?: '-', 'quantity' => (float) $normalizedQty,
            ];
        }
        return response()->json(['rows' => $imported, 'errors' => $errors, 'truncated' => count($sheetRows) > $headerIndex + 2001]);
    }

    /**
     * Receive items to inventory without overriding PO status
     */
    private function receiveToInventory($poId, array $meta)
    {
        $po = DB::table('purchase_orders')->where('id', $poId)->lockForUpdate()->first();
        $exchangeRate = (float) ($po->exchange_rate ?? 1);
        if (strtoupper((string) ($po->currency ?? 'USD')) === 'VND' && $exchangeRate <= 0) {
            throw new \RuntimeException('A valid VND to USD exchange rate is required before receiving this PO.');
        }
        $vatMultiplier = 1 + (float) ($po->vat_percent ?? 0) / 100;
        $items = DB::table('po_items')->where('po_id', $poId)->lockForUpdate()->get()->keyBy('id');
        $entries = collect($meta['items']);
        foreach ($entries->groupBy('po_item_id') as $itemId => $rows) {
            $item = $items->get($itemId);
            if (!$item) throw new \RuntimeException('A receipt line does not belong to this PO.');
            $newQuantity = $rows->sum(fn ($row) => (float) $row['quantity']);
            if ((float) $item->received_qty + $newQuantity > (float) $item->quantity + 0.0001) {
                throw new \RuntimeException("Receipt quantity exceeds the pending quantity for {$item->material_code}.");
            }
        }
        $receiptNo = 'RCP-' . now()->format('YmdHisv') . '-' . Str::upper(Str::random(6));
        $receiptId = DB::table('po_receipts')->insertGetId([
            'receipt_number' => $receiptNo, 'po_id' => $poId, 'received_date' => $meta['received_date'],
            'reference_number' => $meta['reference_number'] ?? null, 'notes' => $meta['notes'] ?? null,
            'customs_declaration_date' => $meta['customs_declaration_date'] ?? null,
            'customs_declaration_number' => $meta['customs_declaration_number'] ?? null,
            'contract_number' => $meta['contract_number'] ?? null,
            'created_by' => request()->user()->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($entries as $entry) {
            $item = $items[$entry['po_item_id']];
            $material = $item->material_id ? DB::table('materials')->find($item->material_id)
                : DB::table('materials')->where('internal_code', $item->material_code)->first();
            if (!$material) throw new \RuntimeException("Material {$item->material_code} was not found in Material Master.");
            $qty = (float) $entry['quantity'];
            // Inventory costs are stored in USD: add VAT, then convert VND using
            // the PO rate expressed as VND per USD.
            $unitCostUsd = (float) $item->unit_price * $vatMultiplier;
            if (strtoupper((string) ($po->currency ?? 'USD')) === 'VND') {
                $unitCostUsd /= $exchangeRate;
            }
            $lot = isset($entry['lot_no']) ? trim($entry['lot_no']) : null;
            $roll = isset($entry['roll_no']) ? trim($entry['roll_no']) : null;
            $label = $lot !== null && $roll !== null ? $lot . ' / ' . $roll : ($entry['lot_roll_no'] ?? null);
            $color = $material->color;
            $size = trim($entry['material_size']);
            DB::table('po_receipt_items')->insert([
                'po_receipt_id' => $receiptId, 'po_item_id' => $item->id, 'material_id' => $material->id,
                'material_code' => $material->internal_code, 'material_color' => $color, 'material_size' => $size,
                'quantity_received' => $qty, 'batch_no' => $label, 'lot_no' => $lot, 'roll_no' => $roll,
                'customs_material_code' => $entry['customs_material_code'] ?? null,
                'customs_unit_price' => $entry['customs_unit_price'] ?? null,
                'warehouse_id' => $meta['warehouse_id'], 'location_id' => $meta['location_id'],
                'created_at' => now(), 'updated_at' => now(),
            ]);
            app(InventoryLedgerService::class)->receive([
                'reference_type' => 'PO_RECEIPT', 'reference_id' => $receiptId, 'reference_doc' => $receiptNo,
                'transaction_date' => $meta['received_date'],
                'material_id' => $material->id, 'material_code' => $material->internal_code, 'color' => $color, 'size' => $size,
                'quantity' => $qty, 'unit' => $item->unit, 'warehouse_id' => $meta['warehouse_id'],
                'location_id' => $meta['location_id'], 'lot_roll_no' => $label, 'lot_no' => $lot, 'roll_no' => $roll,
                'unit_cost' => round($unitCostUsd, 4), 'notes' => "Received from PO #{$po->po_number}",
                'user_id' => request()->user()->id,
            ]);
            $item->received_qty = round($item->received_qty + $qty, 4);
            DB::table('po_items')->where('id', $item->id)->update([
                'material_id' => $material->id, 'received_qty' => $item->received_qty,
                'status' => $item->received_qty >= $item->quantity ? 'received' : 'partial', 'updated_at' => now(),
            ]);
            if ($item->mrp_suggestion_id && $item->received_qty >= $item->quantity) {
                DB::table('mrp_suggestions')->where('id', $item->mrp_suggestion_id)->update(['status' => 'received', 'updated_at' => now()]);
            }
        }
        $status = DB::table('po_items')->where('po_id', $poId)->where('status', '!=', 'received')->exists() ? 'partial' : 'received';
        DB::table('purchase_orders')->where('id', $poId)->update(['status' => $status, 'updated_at' => now()]);
    }
}
