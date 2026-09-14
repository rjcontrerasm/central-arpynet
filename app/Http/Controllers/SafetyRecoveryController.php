<?php

namespace App\Http\Controllers;

use App\Support\SafetyRecoverySnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SafetyRecoveryController extends Controller
{
    public function index(
        Request $request,
        SafetyRecoverySnapshot $snapshot,
    ): View {
        return view('safety-recovery', [
            'snapshot' => $snapshot->build(
                $request->user(),
            ),
        ]);
    }
}
