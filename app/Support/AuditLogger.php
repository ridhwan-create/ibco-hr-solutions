<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function record(
        Request $request,
        string $action,
        string $auditableType,
        int|string $auditableId,
        array $oldValues = [],
        array $newValues = [],
    ): AuditLog {
        return AuditLog::query()->create([
            'user_id' => $request->user()?->getAuthIdentifier(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => (string) $auditableId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
