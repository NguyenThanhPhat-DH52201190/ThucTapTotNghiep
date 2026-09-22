<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NormRateService
{
    public function forItem(int $cutsheetId, object $item): array
    {
        $confirmed = DB::table('norm_confirmations')->where('cutsheet_id', $cutsheetId)
            ->where('bom_item_id', $item->id)->first();
        return [
            'yield' => (float) ($confirmed?->yield_confirmed ?? $item->consumption_rate),
            'waste' => (float) ($confirmed?->waste_confirmed ?? $item->waste_percent),
        ];
    }
}
