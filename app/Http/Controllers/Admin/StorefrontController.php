<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Contractor;
use App\Models\Product;
use App\Support\StorefrontSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontController extends Controller
{
    use ResolvesCurrentContractor;

    private const TAB_STOREFRONT = 'vitrine';

    private const TAB_SHIPPING = 'frete';

    public function edit(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $supportsShipping = $contractor->niche() === Contractor::NICHE_COMMERCIAL;

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $storefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);
        $tab = $this->resolveTab($request, $supportsShipping);
        $shopShipping = is_array($settings['shop_shipping'] ?? null) ? $settings['shop_shipping'] : [];

        $products = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderByDesc('is_pdv_featured')
            ->orderBy('pdv_featured_order')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'sale_price',
                'stock_quantity',
            ])
            ->map(static fn (Product $product): array => [
                'id' => (int) $product->id,
                'name' => (string) $product->name,
                'sale_price' => round((float) $product->sale_price, 2),
                'stock_quantity' => (int) $product->stock_quantity,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Storefront/Index', [
            'initialTab' => $tab,
            'supportsShipping' => $supportsShipping,
            'contractor' => [
                'id' => (int) $contractor->id,
                'name' => (string) $contractor->name,
                'brand_name' => (string) ($contractor->brand_name ?: $contractor->name),
                'slug' => (string) $contractor->slug,
                'primary_color' => (string) ($contractor->brand_primary_color ?: '#073341'),
            ],
            'storefront' => $storefront,
            'shopShipping' => [
                'pickup_enabled' => (bool) ($shopShipping['pickup_enabled'] ?? true),
                'delivery_enabled' => (bool) ($shopShipping['delivery_enabled'] ?? true),
                'fixed_fee' => (float) ($shopShipping['fixed_fee'] ?? 0),
                'free_over' => (float) ($shopShipping['free_over'] ?? 0),
                'estimated_days' => (int) ($shopShipping['estimated_days'] ?? 2),
            ],
            'products' => $products,
            'templates' => [
                [
                    'value' => StorefrontSettings::TEMPLATE_COMMERCE,
                    'label' => 'Comércio',
                    'description' => 'Foco em produtos, carrinho e ofertas.',
                ],
                [
                    'value' => StorefrontSettings::TEMPLATE_SERVICES,
                    'label' => 'Serviços',
                    'description' => 'Foco em atendimentos, agenda e solicitações.',
                ],
            ],
            'shop_url' => route('shop.show', ['slug' => $contractor->slug]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $supportsShipping = $contractor->niche() === Contractor::NICHE_COMMERCIAL;

        $section = strtolower(trim((string) $request->input('section', 'storefront')));
        if ($section === 'shipping') {
            if (! $supportsShipping) {
                throw ValidationException::withMessages([
                    'shipping' => 'Configuracao de frete disponivel apenas para contratantes do nicho comercio.',
                ]);
            }

            return $this->updateShippingSection($request, $contractor);
        }

        return $this->updateStorefrontSection($request, $contractor);
    }

    private function updateStorefrontSection(Request $request, Contractor $contractor): RedirectResponse
    {
        $validated = $request->validate([
            'hero_enabled' => ['required', 'boolean'],
            'hero_title' => ['nullable', 'string', 'max:120'],
            'hero_subtitle' => ['nullable', 'string', 'max:220'],
            'hero_cta_label' => ['nullable', 'string', 'max:40'],
            'banners_enabled' => ['required', 'boolean'],
            'banners' => ['nullable', 'array', 'max:6'],
            'banners.*.title' => ['nullable', 'string', 'max:80'],
            'banners.*.subtitle' => ['nullable', 'string', 'max:160'],
            'banners.*.badge' => ['nullable', 'string', 'max:40'],
            'banners.*.existing_image_path' => ['nullable', 'string', 'max:255'],
            'banners.*.remove_image' => ['nullable', 'boolean'],
            'banners.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'banners.*.image_url' => ['nullable', 'string', 'max:255'],
            'banners.*.cta_label' => ['nullable', 'string', 'max:40'],
            'banners.*.cta_url' => ['nullable', 'string', 'max:255'],
            'banners.*.background_color' => [
                'nullable',
                'string',
                'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
            ],
            'promotions_enabled' => ['required', 'boolean'],
            'promotions_title' => ['nullable', 'string', 'max:80'],
            'promotions_subtitle' => ['nullable', 'string', 'max:220'],
            'promotion_product_ids' => ['nullable', 'array', 'max:24'],
            'promotion_product_ids.*' => ['integer'],
            'categories_enabled' => ['required', 'boolean'],
            'catalog_enabled' => ['required', 'boolean'],
            'catalog_title' => ['nullable', 'string', 'max:80'],
            'catalog_subtitle' => ['nullable', 'string', 'max:220'],
        ]);

        $requestedPromotionIds = StorefrontSettings::normalizeProductIds($validated['promotion_product_ids'] ?? []);
        $validPromotionIds = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->whereIn('id', $requestedPromotionIds)
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if (count($validPromotionIds) !== count($requestedPromotionIds)) {
            throw ValidationException::withMessages([
                'promotion_product_ids' => 'Selecione apenas itens ativos da loja atual.',
            ]);
        }

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $previousStorefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);
        $previousBannerPaths = collect($previousStorefront['banners'] ?? [])
            ->map(fn (array $banner): ?string => $this->normalizeStoragePathForContractor(
                $banner['image_path'] ?? ($banner['image_url'] ?? null),
                $contractor
            ))
            ->filter()
            ->values()
            ->all();

        $processedBanners = $this->resolveSubmittedBanners($request, $validated, $contractor, $previousStorefront);

        $settings['shop_storefront'] = StorefrontSettings::normalize($contractor, [
            'template' => $previousStorefront['template'] ?? StorefrontSettings::defaultTemplate($contractor),
            'blocks' => [
                'hero' => (bool) $validated['hero_enabled'],
                'banners' => (bool) $validated['banners_enabled'],
                'promotions' => (bool) $validated['promotions_enabled'],
                'categories' => (bool) $validated['categories_enabled'],
                'catalog' => (bool) $validated['catalog_enabled'],
            ],
            'hero' => [
                'title' => $validated['hero_title'] ?? '',
                'subtitle' => $validated['hero_subtitle'] ?? '',
                'cta_label' => $validated['hero_cta_label'] ?? '',
            ],
            'banners' => $processedBanners,
            'promotions' => [
                'title' => $validated['promotions_title'] ?? '',
                'subtitle' => $validated['promotions_subtitle'] ?? '',
                'product_ids' => $validPromotionIds,
            ],
            'catalog' => [
                'title' => $validated['catalog_title'] ?? '',
                'subtitle' => $validated['catalog_subtitle'] ?? '',
            ],
        ]);

        $nextStorefront = $settings['shop_storefront'];
        $nextBannerPaths = collect($nextStorefront['banners'] ?? [])
            ->map(fn (array $banner): ?string => $this->normalizeStoragePathForContractor(
                $banner['image_path'] ?? ($banner['image_url'] ?? null),
                $contractor
            ))
            ->filter()
            ->values()
            ->all();

        $stalePaths = array_values(array_diff($previousBannerPaths, $nextBannerPaths));
        foreach ($stalePaths as $path) {
            $this->deleteStoragePath($path, $contractor);
        }

        $contractor->settings = $settings;
        $contractor->save();

        return back()->with('status', 'Configurações da vitrine atualizadas com sucesso.');
    }

    private function updateShippingSection(Request $request, Contractor $contractor): RedirectResponse
    {
        $validated = $request->validate([
            'shipping_pickup_enabled' => ['required', 'boolean'],
            'shipping_delivery_enabled' => ['required', 'boolean'],
            'shipping_fixed_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_free_over' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_estimated_days' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $settings['shop_shipping'] = [
            'pickup_enabled' => (bool) $validated['shipping_pickup_enabled'],
            'delivery_enabled' => (bool) $validated['shipping_delivery_enabled'],
            'fixed_fee' => round((float) $validated['shipping_fixed_fee'], 2),
            'free_over' => isset($validated['shipping_free_over'])
                ? round((float) $validated['shipping_free_over'], 2)
                : 0,
            'estimated_days' => isset($validated['shipping_estimated_days'])
                ? (int) $validated['shipping_estimated_days']
                : 2,
        ];

        $contractor->settings = $settings;
        $contractor->save();

        return back()->with('status', 'Configurações de frete atualizadas com sucesso.');
    }

    /**
     * @param array<string, mixed> $validated
     * @param array<string, mixed> $previousStorefront
     * @return array<int, array<string, string>>
     */
    private function resolveSubmittedBanners(
        Request $request,
        array $validated,
        Contractor $contractor,
        array $previousStorefront
    ): array {
        $rawBanners = is_array($validated['banners'] ?? null) ? $validated['banners'] : [];
        $previousBanners = is_array($previousStorefront['banners'] ?? null)
            ? $previousStorefront['banners']
            : [];

        $prepared = [];

        foreach ($rawBanners as $index => $banner) {
            if (! is_array($banner)) {
                continue;
            }

            $existingPath = $this->normalizeStoragePathForContractor(
                $banner['existing_image_path'] ?? ($previousBanners[$index]['image_path'] ?? ($previousBanners[$index]['image_url'] ?? null)),
                $contractor
            );
            $removeImage = (bool) ($banner['remove_image'] ?? false);
            $upload = $request->file("banners.{$index}.image_file");

            $imageUrl = trim((string) ($banner['image_url'] ?? ''));
            $imagePath = $existingPath;

            if ($upload instanceof UploadedFile) {
                if ($existingPath) {
                    $this->deleteStoragePath($existingPath, $contractor);
                }

                $imagePath = $upload->store("contractors/{$contractor->id}/storefront/banners", 'public');
                $imageUrl = Storage::disk('public')->url($imagePath);
            } elseif ($removeImage) {
                if ($existingPath) {
                    $this->deleteStoragePath($existingPath, $contractor);
                }

                $imagePath = '';
                $imageUrl = '';
            } elseif ($imageUrl !== '') {
                $pathFromUrl = $this->normalizeStoragePathForContractor($imageUrl, $contractor);
                if ($pathFromUrl) {
                    $imagePath = $pathFromUrl;
                    $imageUrl = Storage::disk('public')->url($pathFromUrl);
                } else {
                    if ($existingPath) {
                        $this->deleteStoragePath($existingPath, $contractor);
                    }

                    $imagePath = '';
                }
            } elseif ($existingPath) {
                $imagePath = $existingPath;
                $imageUrl = Storage::disk('public')->url($existingPath);
            }

            $prepared[] = [
                'title' => (string) ($banner['title'] ?? ''),
                'subtitle' => (string) ($banner['subtitle'] ?? ''),
                'badge' => (string) ($banner['badge'] ?? ''),
                'image_url' => $imageUrl,
                'image_path' => $imagePath ?: '',
                'cta_label' => (string) ($banner['cta_label'] ?? ''),
                'cta_url' => (string) ($banner['cta_url'] ?? ''),
                'background_color' => (string) ($banner['background_color'] ?? ''),
            ];
        }

        return StorefrontSettings::normalizeBanners(
            $prepared,
            (string) ($contractor->brand_primary_color ?: '#073341')
        );
    }

    private function normalizeStoragePathForContractor(mixed $value, Contractor $contractor): ?string
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

        $prefix = "contractors/{$contractor->id}/storefront/banners/";
        if (! str_starts_with($candidate, $prefix)) {
            return null;
        }

        return $candidate;
    }

    private function deleteStoragePath(string $path, Contractor $contractor): void
    {
        $normalized = $this->normalizeStoragePathForContractor($path, $contractor);
        if (! $normalized) {
            return;
        }

        if (Storage::disk('public')->exists($normalized)) {
            Storage::disk('public')->delete($normalized);
        }
    }

    private function resolveTab(Request $request, bool $supportsShipping): string
    {
        if (! $supportsShipping) {
            return self::TAB_STOREFRONT;
        }

        $tab = trim((string) $request->string('tab')->toString());

        return in_array($tab, [self::TAB_STOREFRONT, self::TAB_SHIPPING], true)
            ? $tab
            : self::TAB_STOREFRONT;
    }

}
