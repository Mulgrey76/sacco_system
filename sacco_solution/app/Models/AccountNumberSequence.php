<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * BRD: account numbers are <category prefix><4-digit auto-incremented
 * tail>, e.g. category '100100' + seq 123 -> '1001000123'. Must be safe
 * under concurrent requests -- two staff creating clients at once must
 * never receive the same number. Same reasoning as the original
 * services/account_number.py from the FastAPI attempt, reimplemented
 * here using Laravel's own row-locking.
 */
class AccountNumberSequence extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'org_id', 'account_type', 'category_code', 'last_sequence',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    /**
     * Atomically increments the sequence and returns the formatted
     * account number. Uses lockForUpdate() (Laravel's equivalent of
     * SELECT ... FOR UPDATE) so concurrent requests serialize on this
     * row instead of racing to read-then-write the same value.
     */
    public static function generate(int $orgId, string $accountType, string $categoryCode): string
    {
        return DB::transaction(function () use ($orgId, $accountType, $categoryCode) {
            $sequence = static::where('org_id', $orgId)
                ->where('account_type', $accountType)
                ->where('category_code', $categoryCode)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                $sequence = static::create([
                    'org_id' => $orgId,
                    'account_type' => $accountType,
                    'category_code' => $categoryCode,
                    'last_sequence' => 0,
                ]);
            }

            $sequence->increment('last_sequence');
            $nextSeq = $sequence->last_sequence;

            if ($nextSeq > 9999) {
                throw new \RuntimeException(
                    "Sequence for category {$categoryCode} exceeded 4-digit capacity; "
                    . "org admin must extend the numbering scheme."
                );
            }

            return $categoryCode . str_pad((string) $nextSeq, 4, '0', STR_PAD_LEFT);
        });
    }
}
