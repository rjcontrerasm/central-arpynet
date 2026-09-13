<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\RecurringObligation;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecurringObligationFrontController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $organizationIds = $user->activeOrganizationIds();
        $selectedScope = isset($validated['scope'])
            ? (int) $validated['scope']
            : null;

        if (
            $selectedScope
            && ! in_array($selectedScope, $organizationIds, true)
        ) {
            abort(403);
        }

        $search = trim((string) ($validated['q'] ?? ''));

        $query = RecurringObligation::query()
            ->visibleTo($user)
            ->with('organization:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name');

        if ($selectedScope) {
            $query->where('organization_id', $selectedScope);
        }

        if ($search !== '') {
            $query->where(function ($subQuery) use ($search): void {
                $like = '%'.$search.'%';
                $subQuery
                    ->where('name', 'like', $like)
                    ->orWhere('provider', 'like', $like)
                    ->orWhere('reference', 'like', $like);
            });
        }

        return view('recurring-obligations-front', [
            'obligations' => $query->get(),
            'organizations' => $this->accessibleOrganizations($request),
            'selectedScope' => $selectedScope,
            'search' => $search,
            'writableOrganizationIds' => $user->writableOrganizationIds(),
        ]);
    }

    public function create(Request $request): View
    {
        $validated = $request->validate([
            'scope' => ['nullable', 'integer'],
        ]);

        $organizations = $this->writableOrganizations($request);
        abort_if($organizations->isEmpty(), 403);

        $organizationId = isset($validated['scope'])
            ? (int) $validated['scope']
            : (int) $organizations->first()->id;

        abort_unless(
            $request->user()->canWriteToOrganization($organizationId),
            403,
        );

        return view('recurring-obligation-front-form', [
            'obligation' => null,
            'organizations' => $organizations,
            'defaultOrganizationId' => $organizationId,
            'canWrite' => true,
        ]);
    }

    public function edit(
        Request $request,
        RecurringObligation $recurringObligation,
    ): View {
        abort_unless(
            $request->user()->canAccessOrganization(
                (int) $recurringObligation->organization_id,
            ),
            403,
        );

        return view('recurring-obligation-front-form', [
            'obligation' => $recurringObligation->load('organization:id,name'),
            'organizations' => $this->accessibleOrganizations($request),
            'defaultOrganizationId' => (int) $recurringObligation->organization_id,
            'canWrite' => $request->user()->canWriteToOrganization(
                (int) $recurringObligation->organization_id,
            ),
        ]);
    }

    private function accessibleOrganizations(Request $request)
    {
        return Organization::query()
            ->whereIn('id', $request->user()->activeOrganizationIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function writableOrganizations(Request $request)
    {
        return Organization::query()
            ->whereIn('id', $request->user()->writableOrganizationIds())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
