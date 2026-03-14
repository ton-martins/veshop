<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\ServiceCatalog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $isServices = $contractor->hasModule(Contractor::MODULE_SERVICES);

        $commercialOverview = [
            'operations' => [
                'orders_today' => 0,
                'in_production' => 0,
                'monthly_revenue' => 0,
                'clients' => Client::query()
                    ->where('contractor_id', $contractor->id)
                    ->count(),
                'pending_quotes' => 0,
                'deliveries_today' => 0,
                'recent_orders' => [],
            ],
            'pdv' => [
                'sales_today' => 0,
                'sales_count' => 0,
                'avg_ticket' => 0,
                'cash_open' => false,
                'pending_quotes' => 0,
                'recent_sales' => [],
                'payment_summary' => [
                    'pix' => 0,
                    'credit' => 0,
                    'cash' => 0,
                ],
            ],
        ];

        $serviceCatalogCount = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $serviceActiveCount = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $serviceAveragePrice = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->avg('base_price');

        $servicesOverview = [
            'stats' => [
                'open_orders' => 0,
                'today' => 0,
                'catalog' => $serviceCatalogCount,
                'revenue' => 0,
                'active_services' => $serviceActiveCount,
                'avg_price' => $serviceAveragePrice !== null ? (float) $serviceAveragePrice : 0.0,
            ],
            'queue' => [],
        ];

        $quickTotals = [
            'products' => Product::query()
                ->where('contractor_id', $contractor->id)
                ->count(),
            'suppliers' => Supplier::query()
                ->where('contractor_id', $contractor->id)
                ->count(),
        ];

        return Inertia::render('Admin/Home', [
            'isServices' => $isServices,
            'overview' => [
                'commercial' => $commercialOverview,
                'services' => $servicesOverview,
                'quick_totals' => $quickTotals,
            ],
        ]);
    }

    private function resolveCurrentContractor(Request $request): ?Contractor
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $user->loadMissing('contractors');
        $availableContractors = $user->contractors->values();

        if ($availableContractors->isEmpty()) {
            return null;
        }

        $sessionContractorId = (int) $request->session()->get('current_contractor_id', 0);
        if ($sessionContractorId > 0) {
            $selected = $availableContractors->firstWhere('id', $sessionContractorId);
            if ($selected) {
                return $selected;
            }
        }

        $fallback = $availableContractors->first();
        if ($fallback) {
            $request->session()->put('current_contractor_id', $fallback->id);
        }

        return $fallback;
    }
}
