<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/**
 * SECURITY FIX: previously this was a plain Eloquent Model, and
 * AuthController::login() issued a random string that was never
 * checked on any later request -- every endpoint was effectively
 * unauthenticated. Extending Authenticatable + HasApiTokens lets us
 * issue REAL Sanctum tokens and protect routes with the 'auth:sanctum'
 * middleware, which validates the token on every request.
 */
class User extends Authenticatable
{
    use HasApiTokens;

    protected $fillable = [
        'org_id', 'role_id', 'username', 'email', 'email_verified_at',
        'phone_number', 'password_hash', 'must_change_password',
        'password_expires_at', 'failed_login_attempts', 'locked_until',
        'last_login_at', 'status', 'is_back_office', 'created_by',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'must_change_password' => 'boolean',
        'password_expires_at' => 'datetime',
        'failed_login_attempts' => 'integer',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
        'status' => 'integer',
        'is_back_office' => 'boolean',
    ];

    /**
     * Our column is 'password_hash', not Laravel's default 'password' --
     * this tells Authenticatable (and anything else that needs the
     * hashed password) where to actually find it.
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_branches', 'user_id', 'branch_id')
            ->withPivot(['is_primary', 'is_locked']);
    }

    public function onboardedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'onboarded_by');
    }
}
