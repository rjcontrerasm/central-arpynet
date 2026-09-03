<?php

namespace App\Http\Controllers;

use App\Models\WeeklyReviewSession;
use App\Support\WeeklyReviewBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WeeklyReviewController extends Controller
{
    private const STEPS = [
        'carryover' =>
            'carryover_reviewed_at',
        'stagnation' =>
            'stagnation_reviewed_at',
        'finance' =>
            'finance_reviewed_at',
        'obligations' =>
            'obligations_reviewed_at',
        'horizon' =>
            'horizon_reviewed_at',
    ];

    public function show(
        Request $request,
        WeeklyReviewBuilder $builder,
    ): View {
        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $data = $builder->build(
            $request->user(),
            $now,
        );

        $review = WeeklyReviewSession::query()
            ->where(
                'user_id',
                $request->user()->id,
            )
            ->whereDate(
                'week_start',
                $data['week_start']
                    ->toDateString(),
            )
            ->first();

        $steps = [
            'carryover' => [
                'title' =>
                    'Arrastres y vencidos',
                'description' =>
                    'Limpia tareas vencidas y seguimientos en espera que ya requieren una decisión.',
                'count' =>
                    $data['counts'][
                        'carryover'
                    ],
                'reviewed' =>
                    (bool) $review
                        ?->carryover_reviewed_at,
                'links' => [
                    [
                        'label' =>
                            'Abrir Mi día',
                        'url' => route(
                            'daily-ops.show',
                            [
                                'priority' =>
                                    'critical',
                            ],
                        ),
                    ],
                ],
            ],
            'stagnation' => [
                'title' =>
                    'Estancamientos',
                'description' =>
                    'Revisa tareas sin movimiento, sin próxima acción y proyectos detenidos.',
                'count' =>
                    $data['counts'][
                        'stagnation'
                    ],
                'reviewed' =>
                    (bool) $review
                        ?->stagnation_reviewed_at,
                'links' => [
                    [
                        'label' =>
                            'Ver estancados',
                        'url' => route(
                            'global-tracking.show',
                            [
                                'focus' =>
                                    'stagnant',
                            ],
                        ),
                    ],
                    [
                        'label' =>
                            'Sin próxima acción',
                        'url' => route(
                            'global-tracking.show',
                            [
                                'focus' =>
                                    'no_next_action',
                            ],
                        ),
                    ],
                ],
            ],
            'finance' => [
                'title' =>
                    'Cobranza y facturación',
                'description' =>
                    'Atiende facturas vencidas y servicios que ya pueden pasar a facturación.',
                'count' =>
                    $data['counts'][
                        'finance'
                    ],
                'reviewed' =>
                    (bool) $review
                        ?->finance_reviewed_at,
                'links' => [
                    [
                        'label' =>
                            'Cobranza vencida',
                        'url' => route(
                            'service-orders-ops.show',
                            [
                                'focus' => 'all',
                                'finance' =>
                                    'overdue',
                            ],
                        ),
                    ],
                ],
            ],
            'obligations' => [
                'title' =>
                    'Obligaciones',
                'description' =>
                    'Confirma vencidos y compromisos de los próximos 30 días.',
                'count' =>
                    $data['counts'][
                        'obligations'
                    ],
                'reviewed' =>
                    (bool) $review
                        ?->obligations_reviewed_at,
                'links' => [
                    [
                        'label' =>
                            'Abrir vencimientos',
                        'url' => route(
                            'obligation-ops.show',
                            [
                                'focus' =>
                                    'attention',
                            ],
                        ),
                    ],
                ],
            ],
            'horizon' => [
                'title' =>
                    'Próximos 7 / 30 días',
                'description' =>
                    'Anticipa carga, entregables, seguimientos y vencimientos antes de que se vuelvan urgentes.',
                'count' =>
                    $data['counts'][
                        'horizon_30'
                    ],
                'reviewed' =>
                    (bool) $review
                        ?->horizon_reviewed_at,
                'links' => [
                    [
                        'label' =>
                            'Abrir agenda',
                        'url' => route(
                            'operational-agenda.show',
                        ),
                    ],
                ],
            ],
        ];

        $reviewedCount = collect(
            $steps,
        )
            ->where(
                'reviewed',
                true,
            )
            ->count();

        return view(
            'weekly-review',
            [
                ...$data,
                'review' => $review,
                'steps' => $steps,
                'reviewedCount' =>
                    $reviewedCount,
            ],
        );
    }

    public function mark(
        Request $request,
    ): RedirectResponse {
        $validated = $request->validate([
            'step' => [
                'required',
                'in:carryover,stagnation,finance,obligations,horizon',
            ],
        ]);

        $now = CarbonImmutable::now(
            config(
                'app.timezone',
                'America/Lima',
            ),
        );

        $weekStart = $now
            ->startOfWeek()
            ->toDateString();

        $review =
            WeeklyReviewSession::query()
                ->where(
                    'user_id',
                    $request
                        ->user()
                        ->id,
                )
                ->whereDate(
                    'week_start',
                    $weekStart,
                )
                ->first();

        if (! $review) {
            $timestamp = now();

            DB::table(
                'weekly_review_sessions',
            )->insertOrIgnore([
                'user_id' =>
                    $request->user()->id,
                'week_start' =>
                    $weekStart,
                'created_at' =>
                    $timestamp,
                'updated_at' =>
                    $timestamp,
            ]);

            $review =
                WeeklyReviewSession::query()
                    ->where(
                        'user_id',
                        $request
                            ->user()
                            ->id,
                    )
                    ->whereDate(
                        'week_start',
                        $weekStart,
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
            ->route(
                'weekly-review.show',
            )
            ->with(
                'weekly_review_success',
                $allReviewed
                    ? 'Revisión semanal completada.'
                    : 'Paso marcado como revisado.',
            );
    }
}
