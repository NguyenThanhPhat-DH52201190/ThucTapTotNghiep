<?php

namespace App\Jobs;

use App\Services\RequisitionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class CreateRequisitionForCutsheet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 3;
    public int $backoff = 30;
    public function __construct(public int $cutsheetId) {}
    public function handle(RequisitionService $service): void {
        try {
            $service->createForCutsheet($this->cutsheetId);
            DB::table('ocs')->where('id', $this->cutsheetId)->update(['requisition_job_status' => 'completed', 'requisition_job_error' => null, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            DB::table('ocs')->where('id', $this->cutsheetId)->update(['requisition_job_status' => 'failed', 'requisition_job_error' => $e->getMessage(), 'updated_at' => now()]);
            throw $e;
        }
    }
}
