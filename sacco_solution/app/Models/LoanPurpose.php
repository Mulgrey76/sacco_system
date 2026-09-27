<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LoanPurpose extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'org_id', 'name', 'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function loanProducts(): BelongsToMany
    {
        return $this->belongsToMany(LoanProduct::class, 'loan_product_purposes', 'loan_purpose_id', 'loan_product_id')
            ->withPivot('purpose_limit');
    }
}
