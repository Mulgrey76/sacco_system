<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['org_id', 'campaign_id', 'client_id', 'channel', 'recipient', 'message_body', 'status', 'error_message', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
