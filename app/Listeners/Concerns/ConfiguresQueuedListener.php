<?php

namespace App\Listeners\Concerns;

use Illuminate\Support\Facades\Log;
use Throwable;

trait ConfiguresQueuedListener
{
    public int $tries = 3;

    public int $timeout = 30;

    public bool $afterCommit = true;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function failed(object $event, Throwable $exception): void
    {
        Log::error('Queued listener failed.', [
            'listener' => static::class,
            'event' => $event::class,
            'exception' => $exception->getMessage(),
        ]);
    }
}
