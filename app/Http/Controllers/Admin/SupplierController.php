<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    /**
     * Display a listing of suppliers.
     */
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());

        $query = Supplier::query()
            ->where('contractor_id', $contractor->id);

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $suppliers = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(static function (Supplier $supplier): array {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'email' => $supplier->email,
                    'phone' => $supplier->phone,
                    'document' => $supplier->document,
                    'category' => $supplier->category,
                    'lead_time_days' => (int) $supplier->lead_time_days,
                    'is_active' => (bool) $supplier->is_active,
                    'status_label' => $supplier->is_active ? 'Ativo' : 'Inativo',
                    'created_at' => optional($supplier->created_at)?->format('d/m/Y H:i'),
                ];
            });

        $totalSuppliers = Supplier::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeSuppliers = Supplier::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $categories = Supplier::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotNull('category')
            ->distinct('category')
            ->count('category');

        $averageLeadTime = Supplier::query()
            ->where('contractor_id', $contractor->id)
            ->avg('lead_time_days');

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => $totalSuppliers,
                'active' => $activeSuppliers,
                'categories' => $categories,
                'lead_time' => $averageLeadTime !== null ? round((float) $averageLeadTime, 1) : null,
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

