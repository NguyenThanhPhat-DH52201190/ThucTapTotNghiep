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
        DB::transaction(function () use ($rows) {
        foreach ($rows as $row) {
            $cs = trim((string) ($row['cs'] ?? ''));
            $qty = (int) ($row['qty'] ?? 0);
            if ($cs === '' || $qty < 1 || empty($row['onum']) || empty($row['sno']) || empty($row['sname']) || empty($row['customer'])) {
                throw new \RuntimeException('Each import row requires CS, PO, style, customer, and Qty greater than zero.');
            }

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

            $existing = DB::table('ocs')->where('CS', $cs)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['released', 'in_production', 'completed', 'closed'], true)) {
                throw new \RuntimeException("CS {$cs} is released or locked and cannot be changed by import.");
            }

            DB::table('ocs')->updateOrInsert(
                ['CS' => $cs],
                [
                    'ONum' => $row['onum'],
                    'SNo' => $row['sno'],
                    'Sname' => $row['sname'],
                    'Customer' => $row['customer'],
                    'CsDate' => $date,
                    'CMT' => $row['cmt'] ?? 0,
                    'Color' => $row['color'] ?? '',
                    'Qty' => $qty,
                    'status' => $existing?->status ?? 'pending',
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
            if (!$existing) {
                $cutsheetId = DB::table('ocs')->where('CS', $cs)->value('id');
                DB::table('order_sizes')->insert([
                    'cutsheet_id' => $cutsheetId, 'size_name' => 'ONE SIZE', 'quantity' => $qty,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
        });
    }
}
