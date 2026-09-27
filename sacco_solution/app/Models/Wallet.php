<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = [
        'org_id', 'client_id', 'wallet_number', 'balance', 'reserved_amount',
        'min_withdrawable', 'max_withdrawable', 'status',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
        'reserved_amount' => 'decimal:2',
        'min_withdrawable' => 'decimal:2',
        'max_withdrawable' => 'decimal:2',
        'status' => 'integer',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class, 'wallet_id');
    }

    /**
     * BRD-derived helper: money actually available to spend right now,
     * i.e. balance minus whatever's held (e.g. reserved for a pending
     * loan approval per Module 3b).
     */
    public function getAvailableBalanceAttribute(): string
    {
        return bcsub((string) $this->balance, (string) $this->reserved_amount, 2);
    }
}
