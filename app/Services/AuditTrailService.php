<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AuditTrailService
{
    public function record(string $eventType, string $entityType, ?int $entityId, ?int $userId, array $before = [], array $after = [], ?string $reason = null): void
    {
        DB::table('audit_trails')->insert([
            'event_type' => $eventType, 'entity_type' => $entityType, 'entity_id' => $entityId,
            'user_id' => $userId, 'before_data' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null,
            'after_data' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null,
            'reason' => $reason, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
