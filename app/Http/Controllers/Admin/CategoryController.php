<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Contractor;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());

        $query = Category::query()
            ->where('contractor_id', $contractor->id)
            ->with('parent:id,name')
            ->withCount('products')
            ->withCount('children');

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
            ->through(static function (Category $category): array {
                return [
                    'id' => $category->id,
                    'parent_id' => $category->parent_id ? (int) $category->parent_id : null,
                    'parent_name' => $category->parent?->name !== null ? (string) $category->parent->name : null,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'products_count' => (int) $category->products_count,
                    'children_count' => (int) $category->children_count,
                    'is_active' => (bool) $category->is_active,
                    'status_label' => $category->is_active ? 'Ativa' : 'Inativa',
                    'created_at' => optional($category->created_at)?->format('d/m/Y H:i'),
                ];
            });

        $totalCategories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeCategories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $linkedProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotNull('category_id')
            ->count();

        $uncategorizedProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->whereNull('category_id')
            ->count();

        $rootCategories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->whereNull('parent_id')
            ->count();

        $subcategories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotNull('parent_id')
            ->count();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'parentOptions' => $this->resolveParentOptions($contractor),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'categories' => $totalCategories,
                'root' => $rootCategories,
                'subcategories' => $subcategories,
                'active' => $activeCategories,
                'products_linked' => $linkedProducts,
                'uncategorized' => $uncategorizedProducts,
            ],
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        $this->assertParentBelongsToContractor($contractor, $data['parent_id'] ?? null);
        $slugBase = $data['slug'] ?: $data['name'];
        $data['slug'] = $this->resolveUniqueSlug($contractor, $slugBase);
        $data['contractor_id'] = $contractor->id;

        Category::query()->create($data);

        return back()->with('status', 'Categoria criada com sucesso.');
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $category = $this->resolveOwnedCategory($contractor, $category);

        $data = $request->validated();
        $this->assertParentBelongsToContractor($contractor, $data['parent_id'] ?? null, $category->id);
        $this->assertNoCategoryHierarchyCycle($contractor, $category, $data['parent_id'] ?? null);
        $slugBase = $data['slug'] ?: $data['name'];
        $data['slug'] = $this->resolveUniqueSlug($contractor, $slugBase, $category->id);

        $category->fill($data)->save();

        return back()->with('status', 'Categoria atualizada com sucesso.');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $category = $this->resolveOwnedCategory($contractor, $category);
        $category->delete();

        return back()->with('status', 'Categoria removida com sucesso.');
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

    private function resolveOwnedCategory(Contractor $contractor, Category $category): Category
    {
        abort_unless((int) $category->contractor_id === (int) $contractor->id, 404);

        return $category;
    }

    private function resolveUniqueSlug(Contractor $contractor, string $value, ?int $ignoreCategoryId = null): string
    {
        $baseSlug = Str::slug(trim($value));
        if ($baseSlug === '') {
            $baseSlug = 'categoria';
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
        return Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('slug', $slug)
            ->when($ignoreCategoryId, static fn ($query) => $query->where('id', '!=', $ignoreCategoryId))
            ->exists();
    }

    private function assertParentBelongsToContractor(Contractor $contractor, mixed $parentId, ?int $currentCategoryId = null): void
    {
        if (! $parentId) {
            return;
        }

        $safeParentId = (int) $parentId;

        if ($currentCategoryId !== null && $safeParentId === $currentCategoryId) {
            throw ValidationException::withMessages([
                'parent_id' => 'A categoria não pode ser filha dela mesma.',
            ]);
        }

        $exists = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('id', $safeParentId)
            ->exists();

        if ($exists) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_id' => 'Subcategoria inválida para o contratante ativo.',
        ]);
    }

    private function assertNoCategoryHierarchyCycle(Contractor $contractor, Category $category, mixed $parentId): void
    {
        if (! $parentId) {
            return;
        }

        $visited = collect([(int) $category->id]);
        $currentParentId = (int) $parentId;

        while ($currentParentId > 0) {
            if ($visited->contains($currentParentId)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Não é possível criar ciclo entre categorias e subcategorias.',
                ]);
            }

            $visited->push($currentParentId);

            $nextParentId = Category::query()
                ->where('contractor_id', $contractor->id)
                ->where('id', $currentParentId)
                ->value('parent_id');

            $currentParentId = (int) ($nextParentId ?? 0);
        }
    }

    /**
     * @return list<array{id:int,name:string,parent_id:int|null}>
     */
    private function resolveParentOptions(Contractor $contractor): array
    {
        $categories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);

        $groupedByParent = $categories
            ->groupBy(static fn (Category $category): int => (int) ($category->parent_id ?? 0));

        $flattened = [];
        $appendChildren = function (int $parentId, int $depth) use (&$appendChildren, &$flattened, $groupedByParent): void {
            /** @var Collection<int, Category> $children */
            $children = $groupedByParent->get($parentId, collect());

            foreach ($children as $child) {
                $prefix = str_repeat('— ', max(0, $depth));
                $flattened[] = [
                    'id' => (int) $child->id,
                    'name' => trim("{$prefix}{$child->name}"),
                    'parent_id' => $child->parent_id ? (int) $child->parent_id : null,
                ];

                $appendChildren((int) $child->id, $depth + 1);
            }
        };

        $appendChildren(0, 0);

        return $flattened;
    }
}
