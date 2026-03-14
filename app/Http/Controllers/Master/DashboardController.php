<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the master dashboard.
     */
    public function __invoke(): Response
    {
        $totals = [
            'contractors' => Contractor::query()->count(),
            'admins' => User::query()->where('role', User::ROLE_ADMIN)->count(),
            'masters' => User::query()->where('role', User::ROLE_MASTER)->count(),
            'active_users' => User::query()->where('is_active', true)->count(),
        ];

        return Inertia::render('Master/Home', [
            'totals' => $totals,
        ]);
    }
}
