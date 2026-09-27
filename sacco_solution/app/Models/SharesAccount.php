<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharesAccount extends Model
{
    protected $table = 'shares_accounts';
    public $timestamps = false;

    protected $fillable = ['org_id', 'branch_id', 'client_id', 'shares_product_id', 'account_number', 'balance', 'status'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(SharesProduct::class, 'shares_product_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SharesTransaction::class, 'shares_account_id');
    }

    public function canClose(): bool
    {
        return bccomp((string) $this->balance, '0.00', 2) === 0;
    }
}
