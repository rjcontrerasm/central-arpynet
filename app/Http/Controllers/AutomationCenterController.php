<?php

namespace App\Http\Controllers;

use App\Models\AutomationRule;
use App\Models\AutomationRuleRun;
use App\Support\AutomationRuleCatalog;
use App\Support\AutomationRuleEngine;
use App\Support\AutomationRuleExecutor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Illuminate\View\View;
use Throwable;

class AutomationCenterController extends Controller
{
    public function index(
        Request $request,
        AutomationRuleCatalog $catalog,
        AutomationRuleEngine $engine,
    ): View {
        $user = $request->user();

        $organizations = $user->organizations()
            ->wherePivot('is_active', true)
            ->where('organizations.is_active', true)
            ->orderBy('organizations.name')
            ->get();

        $organizationIds = $organizations
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $rules = AutomationRule::query()
            ->with('organization')
            ->whereIn('organization_id', $organizationIds)
            ->latest('id')
            ->get()
            ->map(function (AutomationRule $rule) use ($engine): AutomationRule {
                try {
                    $rule->setAttribute(
                        'preview_matches',
                        $engine->preview($rule)->count(),
                    );
                } catch (Throwable) {
                    $rule->setAttribute(
                        'preview_matches',
                        null,
                    );
                }

                return $rule;
            });

        $recentRuns = AutomationRuleRun::query()
            ->with('rule')
            ->whereIn('organization_id', $organizationIds)
            ->latest('id')
            ->limit(30)
            ->get();

        $counts = [
            'total' => $rules->count(),
            'active' => $rules->where('is_active', true)->count(),
            'automatic' => $rules
                ->where('mode', 'automatic')
                ->where('is_active', true)
                ->count(),
            'pending_confirmation' => $recentRuns
                ->where('outcome', 'pending_confirmation')
                ->count(),
        ];

        return view('automation-center', [
            'organizations' => $organizations,
            'rules' => $rules,
            'recentRuns' => $recentRuns,
            'counts' => $counts,
            'triggers' => $catalog->triggers(),
            'actions' => $catalog->actions(),
            'modes' => AutomationRuleCatalog::MODES,
            'catalogContract' => $catalog->contract(),
        ]);
    }

    public function store(
        Request $request,
        AutomationRuleCatalog $catalog,
    ): RedirectResponse {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:180'],
            'trigger_key' => ['required', 'string', 'max:80'],
            'action_key' => ['required', 'string', 'max:80'],
            'mode' => [
                'required',
                'string',
                'in:preview,confirmation,automatic',
            ],
            'days' => [
                'nullable',
                'integer',
                'min:0',
                'max:30',
            ],
        ]);

        $organizationId = (int) $validated['organization_id'];

        $this->authorizeOrganization(
            $request,
            $organizationId,
        );

        try {
            $catalog->validate(
                $validated['trigger_key'],
                $validated['action_key'],
                $validated['mode'],
            );
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'automation' => $e->getMessage(),
            ]);
        }

        if (
            $validated['mode'] === 'automatic'
            && ! $catalog->isAutomaticInternal(
                $validated['action_key'],
            )
        ) {
            throw ValidationException::withMessages([
                'mode' =>
                    'Esta acción todavía no puede ejecutarse automáticamente.',
            ]);
        }

        $triggerConfig = [];

        if (
            $validated['trigger_key']
                === 'obligation.due_soon'
        ) {
            $triggerConfig['days'] = (int) (
                $validated['days'] ?? 7
            );
        }

        AutomationRule::query()->create([
            'organization_id' => $organizationId,
            'name' => trim($validated['name']),
            'trigger_key' => $validated['trigger_key'],
            'action_key' => $validated['action_key'],
            'trigger_config' => $triggerConfig,
            'action_config' => [],
            'mode' => $validated['mode'],
            'is_active' => false,
            'created_by' => $request->user()->id,
        ]);

        return redirect()
            ->route('automation-center.index')
            ->with(
                'automation_success',
                'Regla creada en estado inactivo. Revísala antes de activarla.',
            );
    }

    public function toggle(
        Request $request,
        AutomationRule $automationRule,
    ): RedirectResponse {
        $this->authorizeOrganization(
            $request,
            (int) $automationRule->organization_id,
        );

        $automationRule->forceFill([
            'is_active' => ! $automationRule->is_active,
        ])->save();

        return redirect()
            ->route('automation-center.index')
            ->with(
                'automation_success',
                $automationRule->is_active
                    ? 'Regla activada.'
                    : 'Regla desactivada.',
            );
    }

    public function preview(
        Request $request,
        AutomationRule $automationRule,
        AutomationRuleEngine $engine,
    ): RedirectResponse {
        $this->authorizeOrganization(
            $request,
            (int) $automationRule->organization_id,
        );

        $matches = $engine->preview($automationRule);

        return redirect()
            ->route('automation-center.index')
            ->with('automation_preview', [
                'rule_id' => $automationRule->id,
                'rule_name' => $automationRule->name,
                'matches' => $matches->count(),
                'examples' => $matches
                    ->take(5)
                    ->map(
                        fn (array $item): array => [
                            'title' => $item['title'],
                            'reason' => $item['reason'],
                        ],
                    )
                    ->values()
                    ->all(),
            ]);
    }

    public function run(
        Request $request,
        AutomationRule $automationRule,
        AutomationRuleExecutor $executor,
    ): RedirectResponse {
        $this->authorizeOrganization(
            $request,
            (int) $automationRule->organization_id,
        );

        if (! $automationRule->is_active) {
            throw ValidationException::withMessages([
                'automation' =>
                    'La regla debe estar activa para evaluarla.',
            ]);
        }

        $result = $executor->runRule(
            $automationRule,
            100,
        );

        return redirect()
            ->route('automation-center.index')
            ->with('automation_run', $result);
    }

    private function authorizeOrganization(
        Request $request,
        int $organizationId,
    ): void {
        $allowed = DB::table('organization_user')
            ->where('user_id', $request->user()->id)
            ->where('organization_id', $organizationId)
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403);
    }
}
