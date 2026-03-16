<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $notifications = $user->notifications()
            ->latest('created_at')
            ->limit(120)
            ->get()
            ->map(static function ($notification): array {
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'id' => (string) $notification->id,
                    'title' => (string) ($data['title'] ?? 'Notificação'),
                    'message' => (string) ($data['message'] ?? ''),
                    'target_url' => (string) ($data['target_url'] ?? ''),
                    'read_at' => optional($notification->read_at)?->toIso8601String(),
                    'created_at' => optional($notification->created_at)?->format('d/m/Y H:i'),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => (int) $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:255'],
        ]);

        $notificationId = trim((string) ($validated['id'] ?? ''));

        if ($notificationId !== '') {
            $user->unreadNotifications()
                ->where('id', $notificationId)
                ->update([
                    'read_at' => now(),
                ]);

            return back();
        }

        $user->unreadNotifications->markAsRead();

        return back()->with('status', 'Notificações marcadas como lidas.');
    }
}
