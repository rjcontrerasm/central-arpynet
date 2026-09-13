<?php

namespace Tests\Feature;

use App\Models\CollaborationComment;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Notifications\CollaborationMentionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CollaborationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_comment_and_mention_a_user_from_same_organization(): void
    {
        Notification::fake();

        [$organization, $owner, $member, $viewer] = $this->team();
        $task = $this->task($organization, $owner);

        $response = $this->actingAs($member)->post(
            route('collaboration.store', [
                'type' => 'task',
                'id' => $task->id,
            ]),
            [
                'body' => 'Validar el avance con soporte.',
                'mentions' => [$viewer->id],
            ],
        );

        $response->assertRedirect(
            route('collaboration.thread', [
                'type' => 'task',
                'id' => $task->id,
            ]),
        );

        $comment = CollaborationComment::query()->sole();

        $this->assertSame($organization->id, $comment->organization_id);
        $this->assertSame($member->id, $comment->user_id);
        $this->assertSame(Task::class, $comment->commentable_type);
        $this->assertSame($task->id, $comment->commentable_id);
        $this->assertSame('Validar el avance con soporte.', $comment->body);

        $this->assertDatabaseHas('collaboration_comment_mentions', [
            'collaboration_comment_id' => $comment->id,
            'user_id' => $viewer->id,
        ]);

        Notification::assertSentTo(
            $viewer,
            CollaborationMentionNotification::class,
        );
    }

    public function test_viewer_can_read_thread_but_cannot_publish(): void
    {
        [$organization, $owner, $member, $viewer] = $this->team();
        $task = $this->task($organization, $owner);

        CollaborationComment::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'commentable_type' => Task::class,
            'commentable_id' => $task->id,
            'body' => 'Comentario visible para lectura.',
        ]);

        $this->actingAs($viewer)
            ->get(route('collaboration.thread', [
                'type' => 'task',
                'id' => $task->id,
            ]))
            ->assertOk()
            ->assertSee('Comentario visible para lectura.')
            ->assertSee('solo lectura');

        $this->actingAs($viewer)
            ->post(route('collaboration.store', [
                'type' => 'task',
                'id' => $task->id,
            ]), [
                'body' => 'No debe guardarse.',
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('collaboration_comments', 1);
    }

    public function test_user_cannot_access_a_thread_from_another_organization(): void
    {
        [$organization, $owner, $member] = $this->team();
        $task = $this->task($organization, $owner);

        $outsideOwner = User::factory()->create(['is_active' => true]);
        $outsideOrganization = Organization::query()->create([
            'name' => 'Otra empresa',
            'slug' => 'otra-empresa-collaboration-test',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $outsideOwner->id,
        ]);
        $outsideOrganization->users()->attach($outsideOwner->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->actingAs($outsideOwner)
            ->get(route('collaboration.thread', [
                'type' => 'task',
                'id' => $task->id,
            ]))
            ->assertNotFound();

        $this->actingAs($outsideOwner)
            ->post(route('collaboration.store', [
                'type' => 'task',
                'id' => $task->id,
            ]), [
                'body' => 'No permitido.',
            ])
            ->assertNotFound();
    }

    public function test_mentions_must_belong_to_same_active_organization(): void
    {
        Notification::fake();

        [$organization, $owner, $member] = $this->team();
        $task = $this->task($organization, $owner);
        $outsider = User::factory()->create(['is_active' => true]);

        $response = $this->actingAs($member)->post(
            route('collaboration.store', [
                'type' => 'task',
                'id' => $task->id,
            ]),
            [
                'body' => 'Intento con mención externa.',
                'mentions' => [$outsider->id],
            ],
        );

        $response->assertSessionHasErrors('mentions');
        $this->assertDatabaseCount('collaboration_comments', 0);
        Notification::assertNothingSent();
    }

    public function test_collaboration_index_only_lists_comments_from_accessible_organizations(): void
    {
        [$organization, $owner, $member] = $this->team();
        $task = $this->task($organization, $owner);

        CollaborationComment::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'commentable_type' => Task::class,
            'commentable_id' => $task->id,
            'body' => 'Comentario permitido.',
        ]);

        $otherOwner = User::factory()->create(['is_active' => true]);
        $otherOrganization = Organization::query()->create([
            'name' => 'Empresa privada externa',
            'slug' => 'empresa-privada-externa-collab',
            'category' => 'company',
            'is_active' => true,
            'created_by' => $otherOwner->id,
        ]);
        $otherOrganization->users()->attach($otherOwner->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);
        $otherTask = $this->task($otherOrganization, $otherOwner);

        CollaborationComment::query()->create([
            'organization_id' => $otherOrganization->id,
            'user_id' => $otherOwner->id,
            'commentable_type' => Task::class,
            'commentable_id' => $otherTask->id,
            'body' => 'Comentario que no debe mostrarse.',
        ]);

        $this->actingAs($member)
            ->get(route('collaboration.index'))
            ->assertOk()
            ->assertSee('Comentario permitido.')
            ->assertDontSee('Comentario que no debe mostrarse.');
    }

    private function team(): array
    {
        $owner = User::factory()->create(['is_active' => true]);
        $member = User::factory()->create(['is_active' => true]);
        $viewer = User::factory()->create(['is_active' => true]);

        $organization = Organization::query()->create([
            'name' => 'ARPYNET',
            'slug' => 'arpynet-collaboration-test-'.str()->random(8),
            'category' => 'company',
            'is_active' => true,
            'created_by' => $owner->id,
        ]);

        $organization->users()->attach($owner->id, [
            'role' => 'owner',
            'is_default' => true,
            'is_active' => true,
        ]);
        $organization->users()->attach($member->id, [
            'role' => 'member',
            'is_default' => false,
            'is_active' => true,
        ]);
        $organization->users()->attach($viewer->id, [
            'role' => 'viewer',
            'is_default' => false,
            'is_active' => true,
        ]);

        return [$organization, $owner, $member, $viewer];
    }

    private function task(
        Organization $organization,
        User $owner,
    ): Task {
        $this->actingAs($owner);

        return Task::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Tarea colaborativa',
            'status' => 'pending',
            'urgency' => 'normal',
            'impact' => 'normal',
            'source' => 'manual',
            'assigned_to' => $owner->id,
        ]);
    }
}
