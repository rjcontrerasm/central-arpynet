<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarService;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleCalendarController extends Controller
{
    public function connect(
        Request $request,
        GoogleCalendarService $calendar,
    ): RedirectResponse {
        $state = Str::random(64);

        $request->session()->put(
            'google_calendar_oauth_state',
            $state,
        );

        $client = $calendar->client();
        $client->setState($state);

        return redirect()->away(
            $client->createAuthUrl(),
        );
    }

    public function callback(
        Request $request,
        GoogleCalendarService $calendar,
    ): RedirectResponse {
        $expectedState = (string) $request->session()->pull(
            'google_calendar_oauth_state',
        );

        $receivedState = (string) $request->string('state');

        if (
            blank($expectedState)
            || blank($receivedState)
            || ! hash_equals($expectedState, $receivedState)
        ) {
            return redirect()
                ->route('filament.admin.pages.integraciones')
                ->with(
                    'google_calendar_error',
                    'La validación OAuth no coincide. Intenta conectar nuevamente.',
                );
        }

        if ($request->filled('error')) {
            return redirect()
                ->route('filament.admin.pages.integraciones')
                ->with(
                    'google_calendar_error',
                    'Google no autorizó la conexión.',
                );
        }

        try {
            $calendar->exchangeCode(
                $request->user(),
                (string) $request->string('code'),
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('filament.admin.pages.integraciones')
                ->with(
                    'google_calendar_error',
                    $exception->getMessage(),
                );
        }

        return redirect()
            ->route('filament.admin.pages.integraciones')
            ->with(
                'google_calendar_success',
                'Google Calendar quedó conectado correctamente.',
            );
    }

    public function sync(
        Request $request,
        GoogleCalendarSyncService $sync,
    ): RedirectResponse {
        try {
            $result = $sync->syncUser(
                $request->user(),
            );
        } catch (Throwable $exception) {
            report($exception);

            return redirect()
                ->route('filament.admin.pages.integraciones')
                ->with(
                    'google_calendar_error',
                    $exception->getMessage(),
                );
        }

        return redirect()
            ->route('filament.admin.pages.integraciones')
            ->with(
                'google_calendar_success',
                sprintf(
                    'Sincronización completada: %d creados, %d actualizados, %d eliminados, %d sin cambios.',
                    $result['created'],
                    $result['updated'],
                    $result['deleted'],
                    $result['unchanged'],
                ),
            );
    }

    public function disconnect(
        Request $request,
        GoogleCalendarService $calendar,
    ): RedirectResponse {
        $calendar->disconnect($request->user());

        return redirect()
            ->route('filament.admin.pages.integraciones')
            ->with(
                'google_calendar_success',
                'Google Calendar fue desconectado.',
            );
    }
}
