<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * NOTE ON SCOPE: almost none of the ~25 named reports in BRD Section 12
 * (Loan Arrears, Portfolio at Risk, Disbursement Report, etc.) get their
 * own model -- they're just Eloquent queries against models we already
 * built (Loan, LoanRepaymentSchedule, PaymentTransaction, SavingsAccount,
 * JournalEntry...). This model only saves a report's CONFIGURATION so
 * it can be re-run or scheduled.
 */
class ReportTemplate extends Model
{
    protected $fillable = [
        'org_id', 'name', 'report_type', 'filters_json',
        'output_format', 'created_by', 'status',
    ];

    protected $casts = [
        'filters_json' => 'array',
        'status' => 'integer',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ScheduledReport::class, 'report_template_id');
    }

    public function generatedRuns(): HasMany
    {
        return $this->hasMany(GeneratedReport::class, 'report_template_id');
    }
}
