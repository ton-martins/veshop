<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCatalogController extends Controller
{
    /**
     * Display a listing of service catalogs.
     */
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $categoryId = (int) $request->integer('category_id', 0);

        $query = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->with('category:id,name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($categoryId > 0) {
            $query->where('service_category_id', $categoryId);
        }

        $services = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(static function (ServiceCatalog $service): array {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'code' => $service->code,
                    'description' => $service->description,
                    'service_category_id' => $service->service_category_id,
                    'category_name' => $service->category?->name,
                    'duration_minutes' => (int) $service->duration_minutes,
                    'base_price' => (float) $service->base_price,
                    'is_active' => (bool) $service->is_active,
                    'status_label' => $service->is_active ? 'Ativo' : 'Inativo',
                ];
            });

        $totalServices = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeServices = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $averagePrice = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->avg('base_price');

        $averageDuration = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->avg('duration_minutes');

        $categories = ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (ServiceCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Services/Catalog', [
            'services' => $services,
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId > 0 ? $categoryId : null,
            ],
            'stats' => [
                'total' => $totalServices,
                'active' => $activeServices,
                'avg_price' => $averagePrice !== null ? round((float) $averagePrice, 2) : null,
                'avg_duration' => $averageDuration !== null ? (int) round((float) $averageDuration) : null,
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

