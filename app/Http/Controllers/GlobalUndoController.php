<?php

namespace App\Http\Controllers;

use App\Support\GlobalUndoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GlobalUndoController extends Controller
{
    public function restore(
        Request $request,
        GlobalUndoService $undo,
    ): RedirectResponse {
        $validated = $request->validate([
            'undo_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $result = $undo->undo(
            $request->user(),
            isset($validated['undo_id'])
                ? (int) $validated['undo_id']
                : null,
        );

        return redirect()
            ->to($result['return_url'])
            ->with(
                'global_undo_success',
                $result['message'],
            );
    }
}
