<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanProduct extends Model
{
    protected $fillable = [
        'org_id', 'name', 'loan_grouping', 'source_level', 'category',
        'sub_category', 'loan_type',
        'deduct_interest_from_principal', 'disallow_interest_adjustment',
        'exclude_weekends', 'exclude_holidays', 'auto_disburse',
        'approval_levels', 'limit_control_amount', 'limit_control_approvers',
        'max_waiver_pct', 'allows_internal_guarantor', 'allows_external_guarantor',
        'min_amount', 'max_amount', 'interest_rate_pct', 'interest_period', 'status',
    ];

    protected $casts = [
        'deduct_interest_from_principal' => 'boolean',
        'disallow_interest_adjustment' => 'boolean',
        'exclude_weekends' => 'boolean',
        'exclude_holidays' => 'boolean',
        'auto_disburse' => 'boolean',
        'approval_levels' => 'integer',
        'limit_control_amount' => 'decimal:2',
        'limit_control_approvers' => 'integer',
        'max_waiver_pct' => 'decimal:2',
        'allows_internal_guarantor' => 'boolean',
        'allows_external_guarantor' => 'boolean',
        'min_amount' => 'decimal:2',
        'max_amount' => 'decimal:2',
        'interest_rate_pct' => 'decimal:3',
        'status' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * BRD: "Product visibility ... allow one product to have many
     * roles' relationship."
     */
    public function visibleToRoles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'loan_product_role_visibility', 'loan_product_id', 'role_id');
    }

    public function visibleAtBranches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'loan_product_branch_visibility', 'loan_product_id', 'branch_id');
    }

    public function charges(): HasMany
    {
        return $this->hasMany(LoanProductCharge::class, 'loan_product_id');
    }

    public function penaltySettings(): HasMany
    {
        return $this->hasMany(LoanProductPenaltySetting::class, 'loan_product_id');
    }

    public function purposes(): BelongsToMany
    {
        return $this->belongsToMany(LoanPurpose::class, 'loan_product_purposes', 'loan_product_id', 'loan_purpose_id')
            ->withPivot('purpose_limit');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'loan_product_id');
    }

    /**
     * BRD: "limit control - Up to an amount ... to pass more approval
     * process." Returns how many approvers a given application amount
     * actually requires for THIS product.
     */
    public function requiredApprovalsFor(float $amount): int
    {
        if ($this->limit_control_amount !== null
            && bccomp((string) $amount, (string) $this->limit_control_amount, 2) > 0
            && $this->limit_control_approvers !== null) {
            return max($this->approval_levels, $this->limit_control_approvers);
        }

        return $this->approval_levels;
    }
}
