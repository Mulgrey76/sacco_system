<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlAccount extends Model
{
    protected $table = 'gl_accounts';

    protected $fillable = [
        'org_id', 'account_code', 'name', 'account_type',
        'parent_account_id', 'is_control_account', 'status',
    ];

    protected $casts = [
        'is_control_account' => 'boolean',
        'status' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * Self-referencing: e.g. 1000 Assets -> 1100 Current Assets ->
     * 1110 Cash at Bank. Same nesting pattern as Loan::refinancedFrom().
     */
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(GlAccount::class, 'parent_account_id');
    }

    public function journalEntryLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'gl_account_id');
    }
}
