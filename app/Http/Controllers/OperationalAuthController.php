<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class OperationalAuthController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('daily-ops.show');
        }

        return view('auth.operational-login');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
            ],
            'remember' => [
                'nullable',
                'boolean',
            ],
        ]);

        $credentials = [
            'email' => mb_strtolower(
                trim($validated['email']),
            ),
            'password' => $validated['password'],
            'is_active' => true,
        ];

        if (! Auth::attempt(
            $credentials,
            (bool) ($validated['remember'] ?? false),
        )) {
            throw ValidationException::withMessages([
                'email' =>
                    'El correo o la contraseña no son correctos.',
            ]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $user || $user->activeOrganizationIds() === []) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'email' =>
                    'Tu usuario no tiene una empresa activa asignada.',
            ]);
        }

        return redirect()->intended(
            route('daily-ops.show'),
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
