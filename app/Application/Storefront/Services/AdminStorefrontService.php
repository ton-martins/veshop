<?php

namespace App\Application\Storefront\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Contractor;
use App\Models\Product;
use App\Models\ServiceCatalog;
use App\Support\BrazilData;
use App\Support\StorefrontSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminStorefrontService
{
    use ResolvesCurrentContractor;

    private const TAB_STOREFRONT = 'vitrine';

    private const TAB_BUSINESS_HOURS = 'horario';

    private const TAB_SHIPPING = 'frete';

    public function __construct(
        private readonly AddressDirectoryService $addressDirectory,
    ) {
    }

    public function edit(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');
        $supportsShipping = $contractor->niche() === Contractor::NICHE_COMMERCIAL;

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $storefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);
        $tab = $this->resolveTab($request, $supportsShipping);
        $shopShipping = is_array($settings['shop_shipping'] ?? null) ? $settings['shop_shipping'] : [];
        $normalizedShopShipping = $this->normalizeShippingSettings($shopShipping);

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

        $services = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'base_price',
                'duration_minutes',
            ])
            ->map(static fn (ServiceCatalog $service): array => [
                'id' => (int) $service->id,
                'name' => (string) $service->name,
                'base_price' => round((float) $service->base_price, 2),
                'duration_minutes' => max(15, (int) ($service->duration_minutes ?? 60)),
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
            'shopShipping' => $normalizedShopShipping,
            'products' => $products,
            'services' => $services,
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
            'addressDirectory' => [
                'states' => $this->addressDirectory->stateOptions(),
                'routes' => [
                    'states' => route('admin.storefront.location.states'),
                    'cities' => route('admin.storefront.location.cities'),
                ],
            ],
        ]);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function locationStates(Request $request): array
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        return $this->addressDirectory->stateOptions();
    }

    /**
     * @return list<string>
     */
    public function locationCities(Request $request): array
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $validated = $request->validate([
            'state' => ['required', 'string', 'size:2', Rule::in(BrazilData::STATE_CODES)],
        ]);

        return $this->addressDirectory->cityNamesByState((string) $validated['state']);
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
                    'shipping' => 'Configuração de frete disponível apenas para contratantes do nicho comércio.',
                ]);
            }

            return $this->updateShippingSection($request, $contractor);
        }

        return $this->updateStorefrontSection($request, $contractor);
    }

    private function updateStorefrontSection(Request $request, Contractor $contractor): RedirectResponse
    {
        $normalizedSlug = Str::slug((string) $request->input('slug', $contractor->slug));
        if ($normalizedSlug === '') {
            $normalizedSlug = (string) $contractor->slug;
        }
        $request->merge(['slug' => $normalizedSlug]);

        $validated = $request->validate([
            'slug' => [
                'required',
                'string',
                'min:3',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('contractors', 'slug')->ignore($contractor->id),
            ],
            'template' => ['nullable', Rule::in(StorefrontSettings::templates())],
            'store_online' => ['nullable', 'boolean'],
            'offline_message' => ['nullable', 'string', 'max:240'],
            'business_hours' => ['nullable', 'array'],
            'business_hours.*.enabled' => ['nullable', 'boolean'],
            'business_hours.*.open' => ['nullable', 'date_format:H:i'],
            'business_hours.*.close' => ['nullable', 'date_format:H:i'],
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
            'banners.*.use_original_image_colors' => ['nullable', 'boolean'],
            'banners.*.background_color' => [
                'nullable',
                'string',
                'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/',
            ],
            'theme' => ['nullable', 'array'],
            'theme.menu_button_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'theme.cart_button_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'theme.favorite_button_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'theme.add_button_color' => ['nullable', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'promotions_enabled' => ['required', 'boolean'],
            'promotions_title' => ['nullable', 'string', 'max:80'],
            'promotions_subtitle' => ['nullable', 'string', 'max:220'],
            'promotion_product_ids' => ['nullable', 'array', 'max:24'],
            'promotion_product_ids.*' => ['integer'],
            'promotion_service_ids' => ['nullable', 'array', 'max:24'],
            'promotion_service_ids.*' => ['integer'],
            'categories_enabled' => ['required', 'boolean'],
            'catalog_enabled' => ['required', 'boolean'],
            'catalog_title' => ['nullable', 'string', 'max:80'],
            'catalog_subtitle' => ['nullable', 'string', 'max:220'],
        ]);

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $previousStorefront = StorefrontSettings::normalize($contractor, $settings['shop_storefront'] ?? []);

        $requestedProductPromotionIds = StorefrontSettings::normalizeProductIds($validated['promotion_product_ids'] ?? []);
        $validProductPromotionIds = Product::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->whereIn('id', $requestedProductPromotionIds)
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if (count($validProductPromotionIds) !== count($requestedProductPromotionIds)) {
            throw ValidationException::withMessages([
                'promotion_product_ids' => 'Selecione apenas produtos ativos da loja atual.',
            ]);
        }

        $requestedServicePromotionIds = StorefrontSettings::normalizeServiceIds($validated['promotion_service_ids'] ?? []);
        $validServicePromotionIds = ServiceCatalog::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->whereIn('id', $requestedServicePromotionIds)
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->values()
            ->all();

        if (count($validServicePromotionIds) !== count($requestedServicePromotionIds)) {
            throw ValidationException::withMessages([
                'promotion_service_ids' => 'Selecione apenas serviços ativos da loja atual.',
            ]);
        }

        if (is_array($validated['business_hours'] ?? null)) {
            $this->assertBusinessHoursRangeIntegrity($validated['business_hours']);
        }

        $previousBannerPaths = collect($previousStorefront['banners'] ?? [])
            ->map(fn (array $banner): ?string => $this->normalizeStoragePathForContractor(
                $banner['image_path'] ?? ($banner['image_url'] ?? null),
                $contractor
            ))
            ->filter()
            ->values()
            ->all();

        $processedBanners = $this->resolveSubmittedBanners($request, $validated, $contractor, $previousStorefront);

        $storeOnline = array_key_exists('store_online', $validated)
            ? (bool) $validated['store_online']
            : (bool) ($previousStorefront['store_online'] ?? true);
        $offlineMessage = isset($validated['offline_message'])
            ? (string) $validated['offline_message']
            : (string) ($previousStorefront['offline_message'] ?? '');
        $businessHoursSource = is_array($validated['business_hours'] ?? null)
            ? $validated['business_hours']
            : ($previousStorefront['business_hours'] ?? []);
        $normalizedBusinessHours = StorefrontSettings::normalizeBusinessHours($businessHoursSource);

        $settings['shop_storefront'] = StorefrontSettings::normalize($contractor, [
            'template' => $validated['template']
                ?? ($previousStorefront['template'] ?? StorefrontSettings::defaultTemplate($contractor)),
            'store_online' => $storeOnline,
            'offline_message' => $offlineMessage,
            'business_hours' => $normalizedBusinessHours,
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
                'product_ids' => $validProductPromotionIds,
                'service_ids' => $validServicePromotionIds,
            ],
            'catalog' => [
                'title' => $validated['catalog_title'] ?? '',
                'subtitle' => $validated['catalog_subtitle'] ?? '',
            ],
            'theme' => [
                'menu_button_color' => $validated['theme']['menu_button_color']
                    ?? ($previousStorefront['theme']['menu_button_color'] ?? '#FF5C35'),
                'cart_button_color' => $validated['theme']['cart_button_color']
                    ?? ($previousStorefront['theme']['cart_button_color'] ?? '#F58D1D'),
                'favorite_button_color' => $validated['theme']['favorite_button_color']
                    ?? ($previousStorefront['theme']['favorite_button_color'] ?? '#FF3B30'),
                'add_button_color' => $validated['theme']['add_button_color']
                    ?? ($previousStorefront['theme']['add_button_color'] ?? '#F59E0B'),
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
        $contractor->slug = (string) $validated['slug'];
        $contractor->save();

        return back()->with('status', 'Configurações da vitrine atualizadas com sucesso.');
    }

    private function updateShippingSection(Request $request, Contractor $contractor): RedirectResponse
    {
        $validated = $request->validate([
            'shipping_pickup_enabled' => ['required', 'boolean'],
            'shipping_delivery_enabled' => ['required', 'boolean'],
            'shipping_nationwide_enabled' => ['required', 'boolean'],
            'shipping_nationwide_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_nationwide_free_over' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_state_rates' => ['nullable', 'array', 'max:27'],
            'shipping_state_rates.*.state' => ['required_with:shipping_state_rates', 'string', 'size:2', Rule::in(BrazilData::STATE_CODES)],
            'shipping_state_rates.*.fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_state_rates.*.free_over' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_state_rates.*.active' => ['nullable', 'boolean'],
            'shipping_statewide_enabled' => ['nullable', 'boolean'],
            'shipping_statewide_state' => ['nullable', 'string', 'size:2'],
            'shipping_statewide_fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_statewide_free_over' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_estimated_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'shipping_city_rates' => ['nullable', 'array', 'max:500'],
            'shipping_city_rates.*.city' => ['nullable', 'string', 'max:120'],
            'shipping_city_rates.*.state' => ['nullable', 'string', 'size:2'],
            'shipping_city_rates.*.fee' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_city_rates.*.free_over' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'shipping_city_rates.*.estimated_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'shipping_city_rates.*.active' => ['nullable', 'boolean'],
            'shipping_city_rates.*.is_free' => ['nullable', 'boolean'],
        ]);

        $settings = is_array($contractor->settings) ? $contractor->settings : [];
        $defaultFixedFee = isset($validated['shipping_nationwide_fee'])
            ? round((float) $validated['shipping_nationwide_fee'], 2)
            : 0.0;
        $defaultFreeOver = isset($validated['shipping_nationwide_free_over'])
            ? round((float) $validated['shipping_nationwide_free_over'], 2)
            : 0.0;
        $defaultEstimatedDays = isset($validated['shipping_estimated_days'])
            ? (int) $validated['shipping_estimated_days']
            : 2;
        $nationwideFee = isset($validated['shipping_nationwide_fee'])
            ? round((float) $validated['shipping_nationwide_fee'], 2)
            : $defaultFixedFee;
        $nationwideFreeOver = isset($validated['shipping_nationwide_free_over'])
            ? round((float) $validated['shipping_nationwide_free_over'], 2)
            : $defaultFreeOver;
        $legacyStatewideEnabled = (bool) ($validated['shipping_statewide_enabled'] ?? false);
        $legacyStatewideState = strtoupper(trim((string) ($validated['shipping_statewide_state'] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $legacyStatewideState) !== 1) {
            $legacyStatewideState = '';
        }
        $legacyStatewideFee = isset($validated['shipping_statewide_fee'])
            ? round((float) $validated['shipping_statewide_fee'], 2)
            : $defaultFixedFee;
        $legacyStatewideFreeOver = isset($validated['shipping_statewide_free_over'])
            ? round((float) $validated['shipping_statewide_free_over'], 2)
            : $defaultFreeOver;

        $stateRatesPayload = is_array($validated['shipping_state_rates'] ?? null)
            ? $validated['shipping_state_rates']
            : [];

        if ($legacyStatewideEnabled && $legacyStatewideState === '' && $stateRatesPayload === []) {
            throw ValidationException::withMessages([
                'shipping_statewide_state' => 'Selecione a UF para entrega em todo o estado.',
            ]);
        }

        if ($stateRatesPayload === [] && $legacyStatewideEnabled && $legacyStatewideState !== '') {
            $stateRatesPayload = [[
                'state' => $legacyStatewideState,
                'fee' => $legacyStatewideFee,
                'free_over' => $legacyStatewideFreeOver,
                'active' => true,
            ]];
        }

        $stateRates = $this->normalizeShippingStateRates(
            $stateRatesPayload,
            $defaultFixedFee,
            $defaultFreeOver
        );

        $primaryActiveStateRate = collect($stateRates)->first(static fn (array $row): bool => (bool) ($row['active'] ?? false));
        $statewideEnabled = is_array($primaryActiveStateRate);
        $statewideState = $statewideEnabled ? (string) ($primaryActiveStateRate['state'] ?? '') : '';
        $statewideFee = $statewideEnabled ? max(0, round((float) ($primaryActiveStateRate['fee'] ?? 0), 2)) : 0.0;
        $statewideFreeOver = $statewideEnabled ? max(0, round((float) ($primaryActiveStateRate['free_over'] ?? 0), 2)) : 0.0;

        $cityRates = $this->normalizeShippingCityRates(
            is_array($validated['shipping_city_rates'] ?? null) ? $validated['shipping_city_rates'] : [],
            $defaultFixedFee,
            $defaultFreeOver,
            $defaultEstimatedDays,
            true
        );

        $settings['shop_shipping'] = [
            'pickup_enabled' => (bool) $validated['shipping_pickup_enabled'],
            'delivery_enabled' => (bool) $validated['shipping_delivery_enabled'],
            'nationwide_enabled' => (bool) $validated['shipping_nationwide_enabled'],
            'nationwide_fee' => max(0, $nationwideFee),
            'nationwide_free_over' => max(0, $nationwideFreeOver),
            'state_rates' => $stateRates,
            'statewide_enabled' => $statewideEnabled,
            'statewide_state' => $statewideState,
            'statewide_fee' => max(0, $statewideFee),
            'statewide_free_over' => max(0, $statewideFreeOver),
            'estimated_days' => $defaultEstimatedDays,
            'city_rates' => $cityRates,
        ];

        $contractor->settings = $settings;
        $contractor->save();

        return back()->with('status', 'Configurações de frete atualizadas com sucesso.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array<string, mixed>  $previousStorefront
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
                'background_color' => (string) ($banner['background_color'] ?? ''),
                'use_original_image_colors' => (bool) ($banner['use_original_image_colors'] ?? false),
            ];
        }

        return StorefrontSettings::normalizeBanners(
            $prepared,
            (string) ($contractor->brand_primary_color ?: '#073341')
        );
    }

    /**
     * @param  array<string, mixed>  $businessHours
     */
    private function assertBusinessHoursRangeIntegrity(array $businessHours): void
    {
        foreach (StorefrontSettings::businessHourDayKeys() as $dayKey) {
            $row = is_array($businessHours[$dayKey] ?? null) ? $businessHours[$dayKey] : [];
            $enabled = (bool) ($row['enabled'] ?? true);
            if (! $enabled) {
                continue;
            }

            $open = trim((string) ($row['open'] ?? ''));
            $close = trim((string) ($row['close'] ?? ''));
            if ($open === '' || $close === '') {
                throw ValidationException::withMessages([
                    "business_hours.{$dayKey}" => 'Informe horário inicial e final para os dias ativos.',
                ]);
            }

            $openMinutes = $this->timeToMinutes($open);
            $closeMinutes = $this->timeToMinutes($close);
            if ($closeMinutes <= $openMinutes) {
                throw ValidationException::withMessages([
                    "business_hours.{$dayKey}.close" => 'Horário final deve ser maior que o inicial.',
                ]);
            }
        }
    }

    private function timeToMinutes(string $time): int
    {
        if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', trim($time)) !== 1) {
            return 0;
        }

        [$hour, $minute] = explode(':', trim($time)) + [0, 0];

        return ((int) $hour * 60) + (int) $minute;
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

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array{state: string, fee: float, free_over: float, active: bool}>
     */
    private function normalizeShippingStateRates(array $rows, float $defaultFixedFee, float $defaultFreeOver): array
    {
        $byState = collect($rows)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static function (array $row): ?array {
                $state = strtoupper(trim((string) ($row['state'] ?? '')));
                if (! in_array($state, BrazilData::STATE_CODES, true)) {
                    return null;
                }

                $fee = isset($row['fee']) && $row['fee'] !== ''
                    ? round((float) $row['fee'], 2)
                    : null;
                $freeOver = isset($row['free_over']) && $row['free_over'] !== ''
                    ? round((float) $row['free_over'], 2)
                    : null;

                return [
                    'state' => $state,
                    'fee' => $fee,
                    'free_over' => $freeOver,
                    'active' => (bool) ($row['active'] ?? false),
                ];
            })
            ->filter()
            ->keyBy(static fn (array $row): string => $row['state']);

        return collect(BrazilData::STATE_CODES)
            ->map(static function (string $stateCode) use ($byState, $defaultFixedFee, $defaultFreeOver): array {
                /** @var array{state:string,fee:float|null,free_over:float|null,active:bool}|null $row */
                $row = $byState->get($stateCode);

                $fee = isset($row['fee']) ? (float) $row['fee'] : $defaultFixedFee;
                $freeOver = isset($row['free_over']) ? (float) $row['free_over'] : $defaultFreeOver;

                return [
                    'state' => $stateCode,
                    'fee' => max(0, round($fee, 2)),
                    'free_over' => max(0, round($freeOver, 2)),
                    'active' => (bool) ($row['active'] ?? false),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeShippingCityRates(
        array $rows,
        float $defaultFixedFee,
        float $defaultFreeOver,
        int $defaultEstimatedDays,
        bool $strictValidation = false
    ): array {
        $normalized = collect($rows)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(function (array $row, int $index) use (
                $defaultFixedFee,
                $defaultFreeOver,
                $defaultEstimatedDays,
                $strictValidation
            ): ?array {
                $city = trim((string) ($row['city'] ?? ''));
                $state = strtoupper(trim((string) ($row['state'] ?? '')));

                if ($city === '' && $state === '') {
                    return null;
                }

                if ($city === '') {
                    if ($strictValidation) {
                        throw ValidationException::withMessages([
                            "shipping_city_rates.{$index}.city" => 'Selecione uma cidade válida.',
                        ]);
                    }

                    return null;
                }

                if (preg_match('/^[A-Z]{2}$/', $state) !== 1) {
                    if ($strictValidation) {
                        throw ValidationException::withMessages([
                            "shipping_city_rates.{$index}.state" => 'Selecione uma UF válida.',
                        ]);
                    }

                    return null;
                }

                if ($strictValidation) {
                    $canonical = $this->addressDirectory->resolveCanonicalCity($state, $city);
                    if (! $canonical) {
                        throw ValidationException::withMessages([
                            "shipping_city_rates.{$index}.city" => 'Cidade/UF inválida. Selecione uma cidade existente para a UF informada.',
                        ]);
                    }

                    $state = $canonical['state_code'];
                    $city = $canonical['city'];
                }

                $active = ! array_key_exists('active', $row) || (bool) $row['active'];
                $isFree = (bool) ($row['is_free'] ?? false);
                $fee = isset($row['fee']) && $row['fee'] !== ''
                    ? round((float) $row['fee'], 2)
                    : $defaultFixedFee;
                $freeOver = isset($row['free_over']) && $row['free_over'] !== ''
                    ? round((float) $row['free_over'], 2)
                    : $defaultFreeOver;
                $estimatedDays = isset($row['estimated_days']) && (int) $row['estimated_days'] > 0
                    ? (int) $row['estimated_days']
                    : $defaultEstimatedDays;

                return [
                    'city' => $city,
                    'city_key' => $this->normalizeShippingCityKey($city),
                    'state' => $state,
                    'fee' => $isFree ? 0.0 : max(0, $fee),
                    'free_over' => $isFree ? 0.0 : max(0, $freeOver),
                    'estimated_days' => max(1, $estimatedDays),
                    'active' => $active,
                    'is_free' => $isFree,
                ];
            })
            ->filter()
            ->unique(static fn (array $row): string => $row['city_key'].'|'.$row['state'])
            ->values()
            ->all();

        return array_map(static function (array $row): array {
            unset($row['city_key']);
            return $row;
        }, $normalized);
    }

    private function normalizeShippingCityKey(string $city): string
    {
        $normalized = Str::ascii(mb_strtolower(trim($city)));
        $normalized = preg_replace('/[^a-z0-9\s-]+/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    /**
     * @param  array<string, mixed>  $shipping
     * @return array<string, mixed>
     */
    private function normalizeShippingSettings(array $shipping): array
    {
        $pickupEnabled = (bool) ($shipping['pickup_enabled'] ?? true);
        $deliveryEnabled = (bool) ($shipping['delivery_enabled'] ?? true);
        $estimatedDays = (int) ($shipping['estimated_days'] ?? 2);
        if ($estimatedDays < 1) {
            $estimatedDays = 2;
        }

        $nationwideFee = array_key_exists('nationwide_fee', $shipping)
            ? max(0, round((float) $shipping['nationwide_fee'], 2))
            : (
                array_key_exists('fixed_fee', $shipping)
                    ? max(0, round((float) $shipping['fixed_fee'], 2))
                    : 0.0
            );
        $nationwideFreeOver = array_key_exists('nationwide_free_over', $shipping)
            ? max(0, round((float) $shipping['nationwide_free_over'], 2))
            : (
                array_key_exists('free_over', $shipping)
                    ? max(0, round((float) $shipping['free_over'], 2))
                    : 0.0
            );
        $statewideFee = array_key_exists('statewide_fee', $shipping)
            ? max(0, round((float) $shipping['statewide_fee'], 2))
            : 0.0;
        $statewideFreeOver = array_key_exists('statewide_free_over', $shipping)
            ? max(0, round((float) $shipping['statewide_free_over'], 2))
            : 0.0;

        $stateRates = $this->normalizeShippingStateRates(
            is_array($shipping['state_rates'] ?? null) ? $shipping['state_rates'] : [],
            $nationwideFee,
            $nationwideFreeOver
        );

        if (! collect($stateRates)->contains(static fn (array $row): bool => (bool) ($row['active'] ?? false))) {
            $legacyStatewideState = strtoupper(trim((string) ($shipping['statewide_state'] ?? '')));
            if (preg_match('/^[A-Z]{2}$/', $legacyStatewideState) === 1 && (bool) ($shipping['statewide_enabled'] ?? false)) {
                $stateRates = array_map(
                    static function (array $row) use ($legacyStatewideState, $statewideFee, $statewideFreeOver): array {
                        if (($row['state'] ?? '') !== $legacyStatewideState) {
                            return $row;
                        }

                        $row['active'] = true;
                        $row['fee'] = $statewideFee;
                        $row['free_over'] = $statewideFreeOver;

                        return $row;
                    },
                    $stateRates
                );
            }
        }

        $primaryActiveStateRate = collect($stateRates)->first(static fn (array $row): bool => (bool) ($row['active'] ?? false));
        $statewideState = is_array($primaryActiveStateRate)
            ? strtoupper(trim((string) ($primaryActiveStateRate['state'] ?? '')))
            : '';
        $statewideEnabled = $statewideState !== '';
        if (! $statewideEnabled) {
            $statewideFee = 0.0;
            $statewideFreeOver = 0.0;
        } else {
            $statewideFee = max(0, round((float) ($primaryActiveStateRate['fee'] ?? 0), 2));
            $statewideFreeOver = max(0, round((float) ($primaryActiveStateRate['free_over'] ?? 0), 2));
        }

        $cityRates = $this->normalizeShippingCityRates(
            is_array($shipping['city_rates'] ?? null) ? $shipping['city_rates'] : [],
            $nationwideFee,
            $nationwideFreeOver,
            $estimatedDays,
            false,
        );

        return [
            'pickup_enabled' => $pickupEnabled,
            'delivery_enabled' => $deliveryEnabled,
            'nationwide_enabled' => (bool) ($shipping['nationwide_enabled'] ?? false),
            'nationwide_fee' => $nationwideFee,
            'nationwide_free_over' => $nationwideFreeOver,
            'state_rates' => $stateRates,
            'statewide_enabled' => $statewideEnabled,
            'statewide_state' => $statewideState,
            'statewide_fee' => $statewideFee,
            'statewide_free_over' => $statewideFreeOver,
            'estimated_days' => $estimatedDays,
            'city_rates' => $cityRates,
        ];
    }

    private function resolveTab(Request $request, bool $supportsShipping): string
    {
        $tab = trim((string) $request->string('tab')->toString());
        $allowedTabs = [self::TAB_STOREFRONT, self::TAB_BUSINESS_HOURS];

        if ($supportsShipping) {
            $allowedTabs[] = self::TAB_SHIPPING;
        }

        return in_array($tab, $allowedTabs, true)
            ? $tab
            : self::TAB_STOREFRONT;
    }
}
