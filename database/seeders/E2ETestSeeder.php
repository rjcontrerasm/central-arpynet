<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class E2ETestSeeder extends Seeder
{
    public const EMAIL = 'e2e@arpynet.test';
    public const PASSWORD = 'central-e2e-password';

    public function run(): void
    {
        if (! app()->environment(['testing', 'local'])) {
            throw new RuntimeException(
                'E2E seed is only available in testing/local.',
            );
        }

        $user = User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Central E2E',
                'password' => self::PASSWORD,
                'is_active' => true,
            ],
        );

        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'arpynet-e2e'],
            [
                'name' => 'ARPYNET E2E',
                'category' => 'company',
                'timezone' => 'America/Lima',
                'is_active' => true,
                'created_by' => $user->id,
            ],
        );

        $organization->users()->syncWithoutDetaching([
            $user->id => [
                'role' => 'owner',
                'is_default' => true,
                'is_active' => true,
            ],
        ]);

        $user->forceFill([
            'current_organization_id' => $organization->id,
        ])->save();

        Task::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'E2E tarea crítica',
            ],
            [
                'status' => 'pending',
                'urgency' => 'critical',
                'impact' => 'high',
                'due_at' => now()->subDay(),
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ],
        );

        Project::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'E2E proyecto',
            ],
            [
                'type' => 'project',
                'status' => 'active',
                'next_action' => 'Validar experiencia E2E',
                'created_by' => $user->id,
            ],
        );

        $client = Client::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'name' => 'E2E cliente',
            ],
            [
                'is_active' => true,
                'created_by' => $user->id,
            ],
        );

        $client->organizations()->syncWithoutDetaching([
            $organization->id => [
                'is_active' => true,
                'created_by' => $user->id,
            ],
        ]);

        ServiceOrder::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'E2E servicio',
            ],
            [
                'client_id' => $client->id,
                'stage' => 'opportunity',
                'next_action' => 'Continuar prueba E2E',
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ],
        );

        Incident::query()->updateOrCreate(
            [
                'organization_id' => $organization->id,
                'title' => 'E2E incidente',
            ],
            [
                'category' => 'other',
                'severity' => 'high',
                'status' => 'new',
                'source' => 'manual',
                'affected_service' => 'Central E2E',
                'assigned_to' => $user->id,
                'created_by' => $user->id,
            ],
        );
    }
}
