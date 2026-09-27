<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBranch extends Model
{
    public $timestamps = false;
    public $incrementing = false;  // composite primary key (user_id, branch_id), no surrogate id

    protected $table = 'user_branches';

    protected $fillable = [
        'user_id', 'branch_id', 'is_primary', 'is_locked',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'is_locked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
