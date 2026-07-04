<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'courier_name',
    'tracking_number',
    'status',
    'shipped_at',
    'delivered_at',
])]
class Shipment extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    protected function casts(): array
    {
        return [
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
