<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuickCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_capture_page_requires_login(): void
    {
        $this->get('/captura')
            ->assertRedirect('/login');
    }

    public function test_user_can_open_capture_page(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/captura')
            ->assertOk()
            ->assertSee('Nueva tarea')
            ->assertDontSee('Tareas recientes');
    }

    public function test_user_can_capture_task_for_today(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' => $organization->id,
                'title' => 'Enviar informe',
                'description' => 'Adjuntar sustento y validar el monto antes de enviarlo.',
                'due_mode' => 'today',
                'urgency' => 'high',
                'impact' => 'high',
            ])
            ->assertRedirect('/captura');

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'title' => 'Enviar informe',
            'description' => 'Adjuntar sustento y validar el monto antes de enviarlo.',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'high',
            'created_by' => $user->id,
        ]);

        $this->assertNotNull(
            \App\Models\Task::query()
                ->where('title', 'Enviar informe')
                ->first()
                ?->due_at,
        );
    }

    public function test_same_submission_token_does_not_create_duplicate_task(): void
    {
        [$user, $organization] = $this->context();

        $token = Str::uuid()->toString();

        $payload = [
            'organization_id' => $organization->id,
            'title' => 'Pago proveedor con detalle',
            'description' => 'Cuenta 001-123456. Validar factura antes de pagar.',
            'due_mode' => 'today',
            'urgency' => 'normal',
            'impact' => 'normal',
            'submission_token' => $token,
        ];

        $this->actingAs($user)
            ->from('/captura')
            ->post('/captura', $payload)
            ->assertRedirect('/captura');

        $this->actingAs($user)
            ->from('/captura')
            ->post('/captura', $payload)
            ->assertRedirect('/captura');

        $this->assertSame(
            1,
            \App\Models\Task::query()
                ->where('title', 'Pago proveedor con detalle')
                ->count(),
        );

        $this->assertDatabaseHas('tasks', [
            'organization_id' => $organization->id,
            'title' => 'Pago proveedor con detalle',
            'description' => 'Cuenta 001-123456. Validar factura antes de pagar.',
            'created_by' => $user->id,
        ]);
    }

    public function test_user_cannot_capture_for_foreign_organization(): void
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

        $this->actingAs($user)
            ->post('/captura', [
                'organization_id' => $foreign->id,
                'title' => 'No permitido',
                'due_mode' => 'none',
                'urgency' => 'medium',
                'impact' => 'medium',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('tasks', [
            'title' => 'No permitido',
        ]);
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

        $user->forceFill([
            'current_organization_id' =>
                $organization->id,
        ])->save();

        return [$user, $organization];
    }
}
