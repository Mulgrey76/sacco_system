<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Execution log every time a report actually runs -- also doubles as
 * the BRD's "Failed and undelivered emails & sms report" concept: any
 * failure just shows up here as status = 'failed'.
 */
class GeneratedReport extends Model
{
    public $timestamps = false;

    protected $table = 'generated_reports';

    protected $fillable = [
        'org_id', 'report_template_id', 'generated_by', 'output_format',
        'file_path', 'row_count', 'status', 'error_message', 'generated_at',
    ];

    protected $casts = [
        'row_count' => 'integer',
        'generated_at' => 'datetime',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
