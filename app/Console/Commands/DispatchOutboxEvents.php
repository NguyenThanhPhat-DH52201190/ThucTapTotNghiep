<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchOutboxEvents extends Command
{
    protected $signature = 'outbox:dispatch {--limit=50}';
    protected $description = 'Deliver pending business events and retry failures safely.';

    public function handle(): int
    {
        $events = DB::table('outbox_events')->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('id')->limit((int) $this->option('limit'))->get();
        foreach ($events as $event) {
            try {
                if ($event->event_type === 'material_requisition_completed') {
                    $url = env('SHOP_FLOOR_WEBHOOK_URL');
                    if (!$url) throw new \RuntimeException('SHOP_FLOOR_WEBHOOK_URL is not configured.');
                    Http::timeout(5)->post($url, json_decode($event->payload, true))->throw();
                }
                DB::table('outbox_events')->where('id', $event->id)->update(['status' => 'delivered', 'delivered_at' => now(), 'updated_at' => now()]);
            } catch (\Throwable $e) {
                $attempts = $event->attempts + 1;
                DB::table('outbox_events')->where('id', $event->id)->update([
                    'attempts' => $attempts, 'available_at' => now()->addMinutes(min(60, 2 ** min(6, $attempts))),
                    'last_error' => $e->getMessage(), 'updated_at' => now(),
                ]);
                Log::warning('Outbox event delivery failed', ['event_id' => $event->id, 'message' => $e->getMessage()]);
            }
        }
        $this->info("Processed {$events->count()} outbox event(s).");
        return self::SUCCESS;
    }
}
