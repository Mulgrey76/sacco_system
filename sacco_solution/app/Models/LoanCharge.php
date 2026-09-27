<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Distinct from LoanProductCharge (the template, Module 3a) -- this is
 * a charge actually applied to ONE loan instance, whether captured at
 * creation or added later (BRD: "Existing loan - Allow add charge to
 * existing loan").
 */
class LoanCharge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loan_id', 'loan_product_charge_id', 'name', 'charge_type',
        'amount_or_pct', 'computed_amount', 'is_prepaid', 'created_by',
    ];

    protected $casts = [
        'amount_or_pct' => 'decimal:4',
        'computed_amount' => 'decimal:2',
        'is_prepaid' => 'boolean',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    public function productChargeTemplate(): BelongsTo
    {
        return $this->belongsTo(LoanProductCharge::class, 'loan_product_charge_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
