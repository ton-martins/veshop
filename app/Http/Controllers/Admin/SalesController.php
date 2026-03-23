<?php

namespace App\Http\Controllers\Admin;

use App\Application\Sales\Services\AdminSalesService;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class SalesController extends Controller
{
    public function __construct(
        private readonly AdminSalesService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function update(Request $request, Sale $sale): RedirectResponse
    {
        return $this->service->update($request, $sale);
    }
}
