<?php

namespace App\Application\Notifications\Services;

use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationCenterService
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user, 403);

        $contractorId = $this->resolveCurrentContractorId($request);

        $notificationsQuery = $user->notifications()->latest('created_at');
        $unreadQuery = $user->unreadNotifications();

        if ($contractorId !== null) {
            $notificationsQuery->where('data->contractor_id', $contractorId);
            $unreadQuery->where('data->contractor_id', $contractorId);
        }

        $notifications = $notificationsQuery
            ->paginate(20)
            ->withQueryString()
            ->through(static function ($notification): array {
                $data = is_array($notification->data) ? $notification->data : [];

                return [
                    'id' => (string) $notification->id,
                    'title' => (string) ($data['title'] ?? 'Notificação'),
                    'message' => (string) ($data['message'] ?? ''),
                    'target_url' => (string) ($data['target_url'] ?? ''),
                    'read_at' => optional($notification->read_at)?->toIso8601String(),
                    'created_at' => optional($notification->created_at)?->format('d/m/Y H:i'),
                ];
            });

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => $notifications,
            'unread_count' => (int) $unreadQuery->count(),
        ]);
    }

    public function markAsRead(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $contractorId = $this->resolveCurrentContractorId($request);

        $validated = $request->validate([
            'id' => ['nullable', 'string', 'max:255'],
        ]);

        $notificationId = trim((string) ($validated['id'] ?? ''));

        if ($notificationId !== '') {
            $query = $user->unreadNotifications()
                ->where('id', $notificationId);

            if ($contractorId !== null) {
                $query->where('data->contractor_id', $contractorId);
            }

            $query->update([
                'read_at' => now(),
            ]);

            return back();
        }

        $query = $user->unreadNotifications();

        if ($contractorId !== null) {
            $query->where('data->contractor_id', $contractorId);
        }

        $query->update([
            'read_at' => now(),
        ]);

        return back()->with('status', 'Notificações marcadas como lidas.');
    }

    private function resolveCurrentContractorId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user || $user->isMaster()) {
            return null;
        }

        $user->loadMissing('contractors:id');
        $availableContractors = $user->contractors->values();

        if ($availableContractors->isEmpty()) {
            return null;
        }

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        if ($sessionContractorId > 0) {
            $selected = $availableContractors->firstWhere('id', $sessionContractorId);
            if ($selected) {
                return (int) $selected->id;
            }
        }

        $fallback = $availableContractors->first();
        if (! $fallback instanceof Contractor) {
            return null;
        }

        $request->session()->put('current_contractor_id', $fallback->id);

        return (int) $fallback->id;
    }
}
