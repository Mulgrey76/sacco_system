<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanCollateral extends Model
{
    public $timestamps = false;

    protected $table = 'loan_collateral';  // Laravel would otherwise guess 'loan_collaterals'

    protected $fillable = [
        'loan_id', 'description', 'estimated_value', 'document_path',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }
}
