<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BRD: "Capture and store detailed logs for every action on the server."
 * Generic across the whole system -- entity_type/entity_id point at
 * whatever row was affected, same pattern as approval_requests.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'org_id', 'user_id', 'entity_type', 'entity_id',
        'action', 'ip_address', 'details_json',
    ];

    protected $casts = [
        'details_json' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Convenience helper so controllers write one line instead of
     * constructing the model manually every time:
     *   AuditLog::record($orgId, $userId, 'LOAN', $loan->id, 'APPROVE', $details);
     */
    public static function record(
        ?int $orgId,
        ?int $userId,
        string $entityType,
        ?int $entityId,
        string $action,
        array $details = []
    ): self {
        return static::create([
            'org_id' => $orgId,
            'user_id' => $userId,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'details_json' => $details,
        ]);
    }
}
