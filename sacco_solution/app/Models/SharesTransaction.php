<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharesTransaction extends Model
{
    protected $table = 'shares_transactions';
    public $timestamps = false;

    protected $fillable = ['shares_account_id', 'wallet_transaction_id', 'type', 'amount', 'balance_after', 'created_by'];

    public function sharesAccount(): BelongsTo
    {
        return $this->belongsTo(SharesAccount::class, 'shares_account_id');
    }
}
