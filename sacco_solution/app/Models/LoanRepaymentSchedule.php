<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanRepaymentSchedule extends Model
{
    protected $fillable = [
        'loan_id', 'installment_number', 'due_date',
        'expected_principal', 'expected_interest', 'expected_total',
        'paid_amount', 'status', 'paid_date',
    ];

    protected $casts = [
        'due_date' => 'date',
        'expected_principal' => 'decimal:2',
        'expected_interest' => 'decimal:2',
        'expected_total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'paid_date' => 'date',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    /**
     * How much of this installment is still owed.
     */
    public function getOutstandingAttribute(): string
    {
        return bcsub((string) $this->expected_total, (string) $this->paid_amount, 2);
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->outstanding, '0.00', 2) <= 0;
    }
}
