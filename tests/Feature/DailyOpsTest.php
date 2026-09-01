<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyOpsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mi_dia_requires_login(): void
    {
        $this->get('/mi-dia')
            ->assertRedirect('/login');
    }

    public function test_user_can_open_mi_dia(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Mi día')
            ->assertSee('Captura rápida');
    }

    public function test_mi_dia_shows_user_task(): void
    {
        [$user, $organization] = $this->context();

        Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea visible',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSee('Tarea visible');
    }

    public function test_foreign_scope_task_is_hidden(): void
    {
        [$user] = $this->context();

        $foreign = Organization::query()->create([
            'name' => 'Empresa ajena',
            'slug' => 'empresa-ajena',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        Task::query()->create([
            'organization_id' => $foreign->id,
            'title' => 'No visible',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'due_at' => now(),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertDontSee('No visible');
    }

    private function context(): array
    {
        $user = User::factory()->create([
            'email' => 'rcontreras@arpynet.com',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet',
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

        return [$user, $organization];
    }
}
