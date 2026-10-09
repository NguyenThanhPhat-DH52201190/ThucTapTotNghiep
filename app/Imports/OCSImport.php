<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Carbon\Carbon;

class OCSImport implements ToCollection
{
    public function collection(Collection $rows): void
    {
        $headerIndex = null;
        $columnMap = [];
        foreach ($rows as $index => $values) {
            $values = $values instanceof Collection ? $values->all() : $values;
            $normalized = array_map(fn ($value) => strtolower(trim((string) $value)), $values);
            if (in_array('cs', $normalized, true) && in_array('onum', $normalized, true) && in_array('qty', $normalized, true)) {
                $headerIndex = $index;
                foreach ($normalized as $column => $heading) {
                    if (in_array($heading, ['cs', 'onum', 'sno', 'sname', 'customer', 'csdate', 'cmt', 'color', 'qty'], true)) {
                        $columnMap[$heading] = $column;
                    }
                }
                break;
            }
        }
        if ($headerIndex === null) {
            throw new \RuntimeException('Could not detect OCS columns in the file.');
        }

        DB::transaction(function () use ($rows, $headerIndex, $columnMap) {
        foreach ($rows->slice($headerIndex + 1) as $values) {
            $values = $values instanceof Collection ? $values->all() : $values;
            $row = [];
            foreach ($columnMap as $heading => $column) {
                $row[$heading] = $values[$column] ?? null;
            }
            // BOM is intentionally optional in the import sheet. OCS rows can be
            // imported first and a matching BOM assigned later from Edit OCS.
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
            if ($existing && $existing->status !== 'pending') {
                throw new \RuntimeException("CS {$cs} is confirmed, archived, or locked and cannot be changed by import.");
            }

            $customers = DB::table('customer_info')->where('name', trim((string) $row['customer']))->get();
            if ($customers->count() !== 1) {
                throw new \RuntimeException("CS {$cs}: customer must match one Customer Master record.");
            }
            $style = DB::table('customer_styles')->where('customer_id', $customers->first()->id)
                ->where('style_no', trim((string) $row['sno']))->first();
            if (!$style) throw new \RuntimeException("CS {$cs}: register this style in Customer Master before importing.");
            if ($existing && $existing->bom_header_id && ($existing->SNo !== $style->style_no || (int) $existing->customer_id !== (int) $style->customer_id)) {
                throw new \RuntimeException("CS {$cs}: change the customer/style and BOM together in Edit OCS.");
            }

            DB::table('ocs')->updateOrInsert(
                ['CS' => $cs],
                [
                    'ONum' => $row['onum'],
                    'SNo' => $style->style_no,
                    'Sname' => $style->style_name,
                    'customer_id' => $style->customer_id,
                    'Customer' => $customers->first()->name,
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
