<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    // Our table doesn't follow Laravel's "created_at/updated_at only" default
    // exactly, but it DOES have both columns, so timestamps stay on.
    public $timestamps = true;

    // Laravel assumes primary key 'id' and auto-increment BIGINT by default,
    // which matches our schema exactly -- no override needed.

    protected $fillable = [
        'org_code', 'name', 'address', 'website', 'country', 'timezone',
        'email', 'logo_path', 'client_ref_prefix', 'client_ref_format',
        'savings_ref_prefix', 'loan_ref_prefix', 'status', 'transactions_paused',
    ];

    protected $casts = [
        'status' => 'integer',
        'transactions_paused' => 'boolean',
    ];

    /**
     * BRD: one organization has many branches.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class, 'org_id');
    }
}
