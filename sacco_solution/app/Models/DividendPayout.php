<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DividendPayout extends Model
{
    public $timestamps = false;

    protected $fillable = ['dividend_declaration_id', 'client_id', 'basis_amount', 'dividend_amount', 'status', 'paid_at'];

    protected $casts = ['paid_at' => 'datetime'];

    public function declaration(): BelongsTo
    {
        return $this->belongsTo(DividendDeclaration::class, 'dividend_declaration_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
