<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RevenueMasterPlanSync
{
    /**
     * Create a Revenue row for every Master Plan CU that is ready for sewing.
     * Existing Revenue inputs are preserved; only the sewing line follows MTP.
     */
    public function syncReadyMasterPlans(): void
    {
        try {
            $readyPlans = DB::table('mtp')
                ->whereNotNull('CU')
                ->where('CU', '!=', '')
                ->whereNotNull('Line')
                ->where('Line', '!=', '')
                ->whereNotNull('FirstOPT')
                ->whereNotNull('lt')
                ->where('lt', '>', 0)
                ->get(['CU', 'Line', 'Qty_dis']);

            foreach ($readyPlans as $plan) {
                $existing = DB::table('revenue')->where('CS', $plan->CU)->first();

                if ($existing) {
                    if ((string) $existing->SewingLine !== (string) $plan->Line) {
                        DB::table('revenue')->where('id', $existing->id)->update([
                            'SewingLine' => $plan->Line,
                            'updated_at' => now(),
                        ]);
                    }

                    continue;
                }

                DB::table('revenue')->insert([
                    'CS' => $plan->CU,
                    'SewingLine' => $plan->Line,
                    'planout' => (int) ($plan->Qty_dis ?? 0),
                    'actualout' => 0,
                    'sewingmp' => 0,
                    'workhrs' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Failed to sync ready Master Plan rows to Revenue', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
