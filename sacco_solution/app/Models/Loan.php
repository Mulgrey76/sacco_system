<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    // BRD status codes, kept here as named constants so controllers never
    // have to hardcode a magic number like "2" when checking loan status.
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_ACTIVE = 2;
    public const STATUS_CLOSED = 3;
    public const STATUS_OVERDUE = 4;
    public const STATUS_WITHDRAWN = 5;
    public const STATUS_REJECTED = 6;
    public const STATUS_RESCHEDULED = 7;
    public const STATUS_WRITTEN_OFF = 8;
    public const STATUS_OVERPAID = 9;

    protected $fillable = [
        'org_id', 'branch_id', 'client_id', 'loan_product_id', 'loan_purpose_id',
        'loan_officer_id', 'refinanced_from_loan_id', 'loan_reference',
        'amount_applied', 'amount_approved', 'interest_rate_pct', 'interest_period',
        'source_of_funds', 'status', 'approved_at', 'disbursed_at',
    ];

    protected $casts = [
        'amount_applied' => 'decimal:2',
        'amount_approved' => 'decimal:2',
        'interest_rate_pct' => 'decimal:3',
        'status' => 'integer',
        'approved_at' => 'datetime',
        'disbursed_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }

    public function purpose(): BelongsTo
    {
        return $this->belongsTo(LoanPurpose::class, 'loan_purpose_id');
    }

    public function loanOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'loan_officer_id');
    }

    /**
     * BRD: "Refinance - Clear existing loan and create a new loan
     * automatically." Self-referencing link back to the loan this one replaced.
     */
    public function refinancedFrom(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'refinanced_from_loan_id');
    }

    public function guarantors(): HasMany
    {
        return $this->hasMany(LoanGuarantor::class, 'loan_id');
    }

    public function collateral(): HasMany
    {
        return $this->hasMany(LoanCollateral::class, 'loan_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(LoanNote::class, 'loan_id');
    }

    public function repaymentSchedule(): HasMany
    {
        return $this->hasMany(LoanRepaymentSchedule::class, 'loan_id')->orderBy('installment_number');
    }

    public function servicingEvents(): HasMany
    {
        return $this->hasMany(LoanServicingEvent::class, 'loan_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LoanCharge::class, 'loan_id');
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class, 'loan_id');
    }

    /**
     * The GENERIC maker-checker request tied to this loan's approval,
     * found via entity_type/entity_id rather than a dedicated FK -- the
     * same generic-reference pattern used throughout the schema.
     */
    public function approvalRequests(): HasMany
    {
        // Not a true Eloquent polymorphic relation (our entity_type is a
        // plain string tag, not Laravel's morph-map format), so we scope
        // it manually instead of relying on morphMany's auto-resolution.
        return $this->hasMany(ApprovalRequest::class, 'entity_id')
            ->where('entity_type', 'LOAN_APPROVAL');
    }

    /**
     * BRD: "Assessment must be done by at least 2 users... Approval on
     * system at least 2 or 3 users depending on the loan amount."
     * True once every attached guarantor has completed their OTP approval.
     */
    public function allGuarantorsApproved(): bool
    {
        return $this->guarantors()->where('approval_status', '!=', 'approved')->doesntExist();
    }
}
