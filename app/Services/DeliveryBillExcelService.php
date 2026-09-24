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
            $count = max(10, count($lines));
            if ($count > 10) $sheet->insertNewRowBefore(21, $count - 10);
            // Explicit strings prevent user-entered codes/text from becoming Excel formulas.
            $text = fn ($cell, $value) => $sheet->setCellValueExplicit($cell, (string) $value, DataType::TYPE_STRING);
            $text('A1', 'CÔNG TY TNHH GLOBAL SAFEWEAR VIỆT NAM');
            $text('A2', 'Địa chỉ :Nhà xưởng C-1 và C-2, Lô G6, G7, G8, G9,');
            $text('A3', 'Đường N3, KCN Đông Nam, Xã Bình Mỹ, H. Củ Chi, Tp HCM');
            $text('E2', $header['number']);
            $sheet->setCellValue('C5', Date::PHPToExcel(new \DateTimeImmutable($date)));
            $sheet->getStyle('C5')->getNumberFormat()->setFormatCode('d/m/yyyy');
            foreach (['B6:G6', 'A7:G7', 'B8:G8', 'B9:C9', 'E9:G9'] as $range) $sheet->mergeCells($range);
            $text('B6', $header['customer']);
            $text('A7', 'Address: '.$header['address']);
            $text('B8', $header['reason']);
            $text('B9', $header['shipper']);
            $text('E9', $header['shipper_address'] ?? '');
            $sheet->getStyle('A6:G9')->getAlignment()->setWrapText(true);
            foreach ([6, 7, 8, 9] as $row) $sheet->getRowDimension($row)->setRowHeight(-1);
            for ($i = 0; $i < $count; $i++) {
                $row = 11 + $i;
                $sheet->duplicateStyle($sheet->getStyle('A11:G11'), "A$row:G$row");
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
            $sheet->getPageSetup()->setPrintArea('A1:G'.($count + 14))->setFitToWidth(1)->setFitToHeight(0);
            $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(10, 10);
            Storage::disk('local')->makeDirectory('delivery-bills');
            (new Xlsx($book))->save(Storage::disk('local')->path($path));
            if (!Storage::disk('local')->exists($path) || !Storage::disk('local')->size($path)) throw new \RuntimeException('Unable to create delivery bill file.');
        } finally {
            $book->disconnectWorksheets();
        }
    }
}
