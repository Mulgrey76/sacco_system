<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'savings_account_id', 'wallet_transaction_id', 'type',
        'amount', 'balance_after', 'narrative', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'savings_account_id');
    }

    /**
     * BRD-driven traceability: walk a savings deposit back to the exact
     * wallet movement (and ultimately the M-Pesa receipt) that funded it.
     */
    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'wallet_transaction_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
