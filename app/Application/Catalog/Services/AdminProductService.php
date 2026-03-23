<?php

namespace App\Application\Catalog\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariation;
use App\Services\ContractorStorageQuotaService;
use App\Services\ProductImageProcessor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductService
{
    use ResolvesCurrentContractor;

    public function __construct(
        private readonly ContractorStorageQuotaService $storageQuotaService,
        private readonly ProductImageProcessor $imageProcessor,
    ) {}

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
            ->with([
                'category:id,name,parent_id',
                'images:id,product_id,image_url,image_path,sort_order',
                'variations:id,product_id,name,sku,sale_price,stock_quantity,is_active,sort_order,attributes',
            ]);

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('variations', function ($variationQuery) use ($search): void {
                        $variationQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('sku', 'like', "%{$search}%");
                    });
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
            ->through(fn (Product $product): array => $this->toProductPayload($product));

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

        $categoryOptions = $this->resolveCategoryOptions($contractor);
        $storageLimitBytes = $this->storageQuotaService->resolveLimitBytes($contractor);
        $storageUsageBytes = $this->storageQuotaService->resolveUsageBytes($contractor);
        $storageRemainingBytes = $storageLimitBytes !== null
            ? max(0, $storageLimitBytes - $storageUsageBytes)
            : null;
        $galleryLimitPerProduct = min(
            5,
            max(1, $this->storageQuotaService->resolveProductImageLimitByPlan($contractor))
        );

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
            'storage' => [
                'limit_bytes' => $storageLimitBytes,
                'usage_bytes' => $storageUsageBytes,
                'remaining_bytes' => $storageRemainingBytes,
                'gallery_limit_per_product' => $galleryLimitPerProduct,
                'gallery_technical_limit' => 5,
            ],
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

        $variations = $this->resolveVariationRowsFromPayload($data);
        $this->assertUniqueVariationSkuForContractor($contractor, $variations);

        $createdPaths = [];

        try {
            DB::transaction(function () use ($request, $contractor, $data, $variations, &$createdPaths): void {
                $productData = $this->sanitizeBaseProductData($data);
                $productData['contractor_id'] = $contractor->id;
                $productData = $this->resolveMainImagePayload($request, $contractor, null, $productData, $createdPaths);

                $product = Product::query()->create($productData);

                $this->syncProductGallery($request, $contractor, $product, $data, $createdPaths);
                $this->syncProductVariations($contractor, $product, $variations);
                $this->refreshProductStockAndPriceByVariations($product);
            });
        } catch (\Throwable $exception) {
            $this->cleanupCreatedPaths($createdPaths);
            throw $exception;
        }

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

        $variations = $this->resolveVariationRowsFromPayload($data);
        $this->assertUniqueVariationSkuForContractor($contractor, $variations, $product->id);

        $createdPaths = [];

        try {
            DB::transaction(function () use ($request, $contractor, $product, $data, $variations, &$createdPaths): void {
                $productData = $this->sanitizeBaseProductData($data);
                $productData = $this->resolveMainImagePayload($request, $contractor, $product, $productData, $createdPaths);

                $product->fill($productData)->save();

                $this->syncProductGallery($request, $contractor, $product, $data, $createdPaths);
                $this->syncProductVariations($contractor, $product, $variations);
                $this->refreshProductStockAndPriceByVariations($product);
            });
        } catch (\Throwable $exception) {
            $this->cleanupCreatedPaths($createdPaths);
            throw $exception;
        }

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

        foreach ($product->images as $image) {
            $this->deleteProductImage($contractor, (string) $image->image_path);
        }

        $this->deleteProductImage($contractor, $product->image_url);
        $product->delete();

        return back()->with('status', 'Produto removido com sucesso.');
    }

    private function resolveOwnedProduct(Contractor $contractor, Product $product): Product
    {
        abort_unless((int) $product->contractor_id === (int) $contractor->id, 404);

        return $product->loadMissing([
            'images:id,product_id,image_url,image_path,sort_order',
            'variations:id,product_id,name,sku,sale_price,stock_quantity,is_active,sort_order,attributes',
        ]);
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
     * @param  list<array<string, mixed>>  $variationRows
     */
    private function assertUniqueVariationSkuForContractor(
        Contractor $contractor,
        array $variationRows,
        ?int $currentProductId = null
    ): void {
        $rowsWithSku = collect($variationRows)
            ->map(static function (array $row): array {
                return [
                    'id' => isset($row['id']) && $row['id'] ? (int) $row['id'] : null,
                    'sku' => trim((string) ($row['sku'] ?? '')),
                    'name' => trim((string) ($row['name'] ?? '')),
                ];
            })
            ->filter(static fn (array $row): bool => $row['sku'] !== '')
            ->values();

        if ($rowsWithSku->isEmpty()) {
            return;
        }

        $duplicateInPayload = $rowsWithSku
            ->groupBy(static fn (array $row): string => mb_strtolower($row['sku']))
            ->first(static fn (Collection $items): bool => $items->count() > 1);

        if ($duplicateInPayload) {
            throw ValidationException::withMessages([
                'variations' => 'Existem SKUs de variação duplicados no envio.',
            ]);
        }

        $skuList = $rowsWithSku
            ->map(static fn (array $row): string => $row['sku'])
            ->values();

        $ignoreVariationIds = $rowsWithSku
            ->pluck('id')
            ->filter(static fn (mixed $id): bool => (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $existing = ProductVariation::query()
            ->where('contractor_id', $contractor->id)
            ->whereIn('sku', $skuList->all())
            ->when($ignoreVariationIds !== [], static fn ($query) => $query->whereNotIn('id', $ignoreVariationIds))
            ->when($currentProductId, static fn ($query, int $productId) => $query->where('product_id', '!=', $productId))
            ->exists();

        if ($existing) {
            throw ValidationException::withMessages([
                'variations' => 'Já existe SKU de variação em uso para este contratante.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private function resolveVariationRowsFromPayload(array $data): array
    {
        return collect($data['variations'] ?? [])
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static function (array $row): array {
                return [
                    'id' => isset($row['id']) && $row['id'] ? (int) $row['id'] : null,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'sku' => trim((string) ($row['sku'] ?? '')),
                    'sale_price' => round((float) ($row['sale_price'] ?? 0), 2),
                    'cost_price' => isset($row['cost_price']) && $row['cost_price'] !== ''
                        ? round((float) $row['cost_price'], 2)
                        : null,
                    'stock_quantity' => max(0, (int) ($row['stock_quantity'] ?? 0)),
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                    'attributes' => is_array($row['attributes'] ?? null) ? $row['attributes'] : [],
                ];
            })
            ->filter(static fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizeBaseProductData(array $data): array
    {
        return [
            'category_id' => $data['category_id'] ?? null,
            'name' => (string) ($data['name'] ?? ''),
            'sku' => isset($data['sku']) && trim((string) $data['sku']) !== '' ? trim((string) $data['sku']) : null,
            'description' => $data['description'] ?? null,
            'cost_price' => isset($data['cost_price']) && $data['cost_price'] !== '' ? (float) $data['cost_price'] : null,
            'sale_price' => round((float) ($data['sale_price'] ?? 0), 2),
            'stock_quantity' => max(0, (int) ($data['stock_quantity'] ?? 0)),
            'unit' => (string) ($data['unit'] ?? Product::UNITS[0]),
            'image_url' => $data['image_url'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $createdPaths
     * @return array<string, mixed>
     */
    private function resolveMainImagePayload(
        Request $request,
        Contractor $contractor,
        ?Product $product,
        array $data,
        array &$createdPaths
    ): array {
        $currentImageUrl = $product?->image_url;
        $currentStoragePath = $this->normalizeProductStoragePath($contractor, $currentImageUrl);
        $removeImage = (bool) ($data['remove_image'] ?? false);
        $submittedImageUrl = trim((string) ($data['image_url'] ?? ''));
        $uploadedImage = $request->file('image_file');

        if ($uploadedImage instanceof UploadedFile) {
            $this->storageQuotaService->assertCanStoreBytes($contractor, (int) ($uploadedImage->getSize() ?? 0));

            if ($currentStoragePath) {
                $this->deleteProductImage($contractor, $currentStoragePath);
            }

            $processed = $this->imageProcessor->processAndStore($uploadedImage, $contractor, 'products/main');
            $createdPaths[] = $processed['path'];
            $data['image_url'] = $processed['url'];
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

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $createdPaths
     */
    private function syncProductGallery(
        Request $request,
        Contractor $contractor,
        Product $product,
        array $data,
        array &$createdPaths
    ): void {
        $limitPerProduct = min(
            5,
            max(1, $this->storageQuotaService->resolveProductImageLimitByPlan($contractor))
        );

        $existingImages = $product->images()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $removeIds = collect($data['remove_gallery_ids'] ?? [])
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($removeIds->isNotEmpty()) {
            $imagesToRemove = $existingImages->whereIn('id', $removeIds->all());
            $notOwnedIds = $removeIds->diff($imagesToRemove->pluck('id')->map(static fn ($id) => (int) $id));
            if ($notOwnedIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'remove_gallery_ids' => 'Há imagens inválidas para remoção.',
                ]);
            }

            foreach ($imagesToRemove as $image) {
                $this->deleteProductImage($contractor, (string) ($image->image_path ?? ''));
                $image->delete();
            }

            $existingImages = $existingImages->whereNotIn('id', $removeIds->all())->values();
        }

        $uploadedFiles = collect($request->file('gallery_files', []))
            ->filter(static fn (mixed $file): bool => $file instanceof UploadedFile)
            ->values();

        if (($existingImages->count() + $uploadedFiles->count()) > $limitPerProduct) {
            throw ValidationException::withMessages([
                'gallery_files' => "Limite de {$limitPerProduct} fotos por produto para o plano atual.",
            ]);
        }

        if ($uploadedFiles->count() > 5) {
            throw ValidationException::withMessages([
                'gallery_files' => 'Limite técnico de 5 fotos por produto.',
            ]);
        }

        $sortBase = (int) ($existingImages->max('sort_order') ?? -1) + 1;

        foreach ($uploadedFiles as $index => $upload) {
            $this->storageQuotaService->assertCanStoreBytes($contractor, (int) ($upload->getSize() ?? 0));

            $processed = $this->imageProcessor->processAndStore($upload, $contractor, 'products/gallery');
            $createdPaths[] = $processed['path'];

            ProductImage::query()->create([
                'contractor_id' => $contractor->id,
                'product_id' => $product->id,
                'image_path' => $processed['path'],
                'image_url' => $processed['url'],
                'sort_order' => $sortBase + $index,
            ]);
        }

        $product->load('images');
        $allImages = $product->images
            ->sortBy('sort_order')
            ->values();

        foreach ($allImages as $position => $image) {
            if ((int) $image->sort_order !== $position) {
                $image->sort_order = $position;
                $image->save();
            }
        }

        $primaryImage = $allImages->first();
        if ($primaryImage) {
            $product->image_url = $primaryImage->image_url ?: Storage::disk('public')->url($primaryImage->image_path);
            $product->save();
        } elseif (! $product->image_url) {
            $product->image_url = null;
            $product->save();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $variationRows
     */
    private function syncProductVariations(Contractor $contractor, Product $product, array $variationRows): void
    {
        $existingById = $product->variations()
            ->get()
            ->keyBy('id');

        $keptIds = [];
        $sortOrder = 0;

        foreach ($variationRows as $row) {
            $variationId = isset($row['id']) && $row['id'] ? (int) $row['id'] : null;
            $variation = null;

            if ($variationId) {
                $variation = $existingById->get($variationId);
                if (! $variation) {
                    throw ValidationException::withMessages([
                        'variations' => 'Uma ou mais variações não pertencem a este produto.',
                    ]);
                }
            }

            $payload = [
                'contractor_id' => $contractor->id,
                'product_id' => $product->id,
                'name' => trim((string) ($row['name'] ?? '')),
                'sku' => trim((string) ($row['sku'] ?? '')) !== '' ? trim((string) $row['sku']) : null,
                'attributes' => is_array($row['attributes'] ?? null) ? $row['attributes'] : [],
                'cost_price' => isset($row['cost_price']) && $row['cost_price'] !== ''
                    ? round((float) $row['cost_price'], 2)
                    : null,
                'sale_price' => round((float) ($row['sale_price'] ?? 0), 2),
                'stock_quantity' => max(0, (int) ($row['stock_quantity'] ?? 0)),
                'is_active' => (bool) ($row['is_active'] ?? true),
                'sort_order' => $sortOrder++,
            ];

            if ($variation) {
                $variation->fill($payload)->save();
                $keptIds[] = (int) $variation->id;

                continue;
            }

            $created = ProductVariation::query()->create($payload);
            $keptIds[] = (int) $created->id;
        }

        if ($keptIds === []) {
            $product->variations()->delete();

            return;
        }

        $product->variations()
            ->whereNotIn('id', $keptIds)
            ->delete();
    }

    private function refreshProductStockAndPriceByVariations(Product $product): void
    {
        $product->load('variations');
        $activeVariations = $product->variations->where('is_active', true)->values();

        if ($activeVariations->isEmpty()) {
            return;
        }

        $product->stock_quantity = (int) $activeVariations->sum(static fn (ProductVariation $variation): int => (int) $variation->stock_quantity);
        $product->sale_price = (float) $activeVariations
            ->min(static fn (ProductVariation $variation): float => (float) $variation->sale_price);
        $product->save();
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

    /**
     * @param  list<string>  $paths
     */
    private function cleanupCreatedPaths(array $paths): void
    {
        foreach ($paths as $path) {
            if (trim((string) $path) === '') {
                continue;
            }

            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        }
    }

    /**
     * @return list<array{id:int,name:string,parent_id:int|null}>
     */
    private function resolveCategoryOptions(Contractor $contractor): array
    {
        $categories = Category::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
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

    /**
     * @return array<string, mixed>
     */
    private function toProductPayload(Product $product): array
    {
        $images = $product->images
            ->sortBy('sort_order')
            ->values()
            ->map(static fn (ProductImage $image): array => [
                'id' => (int) $image->id,
                'image_url' => (string) ($image->image_url ?? ''),
                'image_path' => (string) ($image->image_path ?? ''),
                'sort_order' => (int) $image->sort_order,
            ])
            ->all();

        if ($images === [] && trim((string) $product->image_url) !== '') {
            $images[] = [
                'id' => 0,
                'image_url' => (string) $product->image_url,
                'image_path' => '',
                'sort_order' => 0,
            ];
        }

        $variations = $product->variations
            ->sortBy('sort_order')
            ->values()
            ->map(static fn (ProductVariation $variation): array => [
                'id' => (int) $variation->id,
                'name' => (string) $variation->name,
                'sku' => $variation->sku !== null ? (string) $variation->sku : null,
                'sale_price' => (float) $variation->sale_price,
                'cost_price' => $variation->cost_price !== null ? (float) $variation->cost_price : null,
                'stock_quantity' => (int) $variation->stock_quantity,
                'is_active' => (bool) $variation->is_active,
                'sort_order' => (int) $variation->sort_order,
                'attributes' => is_array($variation->attributes) ? $variation->attributes : [],
            ])
            ->all();

        $hasActiveVariations = collect($variations)->contains(static fn (array $variation): bool => (bool) ($variation['is_active'] ?? false));
        $variationStock = (int) collect($variations)
            ->filter(static fn (array $variation): bool => (bool) ($variation['is_active'] ?? false))
            ->sum(static fn (array $variation): int => (int) ($variation['stock_quantity'] ?? 0));

        $displayStock = $hasActiveVariations
            ? $variationStock
            : (int) $product->stock_quantity;

        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => $product->description,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
            'sale_price' => (float) $product->sale_price,
            'stock_quantity' => $displayStock,
            'unit' => $product->unit,
            'image_url' => $product->image_url,
            'images' => $images,
            'variations' => $variations,
            'has_variations' => $hasActiveVariations || $variations !== [],
            'is_active' => (bool) $product->is_active,
            'status_label' => $displayStock <= 0 ? 'Sem estoque' : ($product->is_active ? 'Ativo' : 'Inativo'),
        ];
    }
}
