<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsAccount extends Model
{
    protected $fillable = [
        'org_id', 'branch_id', 'client_id', 'savings_product_id',
        'account_number', 'balance', 'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'status' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SavingsProduct::class, 'savings_product_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class, 'savings_account_id');
    }

    /**
     * BRD: "Close savings accounts only with 0 balance."
     */
    public function canClose(): bool
    {
        return bccomp((string) $this->balance, '0.00', 2) === 0;
    }
}
