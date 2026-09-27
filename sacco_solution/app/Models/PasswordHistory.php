<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BRD Usability requirement: "the initial should never be reused" --
 * checked against the FULL history here, not just the most recent
 * password, whenever a user sets a new one.
 */
class PasswordHistory extends Model
{
    public $timestamps = false;

    protected $table = 'password_history';  // Laravel would otherwise guess 'password_histories'

    protected $fillable = [
        'user_id', 'password_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
