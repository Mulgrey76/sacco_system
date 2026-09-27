<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Generic OTP store, reused for: client account approval, loan guarantor
 * approval, password reset, and login. Distinguished by 'purpose' and
 * the generic reference_type/reference_id pair -- deliberately NOT a
 * dedicated table per use case (Module 1 design decision).
 */
class OtpCode extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'org_id', 'purpose', 'reference_type', 'reference_id',
        'code', 'channel', 'is_used', 'expires_at',
    ];

    protected $casts = [
        'is_used' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
