<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OutboxService
{
    public function record(string $eventType, string $aggregateType, int $aggregateId, array $payload): void
    {
        DB::table('outbox_events')->updateOrInsert([
            'event_type' => $eventType, 'aggregate_type' => $aggregateType, 'aggregate_id' => $aggregateId,
        ], [
            'payload' => json_encode($payload), 'status' => 'pending', 'available_at' => now(),
            'last_error' => null, 'updated_at' => now(), 'created_at' => now(),
        ]);
    }
}
