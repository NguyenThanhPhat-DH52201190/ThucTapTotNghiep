<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportCustomerStyles extends Command
{
    protected $signature = 'customer-styles:import-existing';
    protected $description = 'Register existing OCS/BOM styles per customer without overwriting configured styles';

    public function handle(): int
    {
        $customers = DB::table('customer_info')->get();
        $groups = [];
        $unmapped = 0;
        foreach (['ocs' => ['SNo', 'Sname', 'Customer'], 'bom_headers' => ['style_no', 'style_name', 'customer']] as $table => [$code, $name, $customerName]) {
            foreach (DB::table($table)->orderBy('id')->get() as $row) {
                if (!trim((string) $row->$code)) continue;
                $customer = $customers->firstWhere('id', $row->customer_id);
                if (!$customer) {
                    $matches = $customers->filter(fn ($c) => mb_strtolower(trim($c->name)) === mb_strtolower(trim((string) $row->$customerName)));
                    if ($matches->count() === 1) $customer = $matches->first();
                }
                if (!$customer) { $unmapped++; $this->warn("Unmapped: {$table} #{$row->id}, style {$row->$code}"); continue; }
                $key = $customer->id.'|'.$row->$code;
                $groups[$key] ??= ['customer_id' => $customer->id, 'style_no' => $row->$code, 'style_name' => $row->$name ?: $row->$code, 'images' => [], 'rows' => []];
                if ($row->image_path && Storage::disk('local')->exists($row->image_path)) $groups[$key]['images'][] = $row->image_path;
                $groups[$key]['rows'][] = [$table, $row->id, $customer->id];
            }
        }
        $created = 0;
        foreach ($groups as $group) {
            $path = null;
            try {
                DB::transaction(function () use ($group, &$path, &$created) {
                    $exists = DB::table('customer_styles')->where('customer_id', $group['customer_id'])->where('style_no', $group['style_no'])->exists();
                    if (!$exists) {
                        $images = array_values(array_unique($group['images']));
                        if (count($images) === 1) {
                            $path = 'customer-style-images/'.Str::uuid().'.'.pathinfo($images[0], PATHINFO_EXTENSION);
                            if (!Storage::disk('local')->copy($images[0], $path)) throw new \RuntimeException('Unable to copy style image.');
                        } elseif (count($images) > 1) {
                            $this->warn("Multiple images: customer {$group['customer_id']} / {$group['style_no']}. Choose the shared image in Customer Master; old images remain available.");
                        }
                        DB::table('customer_styles')->insert([
                            'customer_id' => $group['customer_id'], 'style_no' => $group['style_no'], 'style_name' => $group['style_name'],
                            'image_path' => $path, 'created_at' => now(), 'updated_at' => now(),
                        ]);
                        $created++;
                    }
                    foreach ($group['rows'] as [$table, $id, $customerId]) {
                        DB::table($table)->where('id', $id)->whereNull('customer_id')->update(['customer_id' => $customerId]);
                    }
                });
            } catch (\Throwable $e) {
                if ($path) Storage::disk('local')->delete($path);
                throw $e;
            }
        }
        $this->info("Registered {$created} styles. Unmapped records: {$unmapped}.");
        return self::SUCCESS;
    }
}
