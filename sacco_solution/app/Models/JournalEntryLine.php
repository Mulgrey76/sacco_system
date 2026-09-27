<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'journal_entry_id', 'gl_account_id', 'debit_amount', 'credit_amount', 'narrative',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }

    /**
     * The database CHECK constraint already guarantees exactly one side
     * is nonzero -- this is a convenience accessor for display, not a
     * validation (the real validation lives in the DB constraint).
     */
    public function isDebit(): bool
    {
        return bccomp((string) $this->debit_amount, '0.00', 2) > 0;
    }
}
