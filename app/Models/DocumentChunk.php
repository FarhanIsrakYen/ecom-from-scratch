<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['document_id', 'chunk_index', 'content', 'metadata'])]
class DocumentChunk extends Model
{
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function embedding(): HasOne
    {
        return $this->hasOne(Embedding::class);
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
