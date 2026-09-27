<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Single ledger for disbursement (B2C), repayment (C2B), and reversal.
 * The BRD's "transaction queue" concept (FIFO, retries, locks, on-hold)
 * is modeled as DATA here (status/retry_count/next_retry_at) rather than
 * a message broker -- see sacco_schema_03c's header comment for the
 * full reasoning. A scheduled job (Laravel's task scheduler) will pick
 * up 'pending' rows ordered by created_at and process them.
 */
class PaymentTransaction extends Model
{
    public $timestamps = false;  // has created_at + processed_at, not the standard pair

    protected $fillable = [
        'org_id', 'loan_id', 'wallet_id', 'direction', 'amount',
        'external_reference', 'paid_by_mobile', 'status',
        'retry_count', 'next_retry_at', 'result_url', 'gateway_response_json',
        'transaction_fee', 'initiated_by', 'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'retry_count' => 'integer',
        'next_retry_at' => 'datetime',
        'gateway_response_json' => 'array',
        'transaction_fee' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'wallet_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * BRD: "how to avoid double posting on resubmitting the form data."
     * The real enforcement is the UNIQUE(org_id, external_reference)
     * database constraint -- this helper just lets application code
     * check BEFORE attempting an insert, for a friendlier error message.
     */
    public static function referenceAlreadyUsed(int $orgId, string $externalReference): bool
    {
        return static::where('org_id', $orgId)
            ->where('external_reference', $externalReference)
            ->exists();
    }
}
