<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'country',
    'state',
    'city',
    'rate',
    'is_default',
    'status',
])]
class TaxSetting extends Model
{
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'is_default' => 'boolean',
        ];
    }
}
