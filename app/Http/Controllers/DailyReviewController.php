<?php

namespace App\Http\Controllers;

use App\Models\DailyReviewSession;
use App\Support\ExecutiveSummaryBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DailyReviewController extends Controller
{
    private const STEPS = [
        'decisions' => 'decisions_reviewed_at',
        'waiting' => 'waiting_reviewed_at',
        'tasks' => 'tasks_reviewed_at',
        'operations' => 'operations_reviewed_at',
    ];

    public function show(
        Request $request,
        ExecutiveSummaryBuilder $builder,
    ): View {
        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $organizationIds = DB::table(
            'organization_user',
        )
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->where(
                'is_active',
                true,
            )
            ->pluck('organization_id');

        $summary = $builder->build(
            $organizationIds,
            null,
            'today',
            $now,
        );

        $review = DailyReviewSession::query()
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->whereDate(
                'review_date',
                $now->toDateString(),
            )
            ->first();

        $steps = [
            'decisions' => [
                'title' => 'Decisiones y críticos',
                'description' =>
                    'Revisa lo que requiere una decisión concreta.',
                'count' =>
                    (int) (
                        $summary['counts']['decisions']
                        ?? 0
                    ),
                'reviewed' =>
                    (bool) $review?->decisions_reviewed_at,
                'links' => [
                    [
                        'label' => 'Abrir decisiones',
                        'url' => route(
                            'decision-inbox.index',
                        ),
                    ],
                ],
            ],
            'waiting' => [
                'title' => 'Seguimientos en espera',
                'description' =>
                    'Confirma respuestas, aprobaciones y seguimientos vencidos.',
                'count' =>
                    (int) (
                        $summary['counts']['waiting_followups']
                        ?? 0
                    ),
                'reviewed' =>
                    (bool) $review?->waiting_reviewed_at,
                'links' => [
                    [
                        'label' => 'Abrir Mi día',
                        'url' => route(
                            'daily-ops.show',
                        ),
                    ],
                ],
            ],
            'tasks' => [
                'title' => 'Tareas con fecha',
                'description' =>
                    'Valida qué debe quedar resuelto o reprogramado hoy.',
                'count' =>
                    (int) (
                        $summary['counts']['tasks_due']
                        ?? 0
                    ),
                'reviewed' =>
                    (bool) $review?->tasks_reviewed_at,
                'links' => [
                    [
                        'label' => 'Ver tareas de hoy',
                        'url' => route(
                            'daily-ops.show',
                            [
                                'priority' => 'today',
                            ],
                        ),
                    ],
                ],
            ],
            'operations' => [
                'title' => 'Servicios y vencimientos',
                'description' =>
                    'Revisa próximas acciones comerciales y obligaciones.',
                'count' =>
                    (int) (
                        $summary['counts']['service_actions']
                        ?? 0
                    )
                    + (int) (
                        $summary['counts']['obligations']
                        ?? 0
                    ),
                'reviewed' =>
                    (bool) $review?->operations_reviewed_at,
                'links' => [
                    [
                        'label' => 'Servicios',
                        'url' => route(
                            'service-orders-ops.show',
                        ),
                    ],
                    [
                        'label' => 'Vencimientos',
                        'url' => route(
                            'obligation-ops.show',
                        ),
                    ],
                ],
            ],
        ];

        $reviewedCount = collect(
            $steps,
        )->where(
            'reviewed',
            true,
        )->count();

        return view(
            'daily-review',
            compact(
                'now',
                'review',
                'steps',
                'reviewedCount',
            ),
        );
    }

    public function mark(
        Request $request,
    ): RedirectResponse {
        $validated = $request->validate([
            'step' => [
                'required',
                'in:decisions,waiting,tasks,operations',
            ],
        ]);

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $reviewDate =
            $now->toDateString();

        /*
         * DATE is normalized differently by
         * SQLite/Eloquent during tests. We use
         * whereDate for lookup and the unique
         * index + insertOrIgnore as the final
         * idempotency/concurrency guard.
         */
        $review = DailyReviewSession::query()
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->whereDate(
                'review_date',
                $reviewDate,
            )
            ->first();

        if (! $review) {
            $timestamp = now();

            DB::table(
                'daily_review_sessions',
            )->insertOrIgnore([
                'user_id' =>
                    $request->user()->id,
                'review_date' =>
                    $reviewDate,
                'created_at' =>
                    $timestamp,
                'updated_at' =>
                    $timestamp,
            ]);

            $review = DailyReviewSession::query()
                ->where(
                    'user_id',
                    $request->user()->id,
                )
                ->whereDate(
                    'review_date',
                    $reviewDate,
                )
                ->firstOrFail();
        }

        $column = self::STEPS[
            $validated['step']
        ];

        if (! $review->{$column}) {
            $review->forceFill([
                $column => $now,
            ])->save();
        }

        $review->refresh();

        $allReviewed = collect(
            self::STEPS,
        )->every(
            fn (string $field): bool =>
                (bool) $review->{$field},
        );

        if (
            $allReviewed
            && ! $review->completed_at
        ) {
            $review->forceFill([
                'completed_at' => $now,
            ])->save();
        }

        return redirect()
            ->route('daily-review.show')
            ->with(
                'daily_review_success',
                $allReviewed
                    ? 'Revisión diaria completada.'
                    : 'Paso marcado como revisado.',
            );
    }
}
