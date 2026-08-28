<?php

namespace App\Console\Commands;

use App\Http\Controllers\MRPController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class RunMrpCommand extends Command
{
    protected $signature = 'mrp:run {--from=} {--to=}';
    protected $description = 'Calculate MRP and persist purchase suggestions for active master-plan rows.';

    public function handle(MRPController $controller): int
    {
        try {
            $controller->calculate(Request::create('/admin/mrp/calculate', 'POST', [
                'period_from' => $this->option('from'), 'period_to' => $this->option('to'),
                'notes' => 'Scheduled MRP run', 'throw_on_failure' => true,
            ]));
            $this->info('MRP calculation completed.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('MRP calculation failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
