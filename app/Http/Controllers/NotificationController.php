<?php

namespace App\Http\Controllers;

use App\Application\Notifications\Services\NotificationCenterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationCenterService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function markAsRead(Request $request): RedirectResponse
    {
        return $this->service->markAsRead($request);
    }

    public function clear(Request $request): RedirectResponse
    {
        return $this->service->clear($request);
    }
}
