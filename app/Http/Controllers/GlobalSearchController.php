<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Incident;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ServiceOrder;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class GlobalSearchController extends Controller
{
    private const RESULT_LIMIT = 8;

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'scope' => ['nullable', 'integer'],
            'type' => [
                'nullable',
                'in:all,task,project,client,service,incident',
            ],
        ]);

        $user = $request->user();
        $organizationIds = $user->activeOrganizationIds();

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! in_array(
                $selectedScope,
                $organizationIds,
                true,
            )
        ) {
            abort(403);
        }

        $search = trim((string) ($validated['q'] ?? ''));
        $type = $validated['type'] ?? 'all';
        $searchReady = mb_strlen($search) >= 2;

        $results = collect([
            'task' => collect(),
            'project' => collect(),
            'client' => collect(),
            'service' => collect(),
            'incident' => collect(),
        ]);

        if ($searchReady) {
            if ($this->includes($type, 'task')) {
                $results->put(
                    'task',
                    $this->tasks(
                        $user,
                        $selectedScope,
                        $search,
                    ),
                );
            }

            if ($this->includes($type, 'project')) {
                $results->put(
                    'project',
                    $this->projects(
                        $user,
                        $selectedScope,
                        $search,
                    ),
                );
            }

            if ($this->includes($type, 'client')) {
                $results->put(
                    'client',
                    $this->clients(
                        $user,
                        $organizationIds,
                        $selectedScope,
                        $search,
                    ),
                );
            }

            if ($this->includes($type, 'service')) {
                $results->put(
                    'service',
                    $this->services(
                        $user,
                        $selectedScope,
                        $search,
                    ),
                );
            }

            if ($this->includes($type, 'incident')) {
                $results->put(
                    'incident',
                    $this->incidents(
                        $user,
                        $selectedScope,
                        $search,
                    ),
                );
            }
        }

        $total = $results
            ->sum(
                fn (Collection $items): int =>
                    $items->count(),
            );

        return view('global-search', [
            'organizations' => $organizations,
            'selectedScope' => $selectedScope,
            'search' => $search,
            'searchReady' => $searchReady,
            'type' => $type,
            'results' => $results,
            'total' => $total,
        ]);
    }

    private function tasks(
        $user,
        ?int $selectedScope,
        string $search,
    ): Collection {
        return Task::query()
            ->visibleTo($user)
            ->with('organization:id,name')
            ->when(
                $selectedScope,
                fn (Builder $query): Builder =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('next_action', 'like', $like);
            })
            ->whereNotIn(
                'status',
                ['cancelled'],
            )
            ->latest('updated_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(
                fn (Task $task): array => [
                    'title' => $task->title,
                    'organization' =>
                        $task->organization?->name,
                    'meta' => implode(
                        ' · ',
                        array_filter([
                            Task::statusOptions()[
                                $task->status
                            ] ?? $task->status,
                            $task->due_at
                                ? 'Vence '
                                    .$task->due_at
                                        ->format('d/m/Y')
                                : null,
                        ]),
                    ),
                    'url' => route(
                        'global-tracking.show',
                        [
                            'type' => 'task',
                            'focus' => 'all',
                            'scope' =>
                                $task->organization_id,
                            'q' => $task->title,
                        ],
                    ),
                ],
            );
    }

    private function projects(
        $user,
        ?int $selectedScope,
        string $search,
    ): Collection {
        return Project::query()
            ->visibleTo($user)
            ->with('organization:id,name')
            ->when(
                $selectedScope,
                fn (Builder $query): Builder =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('name', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('next_action', 'like', $like)
                    ->orWhere('blockers', 'like', $like);
            })
            ->whereNotIn(
                'status',
                ['cancelled'],
            )
            ->latest('updated_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(
                fn (Project $project): array => [
                    'title' => $project->name,
                    'organization' =>
                        $project->organization?->name,
                    'meta' => Project::statusOptions()[
                        $project->status
                    ] ?? $project->status,
                    'url' => route(
                        'project-ops.show',
                        [
                            'scope' =>
                                $project->organization_id,
                            'focus' => 'all',
                            'q' => $project->name,
                        ],
                    ),
                ],
            );
    }

    private function clients(
        $user,
        array $organizationIds,
        ?int $selectedScope,
        string $search,
    ): Collection {
        return Client::query()
            ->visibleTo($user)
            ->with([
                'organizations' =>
                    function ($query) use (
                        $organizationIds,
                    ): void {
                        $query
                            ->whereIn(
                                'organizations.id',
                                $organizationIds,
                            )
                            ->where(
                                'organizations.is_active',
                                true,
                            )
                            ->wherePivot(
                                'is_active',
                                true,
                            )
                            ->orderBy(
                                'organizations.name',
                            );
                    },
            ])
            ->when(
                $selectedScope,
                fn (Builder $query): Builder =>
                    $query->forOrganization(
                        $selectedScope,
                    ),
            )
            ->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('name', 'like', $like)
                    ->orWhere('legal_name', 'like', $like)
                    ->orWhere('tax_id', 'like', $like)
                    ->orWhere('contact_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(
                function (Client $client): array {
                    $organizationNames =
                        $client->organizations
                            ->pluck('name')
                            ->join(' · ');

                    return [
                        'title' => $client->name,
                        'organization' =>
                            $organizationNames
                                ?: null,
                        'meta' => implode(
                            ' · ',
                            array_filter([
                                $client->tax_id,
                                $client->contact_name,
                            ]),
                        ),
                        'url' => route(
                            'client-ops.index',
                            ['client' => $client->id],
                        ),
                    ];
                },
            );
    }

    private function services(
        $user,
        ?int $selectedScope,
        string $search,
    ): Collection {
        return ServiceOrder::query()
            ->visibleTo($user)
            ->with([
                'organization:id,name',
                'client:id,name',
            ])
            ->when(
                $selectedScope,
                fn (Builder $query): Builder =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('title', 'like', $like)
                    ->orWhere('order_number', 'like', $like)
                    ->orWhere(
                        'quotation_number',
                        'like',
                        $like,
                    )
                    ->orWhere('next_action', 'like', $like)
                    ->orWhereHas(
                        'client',
                        fn (Builder $clientQuery): Builder =>
                            $clientQuery->where(
                                'name',
                                'like',
                                $like,
                            ),
                    );
            })
            ->whereNotIn(
                'stage',
                ['cancelled'],
            )
            ->latest('updated_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(
                fn (ServiceOrder $order): array => [
                    'title' => $order->title,
                    'organization' =>
                        $order->organization?->name,
                    'meta' => implode(
                        ' · ',
                        array_filter([
                            $order->client?->name,
                            $order->order_number
                                ? 'Orden '
                                    .$order->order_number
                                : null,
                            ServiceOrder::stageOptions()[
                                $order->stage
                            ] ?? $order->stage,
                        ]),
                    ),
                    'url' => route(
                        'service-orders-ops.show',
                        [
                            'scope' =>
                                $order->organization_id,
                            'focus' => 'all',
                            'finance' => 'all',
                            'q' => $order->title,
                        ],
                    ),
                ],
            );
    }

    private function incidents(
        $user,
        ?int $selectedScope,
        string $search,
    ): Collection {
        return Incident::query()
            ->visibleTo($user)
            ->with('organization:id,name')
            ->when(
                $selectedScope,
                fn (Builder $query): Builder =>
                    $query->where(
                        'organization_id',
                        $selectedScope,
                    ),
            )
            ->where(function (Builder $query) use ($search): void {
                $like = '%'.$search.'%';

                $query
                    ->where('title', 'like', $like)
                    ->orWhere(
                        'affected_service',
                        'like',
                        $like,
                    )
                    ->orWhere('description', 'like', $like)
                    ->orWhere('next_action', 'like', $like)
                    ->orWhere('external_id', 'like', $like);
            })
            ->whereNotIn(
                'status',
                ['cancelled'],
            )
            ->latest('detected_at')
            ->limit(self::RESULT_LIMIT)
            ->get()
            ->map(
                fn (Incident $incident): array => [
                    'title' => $incident->title,
                    'organization' =>
                        $incident->organization?->name,
                    'meta' => implode(
                        ' · ',
                        array_filter([
                            Incident::severityOptions()[
                                $incident->severity
                            ] ?? $incident->severity,
                            Incident::statusOptions()[
                                $incident->status
                            ] ?? $incident->status,
                            $incident->affected_service,
                        ]),
                    ),
                    'url' => route(
                        'incident-360.index',
                        [
                            'scope' =>
                                $incident->organization_id,
                            'focus' => 'all',
                            'incident' => $incident->id,
                        ],
                    ),
                ],
            );
    }

    private function includes(
        string $selected,
        string $type,
    ): bool {
        return $selected === 'all'
            || $selected === $type;
    }
}
