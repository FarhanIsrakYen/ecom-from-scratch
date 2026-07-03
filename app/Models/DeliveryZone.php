<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'country',
    'state',
    'city',
    'postal_code',
    'charge',
    'is_default',
    'status',
])]
class DeliveryZone extends Model
{
    protected function casts(): array
    {
        return [
            'charge' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }
}
