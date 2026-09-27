<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BRD: "GL Account Mappings - used to control chart of accounts to
 * their corresponding GL accounts." Lets future auto-posting logic ask
 * "which account do I credit for a loan disbursement?" via a lookup
 * instead of hardcoded IDs scattered through the codebase.
 */
class GlAccountMapping extends Model
{
    public $timestamps = false;

    protected $table = 'gl_account_mappings';

    protected $fillable = [
        'org_id', 'transaction_type', 'gl_account_id',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }

    /**
     * Convenience lookup: GlAccountMapping::accountFor($orgId, 'LOAN_DISBURSEMENT')
     */
    public static function accountFor(int $orgId, string $transactionType): ?GlAccount
    {
        return static::where('org_id', $orgId)
            ->where('transaction_type', $transactionType)
            ->first()
            ?->glAccount;
    }
}
