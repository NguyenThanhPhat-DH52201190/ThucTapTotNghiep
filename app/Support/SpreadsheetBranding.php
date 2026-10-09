<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class SpreadsheetBranding
{
    public static function addCompanyHeader($sheet, string $lastColumn, int $logoHeight = 100): void
    {
        $sheet->insertNewRowBefore(1, 8);

        $logo = new Drawing();
        $logo->setName('Global Safewear');
        $logo->setDescription('Global Safewear logo');
        $logo->setPath(public_path('images/global-safewear-logo.jpg'));
        $logo->setHeight($logoHeight);
        $logo->setCoordinates('A1');
        $logo->setWorksheet($sheet);

        foreach (range(1, 6) as $row) {
            $sheet->getRowDimension($row)->setRowHeight(22);
        }

        foreach ([
            '72 Anson Road',
            '#07-04, Anson House, SINGAPORE 079911',
            'www.globalsafewear.com',
        ] as $index => $addressLine) {
            $row = $index + 5;
            $sheet->setCellValue('A' . $row, $addressLine);
            $sheet->mergeCells('A' . $row . ':' . $lastColumn . $row);
            $sheet->getRowDimension($row)->setRowHeight(20);
        }

        $sheet->getRowDimension(8)->setRowHeight(8);
    }
}
