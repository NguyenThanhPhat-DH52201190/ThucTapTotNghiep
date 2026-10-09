<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DeliveryBillExcelService
{
    public function write(array $header, array $lines, string $date, string $path): void
    {
        $book = IOFactory::load(resource_path('templates/delivery-bill.xlsx'));
        try {
            $sheet = $book->getSheetByName('DELI');
            \App\Support\SpreadsheetBranding::addCompanyHeader($sheet, 'G');
            $count = max(10, count($lines));
            if ($count > 10) $sheet->insertNewRowBefore(29, $count - 10);
            // Explicit strings prevent user-entered codes/text from becoming Excel formulas.
            $text = fn ($cell, $value) => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            $text('A9', 'CÔNG TY TNHH GLOBAL SAFEWEAR VIỆT NAM');
            $text('A10', 'Địa chỉ :Nhà xưởng C-1 và C-2, Lô G6, G7, G8, G9,');
            $text('A11', 'Đường N3, KCN Đông Nam, Xã Bình Mỹ, H. Củ Chi, Tp HCM');
            $text('E10', $header['number']);
            $sheet->setCellValue('C13', Date::PHPToExcel(new \DateTimeImmutable($date)));
            $sheet->getStyle('C13')->getNumberFormat()->setFormatCode('d/m/yyyy');
            foreach (['B14:G14', 'A15:G15', 'B16:G16', 'B17:C17', 'E17:G17'] as $range) $sheet->mergeCells($range);
            $text('B14', $header['customer']);
            $text('A15', 'Address: '.$header['address']);
            $text('B16', $header['reason']);
            $text('B17', $header['shipper']);
            $text('E17', $header['shipper_address'] ?? '');
            $sheet->getStyle('A14:G17')->getAlignment()->setWrapText(true);
            foreach ([14, 15, 16, 17] as $row) $sheet->getRowDimension($row)->setRowHeight(-1);
            for ($i = 0; $i < $count; $i++) {
                $row = 19 + $i;
                $sheet->duplicateStyle($sheet->getStyle('A19:G19'), "A$row:G$row");
                foreach (range('A', 'G') as $column) $sheet->setCellValue($column.$row, null);
                if (!isset($lines[$i])) continue;
                $line = $lines[$i];
                $sheet->setCellValue('A'.$row, $i + 1);
                foreach (['B' => 'code', 'C' => 'description', 'D' => 'colour', 'E' => 'size', 'F' => 'unit'] as $column => $key) $text($column.$row, $line[$key] ?? '');
                $sheet->setCellValue('G'.$row, (float) $line['quantity']);
                $sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode('#,##0.####');
                $sheet->getStyle("B$row:G$row")->getAlignment()->setWrapText(true);
                $sheet->getRowDimension($row)->setRowHeight(-1);
            }
            $sheet->getPageSetup()->setPrintArea('A1:G'.($count + 22))->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(18, 18);
            Storage::disk('local')->makeDirectory('delivery-bills');
            (new Xlsx($book))->save(Storage::disk('local')->path($path));
            if (!Storage::disk('local')->exists($path) || !Storage::disk('local')->size($path)) throw new \RuntimeException('Unable to create delivery bill file.');
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
