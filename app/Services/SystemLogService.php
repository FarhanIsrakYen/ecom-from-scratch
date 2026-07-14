<?php

namespace App\Services;

use App\Support\Monitoring\LogSanitizer;
use Illuminate\Support\Facades\File;

class SystemLogService
{
    /**
     * @var array<string, string>
     */
    private array $channels = [
        'app' => 'laravel',
        'payments' => 'payments',
        'inventory' => 'inventory',
        'failed_jobs' => 'failed-jobs',
    ];

    /**
     * @return array<int, string>
     */
    public function allowedChannels(): array
    {
        return array_keys($this->channels);
    }

    /**
     * @return array{channel: string, lines: array<int, string>}
     */
    public function recent(string $channel = 'app', int $limit = 50): array
    {
        $path = $this->logPath($this->channels[$channel]);

        if ($path === null) {
            return ['channel' => $channel, 'lines' => []];
        }

        $lines = array_slice(file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [], -$limit);

        return [
            'channel' => $channel,
            'lines' => array_map(fn (string $line): string => LogSanitizer::line($line), $lines),
        ];
    }

    private function logPath(string $baseName): ?string
    {
        $direct = storage_path("logs/{$baseName}.log");

        if (File::exists($direct)) {
            return $direct;
        }

        $matches = glob(storage_path("logs/{$baseName}-*.log")) ?: [];
        rsort($matches);

        return $matches[0] ?? null;
    }
}
