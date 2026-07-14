<?php

namespace App\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class SystemHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $checks = [
            'app' => [
                'status' => 'ok',
                'environment' => app()->environment(),
            ],
            'database' => $this->databaseStatus(),
            'redis' => $this->redisStatus(),
            'queue' => $this->queueStatus(),
        ];
        $healthy = collect($checks)->every(fn (array $check): bool => in_array($check['status'], ['ok', 'skipped'], true));

        return [
            'healthy' => $healthy,
            'payload' => [
                'status' => $healthy ? 'ok' : 'degraded',
                'service' => config('app.name'),
                'checks' => $checks,
            ],
        ];
    }

    /**
     * @return array{status: string}
     */
    private function databaseStatus(): array
    {
        try {
            DB::select('select 1');

            return ['status' => 'ok'];
        } catch (QueryException|Throwable) {
            return ['status' => 'failed'];
        }
    }

    /**
     * @return array{status: string}
     */
    private function redisStatus(): array
    {
        if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
            return ['status' => 'skipped'];
        }

        try {
            Redis::connection()->ping();

            return ['status' => 'ok'];
        } catch (Throwable) {
            return ['status' => 'failed'];
        }
    }

    /**
     * @return array{status: string, connection: mixed, failed_jobs: int}
     */
    private function queueStatus(): array
    {
        return [
            'status' => 'ok',
            'connection' => config('queue.default'),
            'failed_jobs' => $this->failedJobsCount(),
        ];
    }

    private function failedJobsCount(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
