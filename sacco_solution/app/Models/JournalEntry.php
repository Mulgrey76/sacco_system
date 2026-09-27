<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    public $timestamps = false;  // has created_at + posted_at, not the standard pair

    protected $fillable = [
        'org_id', 'branch_id', 'entry_date', 'reference', 'narrative',
        'source_module', 'source_reference_id', 'status', 'created_by', 'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    /**
     * IMPORTANT: MySQL cannot enforce "total debits = total credits"
     * across rows via a CHECK constraint (documented in the schema
     * file's header comment) -- this is the actual enforcement point.
     * Call this BEFORE setting status = 'posted'; never post an
     * unbalanced entry.
     */
    public function isBalanced(): bool
    {
        $totalDebits = $this->lines()->sum('debit_amount');
        $totalCredits = $this->lines()->sum('credit_amount');

        return bccomp((string) $totalDebits, (string) $totalCredits, 2) === 0;
    }
}
