<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Contractor;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display a listing of products.
     */
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $categoryId = (int) $request->integer('category_id', 0);

        $query = Product::query()
            ->where('contractor_id', $contractor->id)
            ->with('category:id,name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        } elseif ($status === 'out_of_stock') {
            $query->where('stock_quantity', 0);
        }

        if ($categoryId > 0) {
            $query->where('category_id', $categoryId);
        }

        $products = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(static function (Product $product): array {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'description' => $product->description,
                    'category_id' => $product->category_id,
                    'category_name' => $product->category?->name,
                    'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
                    'sale_price' => (float) $product->sale_price,
                    'stock_quantity' => (int) $product->stock_quantity,
                    'unit' => $product->unit,
                    'image_url' => $product->image_url,
                    'is_active' => (bool) $product->is_active,
                    'status_label' => $product->stock_quantity <= 0 ? 'Sem estoque' : ($product->is_active ? 'Ativo' : 'Inativo'),
                ];
            });

        $totalProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $stockoutProducts = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('stock_quantity', 0)
            ->count();

        $averageMargin = Product::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotNull('cost_price')
            ->where('cost_price', '>', 0)
            ->selectRaw('AVG(((sale_price - cost_price) / cost_price) * 100) as margin')
            ->value('margin');

        $categoryHighlights = Category::query()
            ->where('contractor_id', $contractor->id)
            ->withCount('products')
            ->orderByDesc('products_count')
            ->orderBy('name')
            ->limit(4)
            ->get()
            ->map(static function (Category $category): array {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'qty' => (int) $category->products_count,
                ];
            })
            ->values()
            ->all();

        $categoryOptions = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(static fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'categories' => $categoryOptions,
            'categoryHighlights' => $categoryHighlights,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'category_id' => $categoryId > 0 ? $categoryId : null,
            ],
            'stats' => [
                'products' => $totalProducts,
                'active' => $activeProducts,
                'stockout' => $stockoutProducts,
                'margin' => $averageMargin !== null ? round((float) $averageMargin, 1) : null,
            ],
            'units' => Product::UNITS,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        $this->assertCategoryBelongsToContractor($contractor, $data['category_id'] ?? null);
        $this->assertUniqueSkuForContractor($contractor, $data['sku'] ?? null);

        $data = $this->resolveProductImagePayload($request, $contractor, null, $data);
        $data['contractor_id'] = $contractor->id;

        Product::query()->create($data);

        return back()->with('status', 'Produto criado com sucesso.');
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $product = $this->resolveOwnedProduct($contractor, $product);

        $data = $request->validated();
        $this->assertCategoryBelongsToContractor($contractor, $data['category_id'] ?? null);
        $this->assertUniqueSkuForContractor($contractor, $data['sku'] ?? null, $product->id);

        $data = $this->resolveProductImagePayload($request, $contractor, $product, $data);
        $product->fill($data)->save();

        return back()->with('status', 'Produto atualizado com sucesso.');
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $product = $this->resolveOwnedProduct($contractor, $product);
        $this->deleteProductImage($contractor, $product->image_url);
        $product->delete();

        return back()->with('status', 'Produto removido com sucesso.');
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

    private function resolveOwnedProduct(Contractor $contractor, Product $product): Product
    {
        abort_unless((int) $product->contractor_id === (int) $contractor->id, 404);

        return $product;
    }

    private function assertCategoryBelongsToContractor(Contractor $contractor, mixed $categoryId): void
    {
        if (! $categoryId) {
            return;
        }

        $exists = Category::query()
            ->where('id', (int) $categoryId)
            ->where('contractor_id', $contractor->id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'category_id' => 'Categoria inválida para o contratante ativo.',
            ]);
        }
    }

    private function assertUniqueSkuForContractor(Contractor $contractor, ?string $sku, ?int $ignoreProductId = null): void
    {
        $safeSku = trim((string) ($sku ?? ''));
        if ($safeSku === '') {
            return;
        }

        $exists = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('sku', $safeSku)
            ->when($ignoreProductId, static fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists();

        if (! $exists) {
            return;
        }

        throw ValidationException::withMessages([
            'sku' => 'Já existe um produto com este SKU para o contratante ativo.',
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function resolveProductImagePayload(
        Request $request,
        Contractor $contractor,
        ?Product $product,
        array $data
    ): array {
        $currentImageUrl = $product?->image_url;
        $currentStoragePath = $this->normalizeProductStoragePath($contractor, $currentImageUrl);
        $removeImage = (bool) ($data['remove_image'] ?? false);
        $submittedImageUrl = trim((string) ($data['image_url'] ?? ''));
        $uploadedImage = $request->file('image_file');

        if ($uploadedImage) {
            if ($currentStoragePath) {
                $this->deleteProductImage($contractor, $currentStoragePath);
            }

            $storedPath = $uploadedImage->store("contractors/{$contractor->id}/products", 'public');
            $data['image_url'] = Storage::disk('public')->url($storedPath);
        } elseif ($removeImage) {
            if ($currentStoragePath) {
                $this->deleteProductImage($contractor, $currentStoragePath);
            }

            $data['image_url'] = null;
        } elseif ($submittedImageUrl !== '') {
            $submittedStoragePath = $this->normalizeProductStoragePath($contractor, $submittedImageUrl);

            if (! $submittedStoragePath && $currentStoragePath) {
                $this->deleteProductImage($contractor, $currentStoragePath);
            } elseif ($submittedStoragePath && $currentStoragePath && $submittedStoragePath !== $currentStoragePath) {
                $this->deleteProductImage($contractor, $currentStoragePath);
            }
        } elseif ($product) {
            $data['image_url'] = $product->image_url;
        }

        unset($data['image_file'], $data['remove_image']);

        return $data;
    }

    private function normalizeProductStoragePath(Contractor $contractor, mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        $path = parse_url($raw, PHP_URL_PATH);
        $candidate = is_string($path) && $path !== '' ? $path : $raw;

        if (str_starts_with($candidate, '/storage/')) {
            $candidate = substr($candidate, strlen('/storage/'));
        } elseif (str_starts_with($candidate, 'storage/')) {
            $candidate = substr($candidate, strlen('storage/'));
        }

        $candidate = ltrim($candidate, '/');
        if ($candidate === '') {
            return null;
        }

        $prefix = "contractors/{$contractor->id}/products/";
        if (! str_starts_with($candidate, $prefix)) {
            return null;
        }

        return $candidate;
    }

    private function deleteProductImage(Contractor $contractor, ?string $value): void
    {
        $path = $this->normalizeProductStoragePath($contractor, $value);
        if (! $path) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
