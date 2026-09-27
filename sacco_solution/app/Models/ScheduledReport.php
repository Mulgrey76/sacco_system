<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'report_template_id', 'frequency', 'event_name', 'recipients_json',
        'next_run_at', 'last_run_at', 'status',
    ];

    protected $casts = [
        'recipients_json' => 'array',
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
        'status' => 'integer',
    ];

    public function reportTemplate(): BelongsTo
    {
        return $this->belongsTo(ReportTemplate::class, 'report_template_id');
    }

    /**
     * BRD: "Scheduled reports - time & event based." True when this
     * schedule is due to run -- what Laravel's task scheduler will poll.
     */
    public function isDue(): bool
    {
        return $this->status === 1
            && $this->next_run_at !== null
            && $this->next_run_at->isPast();
    }
}
