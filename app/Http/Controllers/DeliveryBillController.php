<?php

namespace App\Http\Controllers;

use App\Services\DeliveryBillService;
use App\Services\OrderMaterialRequirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeliveryBillController extends Controller
{
    public function index(int $id, OrderMaterialRequirementService $requirements, DeliveryBillService $service)
    {
        $order = DB::table('ocs')->find($id);
        abort_unless($order, 404);
        $needs = $order->bom_header_id ? $requirements->sync($id) : collect();
        $remaining = $service->remaining($id, $needs);
        $balances = DB::table('inventory_balances as balance')->leftJoin('warehouses', 'warehouses.id', '=', 'balance.warehouse_id')
            ->whereIn('balance.material_id', $needs->pluck('material_id')->filter())->where('balance.balance_qty', '>', 0)
            ->select('balance.*', 'warehouses.name as warehouse_name')->orderBy('balance.id')->get();
        $options = $needs->map(fn ($need) => [
            'id' => $need->bom_item_id, 'label' => $need->material_code.' — '.$need->material_name.' / '.$need->material_color.' / '.$need->material_size.' ('.$need->unit.')',
            'remaining' => $remaining[$service->key($need)] ?? 0,
            'balances' => $balances->filter(fn ($balance) => $service->matches($balance, $need))->map(fn ($b) => [
                'id' => $b->id, 'label' => ($b->warehouse_name ?: 'No warehouse').' / '.($b->location ?: 'No location').' / Lot: '.($b->lot_no ?: $b->lot_roll_no ?: '-').' / Roll: '.($b->roll_no ?: '-').' / On hand: '.$b->balance_qty.' / Reserved: '.$b->reserved_qty,
            ])->values(),
        ])->values();
        $bills = DB::table('delivery_bills')->where('cutsheet_id', $id)->orderByDesc('id')->paginate(20);
        return view('admin.norm.delivery-bills', compact('order', 'options', 'bills'));
    }

    public function store(Request $request, int $id, DeliveryBillService $service)
    {
        $data = $request->validate([
            'submission_key' => 'required|uuid', 'number' => 'required|string|max:50',
            'customer' => 'required|string|max:191', 'address' => 'required|string|max:500',
            'reason' => 'required|string|max:1000', 'shipper' => 'required|string|max:191', 'shipper_address' => 'nullable|string|max:500',
            'priority_reason' => 'nullable|string|max:1000', 'items' => 'required|array|list|min:1|max:100',
            'items.*.bom_item_id' => 'required|integer', 'items.*.balance_id' => 'required|integer',
            'items.*.quantity' => 'required|numeric|gt:0|max:99999999|decimal:0,4',
        ]);
        try {
            $bill = $service->confirm($id, $data, $request->user()->id);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Delivery bill confirmation failed', ['cutsheet_id' => $id, 'exception' => $e]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'The delivery bill could not be confirmed. Please retry with this form or check saved bills before creating another issue.'], 500);
            }
            return back()->withInput()->with('error', 'The delivery bill could not be confirmed. No new stock issue was committed. Please reload and try again.');
        }
        return $this->file($bill);
    }

    public function download(int $id, int $bill)
    {
        $bill = DB::table('delivery_bills')->where('cutsheet_id', $id)->where('id', $bill)->first();
        abort_unless($bill, 404);
        return $this->file($bill);
    }

    private function file(object $bill)
    {
        abort_unless(Storage::disk('local')->exists($bill->file_path), 404, 'Stored delivery bill file is missing. Restore it from backup; do not issue again.');
        return Storage::disk('local')->download($bill->file_path, 'Delivery-Bill-'.$bill->id.'.xlsx', ['Cache-Control' => 'private, no-store']);
    }
}
