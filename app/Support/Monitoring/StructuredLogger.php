<?php

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Log;

class StructuredLogger
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function checkout(string $message, array $context = [], string $level = 'info'): void
    {
        Log::channel('daily')->log($level, $message, LogSanitizer::context([
            'event' => 'checkout',
            ...$context,
        ]));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function payment(string $message, array $context = [], string $level = 'info'): void
    {
        Log::channel('payments')->log($level, $message, LogSanitizer::context([
            'event' => 'payment',
            ...$context,
        ]));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function inventory(string $message, array $context = [], string $level = 'info'): void
    {
        Log::channel('inventory')->log($level, $message, LogSanitizer::context([
            'event' => 'inventory',
            ...$context,
        ]));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function failedJob(string $message, array $context = []): void
    {
        Log::channel('failed_jobs')->alert($message, LogSanitizer::context([
            'event' => 'failed_job',
            ...$context,
        ]));
    }
}
