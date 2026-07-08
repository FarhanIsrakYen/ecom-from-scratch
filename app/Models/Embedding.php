<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_chunk_id', 'provider', 'model', 'vector', 'dimensions', 'content_hash'])]
class Embedding extends Model
{
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(DocumentChunk::class, 'document_chunk_id');
    }

    protected function casts(): array
    {
        return [
            'vector' => 'array',
            'dimensions' => 'integer',
        ];
    }
}
