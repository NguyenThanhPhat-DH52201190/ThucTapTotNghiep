<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class OCSImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        foreach ($rows as $row) {

            // Parse the date in a consistent format
            $date = null;

            if (!empty($row['csdate'])) {
                try {
                    if (is_numeric($row['csdate'])) {
                        // Numeric Excel date value (for example, 46132)
                        $date = Date::excelToDateTimeObject($row['csdate'])->format('Y-m-d');
                    } else {
                        // Text-based date value
                        $date = Carbon::parse($row['csdate'])->format('Y-m-d');
                    }
                } catch (\Exception $e) {
                    $date = null; // Ignore invalid values
                }
            }

            DB::table('ocs')->updateOrInsert(
                ['CS' => $row['cs']],
                [
                    'ONum' => $row['onum'],
                    'SNo' => $row['sno'],
                    'Sname' => $row['sname'],
                    'Customer' => $row['customer'],
                    'CsDate' => $date,
                    'CMT' => $row['cmt'] ?? 0,
                    'Color' => $row['color'] ?? '',
                    'Qty' => (int)($row['qty'] ?? 0),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }
}
