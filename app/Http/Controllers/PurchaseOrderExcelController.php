<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PurchaseOrderExcelController extends Controller
{
    public function export(Request $request, int $id)
    {
        $po = DB::table('purchase_orders')->whereNull('deleted_at')->find($id);
        abort_unless($po, 404);

        $settings = $request->validate([
            'reference' => 'nullable|string|max:500',
            'shipping_mark' => 'nullable|string|max:500',
            'buyer' => 'nullable|string|max:3000',
            'consignee' => 'nullable|string|max:3000',
            'payment_details' => 'nullable|string|max:1500',
            'freight_terms' => 'nullable|string|max:1000',
            'shipment_date' => 'nullable|string|max:500',
            'revised' => 'nullable|boolean',
        ]);
        $settings['revised'] = $request->boolean('revised');
        DB::table('purchase_orders')->where('id', $id)->update([
            'pdf_settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ]);

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

        $currency = strtoupper(trim($po->currency ?: 'USD'));
        $showVatColumns = $currency === 'VND';
        $vatRate = (float) ($po->vat_percent ?? 0);
        $vatFactor = $vatRate / 100;
        $settings = array_merge([
            'reference' => '',
            'shipping_mark' => $po->po_number,
            'buyer' => "GLOBAL SAFEWEAR SINGAPORE PTE LTD\n72 Anson Road\n#07-04 Anson House\nSINGAPORE 079911",
            'consignee' => "Global Safewear Viet Nam Company Ltd.\nUnit C1, C2, Lot G6-G7-G8-G9, Street N3, Dong Nam Industrial Park,\nBinh My Commune, Ho Chi Minh City, Vietnam.\nPostcode: 71611",
            'payment_details' => $supplier?->payment_terms ?? '',
            'freight_terms' => '',
            'shipment_date' => $po->expected_delivery ? date('d/m/Y', strtotime($po->expected_delivery)) : '',
            'revised' => false,
        ], $settings);
        $headers = ['No.', 'Supplier Item Code', 'Description', 'Note', 'Color Code', 'Color', 'GSV Code', 'Qty', 'UOM', 'Size', 'Unit Price (' . $currency . ')', 'Amount (' . $currency . ')'];
        if ($showVatColumns) {
            $headers[] = 'VAT Rate';
            $headers[] = 'VAT Amount (' . $currency . ')';
        }
        $headers[] = 'Total Amount (' . $currency . ')';
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Purchase Order');
        $sheet->mergeCells('A1:' . $lastColumn . '1');
        $this->setText($sheet, 'A1', 'PURCHASE ORDER');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $this->setText($sheet, 'A2', 'PO No.:');
        $this->setText($sheet, 'B2', (string) $po->po_number);
        $this->setText($sheet, 'J2', 'Date:');
        $this->setText($sheet, 'K2', $po->order_date ? date('d/m/Y', strtotime($po->order_date)) : '-');
        $this->setText($sheet, 'A3', 'Order to:');
        $this->setText($sheet, 'B3', (string) ($supplier?->name ?? '-'));
        $sheet->getStyle('B3')->getFont()->setBold(true);
        $sheet->mergeCells('B3:' . $lastColumn . '3');
        $metaRow = 4;
        foreach (preg_split('/\r\n|\r|\n/', (string) ($supplier?->address ?? '')) as $addressLine) {
            if ($addressLine === '') continue;
            $this->setText($sheet, 'B' . $metaRow, $addressLine);
            $sheet->mergeCells('B' . $metaRow . ':' . $lastColumn . $metaRow);
            $metaRow++;
        }
        foreach ([
            'Att: ' => $supplier?->contact_person,
            'Tel: ' => $supplier?->phone,
            'Email: ' => $supplier?->email,
        ] as $prefix => $contactValue) {
            if (!$contactValue) continue;
            $this->setText($sheet, 'B' . $metaRow, $prefix . $contactValue);
            $sheet->mergeCells('B' . $metaRow . ':' . $lastColumn . $metaRow);
            $metaRow++;
        }
        if (!empty($settings['revised'])) {
            $this->setText($sheet, $lastColumn . '2', 'REVISED');
            $sheet->getStyle($lastColumn . '2')->getFont()->setBold(true)->getColor()->setRGB('ED0000');
        }
        $metaRow++;
        $this->setText($sheet, 'A' . $metaRow, 'Ref.');
        $this->setText($sheet, 'B' . $metaRow, (string) ($settings['reference'] ?? ''));
        $sheet->mergeCells('B' . $metaRow . ':' . $lastColumn . $metaRow);

        $headerRow = $metaRow + 2;
        foreach ($headers as $index => $header) {
            $column = Coordinate::stringFromColumnIndex($index + 1);
            $this->setText($sheet, $column . $headerRow, $header);
        }
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $headerRow)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'A6A6A6']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(32);

        $row = $headerRow + 1;
        $lineNumber = 0;
        $writeLine = function (array $line, float $quantity, float $unitPrice, string $unit, ?float $lineVat) use ($sheet, &$row, &$lineNumber, $showVatColumns, $vatRate): void {
            $amount = $quantity * $unitPrice;
            $lineNumber++;
            $values = [
                $lineNumber,
                $line['vendor_item_code'] ?? '',
                $line['description'] ?? '',
                $line['notes'] ?? '',
                $line['color_code'] ?? '',
                $line['color'] ?? '',
                $line['code'] ?? '',
                $quantity,
                $unit,
                $line['size'] ?? '',
                $unitPrice,
                $amount,
            ];
            if ($showVatColumns) {
                $values[] = $vatRate > 0 ? $vatRate / 100 : '-';
                $values[] = $vatRate > 0 ? ($lineVat ?? 0) : '-';
            }
            $values[] = $amount + ($lineVat ?? 0);

            foreach ($values as $index => $value) {
                $cell = Coordinate::stringFromColumnIndex($index + 1) . $row;
                if (is_int($value) || is_float($value)) {
                    $sheet->setCellValue($cell, $value);
                } else {
                    $this->setText($sheet, $cell, (string) $value);
                }
            }
            $row++;
        };

        foreach ($items as $item) {
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $amount = $quantity * $unitPrice;
            $writeLine([
                'vendor_item_code' => $item->vendor_item_code,
                'description' => $item->supplier_description ?: $item->material_name,
                'notes' => $item->notes,
                'color_code' => $item->supplier_color_code,
                'color' => $item->master_color,
                'code' => $item->material_code,
                'size' => $item->master_size,
            ], $quantity, $unitPrice, (string) $item->unit, $amount * $vatFactor);
        }
        foreach ($surcharges as $surcharge) {
            $quantity = (float) $surcharge->quantity;
            $unitPrice = (float) $surcharge->unit_price;
            $amount = $quantity * $unitPrice;
            $writeLine([
                'vendor_item_code' => '',
                'description' => $surcharge->description,
                'notes' => '',
                'color_code' => '',
                'color' => '',
                'code' => 'Surcharge',
                'size' => '',
            ], $quantity, $unitPrice, (string) $surcharge->unit, $amount * $vatFactor);
        }

        $totalRow = $row;
        $this->setText($sheet, 'A' . $totalRow, 'TOTAL');
        $sheet->mergeCells('A' . $totalRow . ':' . Coordinate::stringFromColumnIndex(11) . $totalRow);
        $sheet->getStyle('A' . $totalRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->setCellValue(Coordinate::stringFromColumnIndex(12) . $totalRow, (float) $po->total_amount);
        $totalColumn = count($headers);
        if ($showVatColumns) {
            $vatTotalCell = Coordinate::stringFromColumnIndex(14) . $totalRow;
            if ($vatRate > 0) {
                $sheet->setCellValue($vatTotalCell, (float) $po->total_amount * $vatFactor);
            } else {
                $this->setText($sheet, $vatTotalCell, '-');
            }
        }
        $sheet->setCellValue(Coordinate::stringFromColumnIndex($totalColumn) . $totalRow, (float) $po->total_amount * (1 + $vatFactor));
        $sheet->getStyle('A' . $totalRow . ':' . $lastColumn . $totalRow)->getFont()->setBold(true);
        $sheet->getStyle('A' . $headerRow . ':' . $lastColumn . $totalRow)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E2F3');

        $sheet->getStyle('H' . ($headerRow + 1) . ':H' . ($totalRow - 1))->getNumberFormat()->setFormatCode('#,##0.####');
        $sheet->getStyle('K' . ($headerRow + 1) . ':K' . ($totalRow - 1))->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet->getStyle('L' . ($headerRow + 1) . ':L' . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($lastColumn . ($headerRow + 1) . ':' . $lastColumn . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
        if ($showVatColumns) {
            $sheet->getStyle('M' . ($headerRow + 1) . ':M' . ($totalRow - 1))->getNumberFormat()->setFormatCode('0.00%');
            $sheet->getStyle('N' . ($headerRow + 1) . ':N' . $totalRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $detailRow = $totalRow + 2;
        $details = [
            'SHIPPING MARK' => $settings['shipping_mark'] ?? '',
        ];
        if (!empty($settings['freight_terms'])) $details['FREIGHT TERMS'] = $settings['freight_terms'];
        $details += [
            'BUYER' => $settings['buyer'] ?? '',
            'CONSIGNEE' => $settings['consignee'] ?? '',
            'PAYMENT DETAILS' => $settings['payment_details'] ?? '',
            'SHIPMENT DATE' => $settings['shipment_date'] ?? '',
        ];
        if ($po->notes) $details['NOTES'] = $po->notes;
        foreach ($details as $label => $value) {
            $this->setText($sheet, 'A' . $detailRow, $label);
            $sheet->getStyle('A' . $detailRow)->getFont()->setBold(true);
            $this->setText($sheet, 'B' . $detailRow, (string) $value);
            $sheet->mergeCells('B' . $detailRow . ':' . $lastColumn . $detailRow);
            $sheet->getStyle('B' . $detailRow)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
            $lineCount = max(1, substr_count((string) $value, "\n") + 1);
            $sheet->getRowDimension($detailRow)->setRowHeight(max(18, 15 * $lineCount));
            $detailRow++;
        }
        foreach (range(1, count($headers)) as $index) {
            $column = Coordinate::stringFromColumnIndex($index);
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        foreach (['B', 'C', 'D', 'E', 'F', 'G'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(false);
            $sheet->getColumnDimension($column)->setWidth($column === 'C' ? 36 : 20);
        }
        $sheet->freezePane('A' . ($headerRow + 1));
        if ($row > $headerRow + 1) {
            $sheet->setAutoFilter('A' . $headerRow . ':' . $lastColumn . ($row - 1));
        }

        $filename = (Str::slug($po->po_number) ?: 'purchase-order-' . $id) . '.xlsx';
        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function setText($sheet, string $cell, string $value): void
    {
        $sheet->setCellValueExplicit($cell, $value, DataType::TYPE_STRING);
    }
}
