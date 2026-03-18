<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ManualController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = trim((string) $request->string('tab')->toString());
        if (! in_array($tab, ['settings', 'finance', 'products', 'orders', 'best_practices'], true)) {
            $tab = 'settings';
        }

        return Inertia::render('Admin/Manuals/Index', [
            'initialTab' => $tab,
        ]);
    }
}
