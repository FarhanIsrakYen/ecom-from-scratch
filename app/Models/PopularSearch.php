<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['query', 'search_count', 'last_searched_at'])]
class PopularSearch extends Model
{
    protected function casts(): array
    {
        return [
            'last_searched_at' => 'datetime',
        ];
    }
}
