<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetAllocation extends Model
{
    public $timestamps = false;

    protected $fillable = ['org_id', 'branch_id', 'expense_type', 'period_label', 'allocated_amount', 'utilized_amount', 'allocated_by'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'budget_allocation_id');
    }

    public function getRemainingAttribute(): string
    {
        return bcsub((string) $this->allocated_amount, (string) $this->utilized_amount, 2);
    }
}
