<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic log for every "something changed about this loan" action:
 * refinance, top-up, reschedule, off-set, waiver, write-off, closure,
 * amendment, limit-change, charge-added. One table instead of ten,
 * distinguished by event_type -- same generic-event pattern used
 * elsewhere in the schema.
 */
class LoanServicingEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loan_id', 'event_type', 'amount', 'source_of_funds',
        'remarks', 'approval_request_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    /**
     * Populated only for events requiring maker-checker (reschedule,
     * waiver, write_off, limit_change per the BRD); NULL for e.g. a
     * system auto-closure that needs no approval.
     */
    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
