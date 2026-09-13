<?php

namespace App\Http\Controllers;

use App\Support\ControlledDelegationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DecisionDelegationController extends Controller
{
    public function store(
        Request $request,
        ControlledDelegationService $delegation,
    ): RedirectResponse {
        $validated = $request->validate([
            'subject_type' => [
                'required',
                Rule::in([
                    'task',
                    'project',
                    'service',
                ]),
            ],
            'subject_id' => [
                'required',
                'integer',
                'min:1',
            ],
            'action' => [
                'required',
                Rule::in([
                    'start',
                    'today',
                    'tomorrow',
                    'next_week',
                    'project.next_action.set',
                    'service_order.next_action.set',
                ]),
            ],
            'next_action' => [
                'nullable',
                'string',
                'max:255',
            ],
            'next_action_at' => [
                'nullable',
                'date',
            ],
        ]);

        $payload = [];

        if (array_key_exists('next_action', $validated)) {
            $payload['next_action'] = trim(
                (string) $validated['next_action'],
            );
        }

        if (array_key_exists('next_action_at', $validated)) {
            $payload['next_action_at'] =
                $validated['next_action_at'];
        }

        $result = $delegation->delegate(
            $request->user(),
            (string) $validated['subject_type'],
            (int) $validated['subject_id'],
            (string) $validated['action'],
            $payload,
        );

        $proposal = $result['proposal'];

        $message = ($result['created'] ?? false)
            ? 'Delegación preparada. La propuesta quedó pendiente de revisión; no se ejecutó ningún cambio.'
            : 'Ya existía una propuesta pendiente idéntica. CENTRAL la reutilizó; no se ejecutó ningún cambio.';

        return redirect()
            ->route(
                'agent-proposals.index',
                [
                    'scope' =>
                        $proposal->organization_id,
                    'status' => 'pending',
                ],
            )
            ->with(
                'agent_proposal_message',
                $message,
            );
    }
}
