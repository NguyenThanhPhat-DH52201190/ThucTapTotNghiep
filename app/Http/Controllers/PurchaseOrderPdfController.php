<?php

namespace App\Http\Controllers;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseOrderPdfController extends Controller
{
    public function export(Request $request, int $id)
    {
        $po = DB::table('purchase_orders')->find($id);
        abort_unless($po, 404);
        $settings = $request->validate([
            'reference' => 'nullable|string|max:500', 'shipping_mark' => 'nullable|string|max:500',
            'buyer' => 'nullable|string|max:3000', 'consignee' => 'nullable|string|max:3000',
            'payment_details' => 'nullable|string|max:1500',
            'freight_terms' => 'nullable|string|max:1000', 'shipment_date' => 'nullable|string|max:500',
            'revised' => 'nullable|boolean',
        ]);
        $settings['revised'] = $request->boolean('revised');
        $supplier = DB::table('suppliers')->find($po->supplier_id);
        $items = DB::table('po_items')
            ->leftJoin('materials', 'materials.id', '=', 'po_items.material_id')
            ->leftJoin('material_vendors', function ($join) use ($po) {
                $join->on('material_vendors.material_id', '=', 'po_items.material_id')
                    ->where('material_vendors.vendor_id', '=', $po->supplier_id);
            })
            ->where('po_items.po_id', $id)
            ->select('po_items.*', 'materials.color as master_color', 'materials.size as master_size',
                'material_vendors.vendor_item_code', 'material_vendors.supplier_description',
                'material_vendors.supplier_color_code')
            ->orderBy('po_items.id')->get();
        $surcharges = DB::table('po_surcharges')->where('po_id', $id)->orderBy('id')->get();
        $logoPath = public_path('images/global-safewear-logo.jpg');
        $logoDataUri = is_file($logoPath)
            ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($logoPath))
            : '';
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', false);
        $options->set('isJavascriptEnabled', false);
        $pdf = new Dompdf($options);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('admin.procurement.pdf', compact('po', 'supplier', 'items', 'surcharges', 'settings', 'logoDataUri'))->render(), 'UTF-8');
        $pdf->render();
        $bytes = $pdf->output();
        // Save only after a successful render so the user can export again with the same details.
        DB::table('purchase_orders')->where('id', $id)->update(['pdf_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE)]);
        $filename = (Str::slug($po->po_number) ?: 'purchase-order-' . $id) . '.pdf';
        return response($bytes, 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
