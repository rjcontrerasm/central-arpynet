<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuickCaptureController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();

        $organizationIds = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->pluck('organization_id');

        $organizations = Organization::query()
            ->whereIn('id', $organizationIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $recentTasks = Task::query()
            ->with('organization')
            ->where('created_by', $user->id)
            ->latest('id')
            ->limit(5)
            ->get();

        $defaultOrganizationId = $user->current_organization_id;

        if (
            ! $defaultOrganizationId
            || ! $organizationIds->contains(
                $defaultOrganizationId,
            )
        ) {
            $defaultOrganizationId = $organizations
                ->first()?->id;
        }

        return view(
            'quick-capture',
            compact(
                'organizations',
                'recentTasks',
                'defaultOrganizationId',
            ),
        );
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'organization_id' => [
                'required',
                'integer',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'due_mode' => [
                'required',
                'in:today,tomorrow,next_week,custom,none',
            ],
            'due_date' => [
                'nullable',
                'date',
                'required_if:due_mode,custom',
            ],
            'urgency' => [
                'required',
                'in:low,medium,high',
            ],
            'impact' => [
                'required',
                'in:low,medium,high',
            ],
        ]);

        $allowed = DB::table('organization_user')
            ->where('user_id', $user->id)
            ->where(
                'organization_id',
                $validated['organization_id'],
            )
            ->where('is_active', true)
            ->exists();

        abort_unless($allowed, 403);

        $dueAt = $this->dueAt(
            $validated['due_mode'],
            $validated['due_date'] ?? null,
        );

        $task = new Task();

        $task->forceFill([
            'organization_id' =>
                $validated['organization_id'],
            'title' => trim($validated['title']),
            'status' => 'pending',
            'urgency' => $validated['urgency'],
            'impact' => $validated['impact'],
            'due_at' => $dueAt,
            'created_by' => $user->id,
        ]);

        $task->save();

        return redirect()
            ->route('quick-capture.show')
            ->with(
                'quick_capture_success',
                'Tarea registrada correctamente.',
            );
    }

    private function dueAt(
        string $mode,
        ?string $customDate,
    ): ?CarbonImmutable {
        $timezone = config(
            'app.timezone',
            'America/Lima',
        );

        $now = CarbonImmutable::now($timezone);

        return match ($mode) {
            'today' => $now->setTime(17, 0),
            'tomorrow' => $now
                ->addDay()
                ->setTime(17, 0),
            'next_week' => $now
                ->addWeek()
                ->setTime(17, 0),
            'custom' => CarbonImmutable::parse(
                $customDate,
                $timezone,
            )->setTime(17, 0),
            default => null,
        };
    }
}
