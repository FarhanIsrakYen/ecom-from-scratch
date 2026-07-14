<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code',
    'type',
    'value',
    'max_discount_amount',
    'minimum_order_amount',
    'usage_limit',
    'usage_per_user',
    'starts_at',
    'expires_at',
    'status',
])]
class Coupon extends Model
{
    use HasFactory;

    public function usages(): HasMany
    {
        return $this->hasMany(CouponUsage::class);
    }

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_per_user' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
