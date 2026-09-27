<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsProduct extends Model
{
    protected $fillable = [
        'org_id', 'name', 'account_number_prefix', 'allow_overdraft',
        'allow_withdrawal', 'min_withdrawable', 'max_withdrawable',
        'non_withdrawable_limit', 'interest_rate_pct',
        'auto_create_on_activation', 'status',
    ];

    protected $casts = [
        'allow_overdraft' => 'boolean',
        'allow_withdrawal' => 'boolean',
        'min_withdrawable' => 'decimal:2',
        'max_withdrawable' => 'decimal:2',
        'non_withdrawable_limit' => 'decimal:2',
        'interest_rate_pct' => 'decimal:3',
        'auto_create_on_activation' => 'boolean',
        'status' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SavingsAccount::class, 'savings_product_id');
    }
}
