<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Controllers\Controller;
use App\Models\Contractor;
use App\Models\ServiceCatalog;
use App\Models\ServiceCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ServiceCategoryController extends Controller
{
    use ResolvesCurrentContractor;

    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());

        $query = ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->withCount('services');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(static fn (ServiceCategory $category): array => [
                'id' => (int) $category->id,
                'name' => (string) $category->name,
                'slug' => (string) $category->slug,
                'description' => $category->description ? (string) $category->description : '',
                'sort_order' => (int) $category->sort_order,
                'services_count' => (int) $category->services_count,
                'is_active' => (bool) $category->is_active,
                'status_label' => $category->is_active ? 'Ativa' : 'Inativa',
                'created_at' => optional($category->created_at)?->format('d/m/Y H:i'),
            ]);

        $totalCategories = ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeCategories = ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $categorizedServices = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotNull('service_category_id')
            ->count();

        $uncategorizedServices = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->whereNull('service_category_id')
            ->count();

        return Inertia::render('Admin/Services/Categories', [
            'categories' => $categories,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'categories' => $totalCategories,
                'active' => $activeCategories,
                'services_linked' => $categorizedServices,
                'uncategorized' => $uncategorizedServices,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $this->validatePayload($request, $contractor);
        $data['slug'] = $this->resolveUniqueSlug($contractor, (string) ($data['slug'] ?? $data['name']));
        $data['contractor_id'] = $contractor->id;

        ServiceCategory::query()->create($data);

        return back()->with('status', 'Categoria de serviço criada com sucesso.');
    }

    public function update(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceCategory = $this->resolveOwnedCategory($contractor, $serviceCategory);
        $data = $this->validatePayload($request, $contractor, (int) $serviceCategory->id);
        $data['slug'] = $this->resolveUniqueSlug(
            $contractor,
            (string) ($data['slug'] ?? $data['name']),
            (int) $serviceCategory->id
        );

        $serviceCategory->fill($data)->save();

        return back()->with('status', 'Categoria de serviço atualizada com sucesso.');
    }

    public function destroy(Request $request, ServiceCategory $serviceCategory): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $serviceCategory = $this->resolveOwnedCategory($contractor, $serviceCategory);
        $serviceCategory->delete();

        return back()->with('status', 'Categoria de serviço removida com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, Contractor $contractor, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:180'],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('service_categories', 'slug')
                    ->where(static fn ($query) => $query->where('contractor_id', $contractor->id))
                    ->ignore($ignoreId),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);
    }

    private function resolveOwnedCategory(Contractor $contractor, ServiceCategory $serviceCategory): ServiceCategory
    {
        abort_unless((int) $serviceCategory->contractor_id === (int) $contractor->id, 404);

        return $serviceCategory;
    }

    private function resolveUniqueSlug(Contractor $contractor, string $value, ?int $ignoreCategoryId = null): string
    {
        $baseSlug = Str::slug(trim($value));
        if ($baseSlug === '') {
            $baseSlug = 'categoria-servico';
        }

        $candidate = $baseSlug;
        $counter = 2;

        while ($this->slugExists($contractor, $candidate, $ignoreCategoryId)) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function slugExists(Contractor $contractor, string $slug, ?int $ignoreCategoryId = null): bool
    {
        return ServiceCategory::query()
            ->where('contractor_id', $contractor->id)
            ->where('slug', $slug)
            ->when($ignoreCategoryId, static fn ($query) => $query->where('id', '!=', $ignoreCategoryId))
            ->exists();
    }
}
