<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharesProduct extends Model
{
    protected $table = 'shares_products';
    public $timestamps = false;

    protected $fillable = ['org_id', 'name', 'account_number_prefix', 'interest_rate_pct', 'status'];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(SharesAccount::class, 'shares_product_id');
    }
}
