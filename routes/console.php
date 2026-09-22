<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('mrp:run')->dailyAt('00:00')->withoutOverlapping();
Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping();

Artisan::command('stock-records:sync', function () {
    $count = app(\App\Services\StockRecordService::class)->importInventory();
    $this->info("Added $count stock records; existing opening snapshots preserved.");
})->purpose('Initialize opening stock records for new inventory material codes');
