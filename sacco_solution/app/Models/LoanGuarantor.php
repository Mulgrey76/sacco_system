<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoanGuarantor extends Model
{
    public $timestamps = false;  // only has created_at

    protected $fillable = [
        'loan_id', 'guarantor_type', 'client_id',
        'external_full_name', 'external_id_number', 'external_phone',
        'approval_status', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class, 'loan_id');
    }

    /**
     * Only populated for guarantor_type = 'internal' -- an external
     * guarantor's details live directly on this row instead (BRD:
     * "Add option to select if a loan product allows for
     * internal/member or external/non-member guarantor(s)").
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    /**
     * OTP verification for this guarantor reuses the generic otp_codes
     * table (Module 1) rather than a dedicated column -- looked up via
     * reference_type = 'loan_guarantor', reference_id = this row's id.
     * (otp_codes has no foreign key to loan_guarantors in the schema --
     * it's a deliberately generic table -- so we scope the join by hand.)
     */
    public function otpCodes(): HasMany
    {
        return $this->hasMany(OtpCode::class, 'reference_id')
            ->where('reference_type', 'loan_guarantor');
    }
}
