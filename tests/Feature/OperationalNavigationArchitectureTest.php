<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperationalNavigationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_secondary_navigation_is_grouped_without_removing_core_destinations(): void
    {
        $user = User::factory()->create([
            'email' => 'navigation-architecture@arpynet.test',
        ]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET Navigation',
            'slug' => 'arpynet-navigation',
            'category' => 'company',
            'timezone' => 'America/Lima',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $organization->users()->attach($user->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/mi-dia')
            ->assertOk()
            ->assertSeeInOrder([
                'Hoy',
                'Mi equipo',
                'Sin asignar',
                'Trabajo',
                'Servicios',
                'Clientes',
                'Proyectos',
                'Control',
                'Revisión diaria',
                'Revisión semanal',
                'Inteligencia',
                'Decisiones',
                'Resumen',
                'Copilot',
                'Jarvis',
                'Automatizaciones',
                'Sistema',
                'Estado y recuperación',
                'Historial',
                'Papelera',
                'Administración avanzada',
            ]);
    }

    public function test_non_admin_roles_do_not_see_advanced_administration_link(): void
    {
        foreach (['member', 'viewer'] as $role) {
            $user = User::factory()->create([
                'email' => $role.'-navigation@arpynet.test',
            ]);

            $organization = Organization::query()->create([
                'name' => 'ARPYNET Navigation '.ucfirst($role),
                'slug' => 'arpynet-navigation-'.$role,
                'category' => 'company',
                'timezone' => 'America/Lima',
                'is_active' => true,
                'created_by' => $user->id,
            ]);

            $organization->users()->attach($user->id, [
                'role' => $role,
                'is_default' => true,
                'is_active' => true,
            ]);

            $this->actingAs($user)
                ->get('/mi-dia')
                ->assertOk()
                ->assertDontSee('Administración avanzada');
        }
    }

}
