<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The GENERIC maker-checker engine, reused across the whole platform:
 * branch fund transfers, client status changes, loan approvals,
 * reversals, equity limit updates, etc. -- distinguished only by
 * entity_type + entity_id, never a dedicated table per use case.
 */
class ApprovalRequest extends Model
{
    public $timestamps = false;  // table has created_at + resolved_at, not the standard pair

    protected $fillable = [
        'org_id', 'entity_type', 'entity_id', 'action', 'payload_json',
        'required_approvals', 'initiated_by', 'status', 'remarks', 'resolved_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'required_approvals' => 'integer',
        'resolved_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'approval_request_id');
    }

    /**
     * BRD Validation Settings: "Maker and checker should always be two
     * different users... the operation will always fail with an error
     * message." This is the exact rule; controllers must call this
     * before recording any approve/reject action.
     */
    public function canBeActionedBy(int $userId): bool
    {
        if ($this->initiated_by === $userId) {
            return false;
        }

        return $this->actions()->where('acted_by', $userId)->doesntExist();
    }
}
