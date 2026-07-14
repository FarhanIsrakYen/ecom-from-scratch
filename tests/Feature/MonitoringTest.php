<?php

namespace Tests\Feature;

use App\Enums\RoleEnum;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\InteractsWithEcommerce;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use InteractsWithEcommerce;
    use RefreshDatabase;

    public function test_health_check_returns_dependency_statuses_and_request_id(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertHeader('X-Request-Id')
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonStructure([
                'data' => [
                    'checks' => [
                        'app' => ['status', 'environment'],
                        'database' => ['status'],
                        'redis' => ['status'],
                        'queue' => ['status', 'connection', 'failed_jobs'],
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_sanitized_system_logs(): void
    {
        $this->actingAsRole(RoleEnum::Admin);
        File::ensureDirectoryExists(storage_path('logs'));
        File::put(storage_path('logs/payments.log'), implode(PHP_EOL, [
            'payment created for jane@example.com phone +15555550123',
            'payment processed safely',
        ]));

        $this->getJson('/api/admin/system-logs?channel=payments&limit=10')
            ->assertOk()
            ->assertJsonPath('data.channel', 'payments')
            ->assertJsonPath('data.lines.0', 'payment created for [masked-email] phone [masked-phone]')
            ->assertJsonPath('data.lines.1', 'payment processed safely');
    }

    public function test_customer_cannot_view_system_logs(): void
    {
        $this->actingAsRole(RoleEnum::Customer);

        $this->getJson('/api/admin/system-logs')
            ->assertForbidden();
    }
}
