<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationCenterController extends Controller
{
    public function index(Request $request): View
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(30);

        $unreadCount = $request->user()
            ->unreadNotifications()
            ->count();

        return view(
            'notification-center',
            compact(
                'notifications',
                'unreadCount',
            ),
        );
    }

    public function read(
        Request $request,
        string $notification,
    ): RedirectResponse {
        $record = $request->user()
            ->notifications()
            ->whereKey($notification)
            ->firstOrFail();

        if ($record->read_at === null) {
            $record->markAsRead();
        }

        $url = $record->data['url'] ?? null;

        if (
            is_string($url)
            && str_starts_with(
                $url,
                config('app.url'),
            )
        ) {
            return redirect()->to($url);
        }

        return redirect()
            ->route('notification-center.index');
    }

    public function readAll(
        Request $request,
    ): RedirectResponse {
        $request->user()
            ->unreadNotifications
            ->markAsRead();

        return redirect()
            ->route('notification-center.index')
            ->with(
                'notification_success',
                'Notificaciones marcadas como leídas.',
            );
    }
}
