<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductCharge extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loan_product_id', 'name', 'charge_type', 'amount_or_pct', 'is_prepaid', 'status',
    ];

    protected $casts = [
        'amount_or_pct' => 'decimal:4',
        'is_prepaid' => 'boolean',
        'status' => 'integer',
    ];

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
