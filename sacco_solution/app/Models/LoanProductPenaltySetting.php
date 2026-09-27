<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanProductPenaltySetting extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'loan_product_id', 'penalty_type', 'amount_or_pct', 'max_applications', 'applies_daily',
    ];

    protected $casts = [
        'amount_or_pct' => 'decimal:4',
        'max_applications' => 'integer',
        'applies_daily' => 'boolean',
    ];

    public function loanProduct(): BelongsTo
    {
        return $this->belongsTo(LoanProduct::class, 'loan_product_id');
    }
}
