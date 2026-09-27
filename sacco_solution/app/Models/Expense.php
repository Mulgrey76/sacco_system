<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'org_id', 'branch_id', 'budget_allocation_id', 'description', 'amount',
        'status', 'approval_request_id', 'created_by', 'paid_at',
    ];

    protected $casts = ['paid_at' => 'datetime'];

    public function budgetAllocation(): BelongsTo
    {
        return $this->belongsTo(BudgetAllocation::class, 'budget_allocation_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
