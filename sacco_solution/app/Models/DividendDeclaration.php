<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DividendDeclaration extends Model
{
    public $timestamps = false;

    protected $fillable = ['org_id', 'period_label', 'rate_pct', 'basis', 'status', 'declared_by'];

    public function payouts(): HasMany
    {
        return $this->hasMany(DividendPayout::class, 'dividend_declaration_id');
    }

    public function declaredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declared_by');
    }
}
