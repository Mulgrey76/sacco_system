<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Branch extends Model
{
    protected $fillable = [
        'org_id', 'branch_code', 'name', 'address',
        'working_ac_limit', 'utility_ac_pct', 'arrears_rate_pct',
        'is_disabled', 'status',
    ];

    protected $casts = [
        'working_ac_limit' => 'decimal:2',
        'utility_ac_pct' => 'decimal:2',
        'arrears_rate_pct' => 'decimal:2',
        'is_disabled' => 'boolean',
        'status' => 'integer',
    ];

    /**
     * Every branch belongs to exactly one organization.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }
}
