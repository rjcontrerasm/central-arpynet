<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Organization;
use App\Models\User;
use App\Services\CasaAndinaMonitorSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CasaAndinaMonitorIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_down_service_creates_single_incident(): void
    {
        $this->context();

        Http::fake([
            '*' => Http::response([
                'version' => 1,
                'generated_at' =>
                    '2026-08-14T01:30:00-05:00',
                'services' => [
                    [
                        'id' => 'web:casa-andina.com',
                        'name' => 'Casa Andina Web',
                        'status' => 'down',
                        'url' =>
                            'https://www.casa-andina.com',
                        'http_status' => 503,
                    ],
                ],
                'certificates' => [],
            ]),
        ]);

        $service = app(
            CasaAndinaMonitorSyncService::class,
        );

        $first = $service->sync();
        $second = $service->sync();

        $this->assertSame(1, $first['created']);
        $this->assertSame(0, $second['created']);

        $this->assertDatabaseCount('incidents', 1);

        $this->assertDatabaseHas('incidents', [
            'source' => 'monitor',
            'external_id' =>
                'service:web:casa-andina.com',
            'category' => 'availability',
            'severity' => 'critical',
        ]);
    }

    public function test_recovery_resolves_same_incident(): void
    {
        $this->context();

        Http::fakeSequence()
            ->push([
                'version' => 1,
                'services' => [
                    [
                        'id' => 'web:casa-andina.com',
                        'name' => 'Casa Andina Web',
                        'status' => 'down',
                    ],
                ],
                'certificates' => [],
            ])
            ->push([
                'version' => 1,
                'services' => [
                    [
                        'id' => 'web:casa-andina.com',
                        'name' => 'Casa Andina Web',
                        'status' => 'up',
                    ],
                ],
                'certificates' => [],
            ]);

        $service = app(
            CasaAndinaMonitorSyncService::class,
        );

        $service->sync();
        $result = $service->sync();

        $this->assertSame(1, $result['resolved']);
        $this->assertDatabaseCount('incidents', 1);

        $this->assertDatabaseHas('incidents', [
            'source' => 'monitor',
            'external_id' =>
                'service:web:casa-andina.com',
            'status' => 'resolved',
        ]);
    }

    public function test_ssl_warning_creates_certificate_incident(): void
    {
        $this->context();

        Http::fake([
            '*' => Http::response([
                'version' => 1,
                'services' => [],
                'certificates' => [
                    [
                        'id' => 'ssl:casa-andina.com',
                        'hostname' => 'casa-andina.com',
                        'status' => 'warning',
                        'expires_at' =>
                            '2026-08-25T00:00:00Z',
                        'days_remaining' => 11,
                    ],
                ],
            ]),
        ]);

        app(
            CasaAndinaMonitorSyncService::class,
        )->sync();

        $this->assertDatabaseHas('incidents', [
            'source' => 'monitor',
            'external_id' =>
                'certificate:ssl:casa-andina.com',
            'category' => 'certificate',
            'severity' => 'medium',
        ]);
    }

    private function context(): void
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'Casa Andina',
            'slug' => 'casa-andina',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach(
            $user->id,
            [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        config()->set(
            'casa_andina_monitor.enabled',
            true,
        );

        config()->set(
            'casa_andina_monitor.base_url',
            'https://monitor.example.test',
        );

        config()->set(
            'casa_andina_monitor.endpoint',
            '/api/integrations/central/status',
        );

        config()->set(
            'casa_andina_monitor.token',
            'test-token',
        );

        config()->set(
            'casa_andina_monitor.organization_slug',
            'casa-andina',
        );

        config()->set(
            'casa_andina_monitor.owner_email',
            'rcontreras@arpynet.com',
        );
    }
}
