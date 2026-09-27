<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Client extends Model
{
    protected $fillable = [
        'org_id', 'branch_id', 'member_number', 'account_category',
        'first_name', 'middle_name', 'last_name', 'other_names',
        'national_id', 'mobile_number', 'email', 'account_type',
        'status', 'is_dormant', 'kyc_verified', 'onboarded_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'is_dormant' => 'boolean',
        'kyc_verified' => 'boolean',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function onboardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'onboarded_by');
    }

    /**
     * BRD: "No client transaction is allowed without an existing &
     * active wallet" -- exactly one wallet per client.
     */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class, 'client_id');
    }

    /**
     * Convenience accessor matching how the BRD always refers to
     * clients: "Accommodate 4 names i.e first, middle, last, other."
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->middle_name} {$this->last_name} {$this->other_names}");
    }
}
