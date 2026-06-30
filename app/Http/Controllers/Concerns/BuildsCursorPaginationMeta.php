<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Pagination\CursorPaginator;

trait BuildsCursorPaginationMeta
{
    private function cursorPaginationMeta(CursorPaginator $paginator): array
    {
        return [
            'per_page' => $paginator->perPage(),
            'next_cursor' => $paginator->nextCursor()?->encode(),
            'prev_cursor' => $paginator->previousCursor()?->encode(),
            'has_more_pages' => $paginator->hasMorePages(),
            'has_previous_pages' => $paginator->previousCursor() !== null,
        ];
    }
}
