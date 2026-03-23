<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /**
     * Redirect authenticated users to the proper dashboard area.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user?->isMaster()) {
            return redirect()->route('master.home');
        }

        return redirect()->route('admin.home');
    }
}
