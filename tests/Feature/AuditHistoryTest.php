<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuditHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_creation_is_audited(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user);

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' => 'Preparar informe',
            'status' => 'pending',
            'urgency' => 'high',
            'impact' => 'medium',
            'due_at' => now()->addDay(),
            'created_by' => $user->id,
        ]);

        $log = AuditLog::query()
            ->where(
                'subject_type',
                'Task',
            )
            ->where(
                'subject_id',
                $task->id,
            )
            ->where(
                'event',
                'created',
            )
            ->firstOrFail();

        $this->assertSame(
            $user->id,
            $log->user_id,
        );

        $this->assertSame(
            $organization->id,
            $log->organization_id,
        );

        $this->assertSame(
            'Preparar informe',
            $log->changes['new']['title'],
        );
    }

    public function test_task_update_tracks_safe_fields_only(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user);

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' => 'Tarea auditada',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'notes' => 'Dato privado inicial',
            'created_by' => $user->id,
        ]);

        AuditLog::query()->delete();

        $task->update([
            'status' => 'completed',
            'notes' => 'Dato privado modificado',
        ]);

        $log = AuditLog::query()
            ->where(
                'subject_type',
                'Task',
            )
            ->where(
                'event',
                'updated',
            )
            ->firstOrFail();

        $fields = $log->changes['fields'];

        $this->assertArrayHasKey(
            'status',
            $fields,
        );

        $this->assertArrayNotHasKey(
            'notes',
            $fields,
        );

        $this->assertSame(
            'pending',
            $fields['status']['old'],
        );

        $this->assertSame(
            'completed',
            $fields['status']['new'],
        );
    }

    public function test_private_only_change_does_not_create_audit_noise(): void
    {
        [$user, $organization] = $this->context();

        $this->actingAs($user);

        $task = Task::query()->create([
            'organization_id' =>
                $organization->id,
            'title' => 'Sin ruido',
            'status' => 'pending',
            'urgency' => 'medium',
            'impact' => 'medium',
            'notes' => 'Inicial',
            'created_by' => $user->id,
        ]);

        AuditLog::query()->delete();

        $task->update([
            'notes' => 'Solo cambia nota privada',
        ]);

        $this->assertDatabaseCount(
            'audit_logs',
            0,
        );
    }

    public function test_history_screen_is_available(): void
    {
        [$user] = $this->context();

        $this->actingAs($user)
            ->get('/historial')
            ->assertOk()
            ->assertSee('Historial')
            ->assertSee('Cambios operativos');
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

        DB::table('organization_user')->insert([
            'organization_id' =>
                $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        return [$user, $organization];
    }
}
