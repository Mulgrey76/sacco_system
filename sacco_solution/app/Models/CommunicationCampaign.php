<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    public $timestamps = false;

    protected $fillable = ['org_id', 'name', 'trigger_type', 'channel', 'message_template', 'status', 'created_by'];

    public function logs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class, 'campaign_id');
    }
}
