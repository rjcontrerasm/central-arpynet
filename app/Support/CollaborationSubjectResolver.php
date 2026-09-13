<?php

namespace App\Support;

use App\Models\Incident;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class CollaborationSubjectResolver
{
    /**
     * @var array<string, class-string<Model>>
     */
    private const TYPES = [
        'task' => Task::class,
        'project' => Project::class,
        'service_order' => ServiceOrder::class,
        'incident' => Incident::class,
    ];

    public function resolveVisible(
        string $type,
        int $id,
        User $user,
    ): Model {
        $class = self::TYPES[$type] ?? null;

        if ($class === null) {
            throw (new ModelNotFoundException())
                ->setModel(Model::class, [$id]);
        }

        return $class::query()
            ->visibleTo($user)
            ->findOrFail($id);
    }

    public function typeFor(Model|string $subject): string
    {
        $class = is_string($subject)
            ? $subject
            : $subject::class;

        $type = array_search($class, self::TYPES, true);

        if (! is_string($type)) {
            throw new \InvalidArgumentException(
                'El tipo no admite colaboración.',
            );
        }

        return $type;
    }

    public function title(Model $subject): string
    {
        return match (true) {
            $subject instanceof Project => $subject->name,
            $subject instanceof Task,
            $subject instanceof ServiceOrder,
            $subject instanceof Incident => $subject->title,
            default => 'Elemento',
        };
    }

    public function label(Model|string $subject): string
    {
        $class = is_string($subject)
            ? $subject
            : $subject::class;

        return match ($class) {
            Task::class => 'Tarea',
            Project::class => 'Proyecto',
            ServiceOrder::class => 'Servicio',
            Incident::class => 'Incidente',
            default => 'Elemento',
        };
    }

    public function recentSubjects(User $user): Collection
    {
        $collections = collect([
            'task' => Task::query()
                ->visibleTo($user)
                ->with('organization:id,name')
                ->latest('updated_at')
                ->limit(15)
                ->get(),
            'project' => Project::query()
                ->visibleTo($user)
                ->with('organization:id,name')
                ->latest('updated_at')
                ->limit(15)
                ->get(),
            'service_order' => ServiceOrder::query()
                ->visibleTo($user)
                ->with('organization:id,name')
                ->latest('updated_at')
                ->limit(15)
                ->get(),
            'incident' => Incident::query()
                ->visibleTo($user)
                ->with('organization:id,name')
                ->latest('updated_at')
                ->limit(15)
                ->get(),
        ]);

        return $collections
            ->flatMap(function (Collection $subjects, string $type): Collection {
                return $subjects->map(
                    fn (Model $subject): array => [
                        'type' => $type,
                        'id' => (int) $subject->getKey(),
                        'label' => $this->label($subject),
                        'title' => $this->title($subject),
                        'organization_id' => (int) $subject->organization_id,
                        'organization_name' => $subject->organization?->name
                            ?? 'Empresa',
                        'updated_at' => $subject->updated_at,
                    ],
                );
            })
            ->sortByDesc('updated_at')
            ->take(60)
            ->values();
    }
}
