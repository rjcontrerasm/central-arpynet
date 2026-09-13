<?php

namespace App\Http\Controllers;

use App\Models\CollaborationComment;
use App\Models\User;
use App\Notifications\CollaborationMentionNotification;
use App\Support\CollaborationSubjectResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CollaborationController extends Controller
{
    public function __construct(
        private readonly CollaborationSubjectResolver $subjects,
    ) {
    }

    public function index(Request $request): View
    {
        $user = $request->user();

        $comments = CollaborationComment::query()
            ->visibleTo($user)
            ->with([
                'author:id,name',
                'mentions:id,name',
                'commentable',
                'organization:id,name',
            ])
            ->latest()
            ->paginate(30);

        $subjects = $this->subjects
            ->recentSubjects($user);

        return view(
            'collaboration.index',
            compact('comments', 'subjects'),
        );
    }

    public function thread(
        Request $request,
        string $type,
        int $id,
    ): View {
        $user = $request->user();
        $subject = $this->subjects
            ->resolveVisible($type, $id, $user);
        $organizationId = (int) $subject->organization_id;

        $comments = CollaborationComment::query()
            ->where('organization_id', $organizationId)
            ->where('commentable_type', $subject::class)
            ->where('commentable_id', $subject->getKey())
            ->with([
                'author:id,name',
                'mentions:id,name',
            ])
            ->oldest()
            ->get();

        $members = User::query()
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true),
            )
            ->orderBy('name')
            ->get(['users.id', 'users.name']);

        $canWrite = $user->canWriteToOrganization($organizationId);
        $subjectLabel = $this->subjects->label($subject);
        $subjectTitle = $this->subjects->title($subject);

        return view(
            'collaboration.thread',
            compact(
                'type',
                'subject',
                'subjectLabel',
                'subjectTitle',
                'comments',
                'members',
                'canWrite',
            ),
        );
    }

    public function store(
        Request $request,
        string $type,
        int $id,
    ): RedirectResponse {
        $user = $request->user();
        $subject = $this->subjects
            ->resolveVisible($type, $id, $user);
        $organizationId = (int) $subject->organization_id;

        abort_unless(
            $user->canWriteToOrganization($organizationId),
            403,
            'Tu acceso a esta empresa es de solo lectura.',
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'mentions' => ['nullable', 'array'],
            'mentions.*' => ['integer', 'distinct'],
        ]);

        $requestedMentions = collect($validated['mentions'] ?? [])
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        $mentionedUsers = User::query()
            ->whereIn('id', $requestedMentions)
            ->where('is_active', true)
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder => $query
                    ->where('organizations.id', $organizationId)
                    ->where('organizations.is_active', true)
                    ->where('organization_user.is_active', true),
            )
            ->orderBy('id')
            ->get(['users.id', 'users.name']);

        if (
            $requestedMentions->sort()->values()->all()
            !== $mentionedUsers->pluck('id')->map(fn ($value): int => (int) $value)
                ->sort()->values()->all()
        ) {
            throw ValidationException::withMessages([
                'mentions' =>
                    'Todas las menciones deben pertenecer a usuarios activos de la misma empresa.',
            ]);
        }

        $comment = DB::transaction(function () use (
            $organizationId,
            $user,
            $subject,
            $validated,
            $mentionedUsers,
        ): CollaborationComment {
            $comment = CollaborationComment::query()->create([
                'organization_id' => $organizationId,
                'user_id' => $user->id,
                'commentable_type' => $subject::class,
                'commentable_id' => $subject->getKey(),
                'body' => trim($validated['body']),
            ]);

            $comment->mentions()->sync(
                $mentionedUsers->pluck('id')->all(),
            );

            $this->touchSubject($subject);

            return $comment;
        });

        $recipients = $mentionedUsers
            ->where('id', '!=', $user->id)
            ->values();

        if ($recipients->isNotEmpty()) {
            Notification::send(
                $recipients,
                new CollaborationMentionNotification(
                    commentId: (int) $comment->id,
                    authorName: $user->name,
                    body: $comment->body,
                    subjectType: $type,
                    subjectId: (int) $subject->getKey(),
                    subjectLabel: $this->subjects->label($subject),
                    subjectTitle: $this->subjects->title($subject),
                ),
            );
        }

        return redirect()
            ->route(
                'collaboration.thread',
                ['type' => $type, 'id' => $id],
            )
            ->with(
                'collaboration_success',
                'Comentario publicado.',
            );
    }

    private function touchSubject(Model $subject): void
    {
        if (method_exists($subject, 'touchActivity')) {
            $subject->touchActivity();

            return;
        }

        if ($subject->isFillable('last_activity_at')) {
            $subject->forceFill([
                'last_activity_at' => now(),
            ])->saveQuietly();
        }
    }
}
