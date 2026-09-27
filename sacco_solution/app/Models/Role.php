<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public $timestamps = false;  // our roles table only has created_at, no updated_at

    protected $fillable = [
        'org_id', 'name', 'role_type', 'description', 'status',
    ];

    protected $casts = [
        'role_type' => 'integer',
        'status' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role_id');
    }
}
