<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreContractorRequest;
use App\Http\Requests\Master\UpdateContractorRequest;
use App\Models\Contractor;
use App\Models\Plan;
use App\Models\User;
use App\Services\ContractorCapabilitiesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
    public function __construct(
        private readonly ContractorCapabilitiesService $contractorCapabilitiesService,
    ) {
    }

    /**
     * Display a listing of contractors.
     */
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $search = trim((string) $request->string('search')->toString());
        $niche = trim((string) $request->string('niche')->toString());
        $status = trim((string) $request->string('status')->toString());
        $planId = (int) $request->integer('plan_id', 0);

        $query = Contractor::query()
            ->with('plan:id,name,price_monthly,niche')
            ->with('modules:id,code,is_active')
            ->withCount([
                'users as admins_count' => static fn ($innerQuery) => $innerQuery->where('role', User::ROLE_ADMIN),
            ]);

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%");
            });
        }

        if ($niche !== '') {
            $query->where('settings->business_niche', $this->normalizeNiche($niche));
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($planId > 0) {
            $query->where('plan_id', $planId);
        }

        $contractors = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Contractor $contractor): array {
                $niche = $contractor->niche();

                return [
                    'id' => $contractor->id,
                    'uuid' => $contractor->uuid,
                    'name' => $contractor->name,
                    'email' => $contractor->email,
                    'phone' => $contractor->phone,
                    'cnpj' => $contractor->cnpj,
                    'slug' => $contractor->slug,
                    'timezone' => $contractor->timezone,
                    'brand_name' => $contractor->brand_name,
                    'brand_primary_color' => $contractor->brand_primary_color,
                    'business_niche' => $niche,
                    'business_niche_label' => $this->resolveNicheLabel($niche),
                    'business_type' => $contractor->businessType(),
                    'business_type_label' => Contractor::labelForBusinessType($contractor->businessType()),
                    'enabled_module_codes' => $contractor->enabledModules(),
                    'plan_id' => $contractor->plan_id,
                    'plan_name' => $contractor->plan?->name ?? $contractor->activePlanName(),
                    'monthly_price' => $contractor->plan?->price_monthly !== null ? (float) $contractor->plan->price_monthly : null,
                    'admins_count' => (int) $contractor->admins_count,
                    'is_active' => (bool) $contractor->is_active,
                    'status_label' => $contractor->is_active ? 'Ativo' : 'Inativo',
                    'created_at' => optional($contractor->created_at)?->format('d/m/Y H:i'),
                ];
            });

        $total = Contractor::query()->count();
        $active = Contractor::query()->where('is_active', true)->count();
        $commercial = Contractor::query()->where('settings->business_niche', Contractor::NICHE_COMMERCIAL)->count();
        $services = Contractor::query()->where('settings->business_niche', Contractor::NICHE_SERVICES)->count();

        return Inertia::render('Master/Contractors/Index', [
            'contractors' => $contractors,
            'filters' => [
                'search' => $search,
                'niche' => $niche,
                'status' => $status,
                'plan_id' => $planId > 0 ? $planId : null,
            ],
            'stats' => [
                'total' => $total,
                'active' => $active,
                'commercial' => $commercial,
                'services' => $services,
            ],
            'plans' => Plan::query()
                ->orderByRaw("CASE niche WHEN 'commercial' THEN 0 WHEN 'services' THEN 1 ELSE 99 END")
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'price_monthly', 'niche'])
                ->map(static fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price_monthly' => $plan->price_monthly !== null ? (float) $plan->price_monthly : null,
                    'niche' => $plan->niche,
                    'niche_label' => Plan::labelForNiche($plan->niche),
                ])
                ->values()
                ->all(),
            'niches' => collect(Contractor::availableNiches())
                ->map(fn ($item): array => [
                    'value' => $item,
                    'label' => $this->resolveNicheLabel($item),
                ])
                ->values()
                ->all(),
            'businessTypes' => collect(Contractor::availableNiches())
                ->flatMap(function (string $niche): Collection {
                    return collect(Contractor::availableBusinessTypes($niche))
                        ->map(static fn (string $businessType): array => [
                            'value' => $businessType,
                            'niche' => $niche,
                            'label' => Contractor::labelForBusinessType($businessType),
                        ]);
                })
                ->values()
                ->all(),
            'moduleCatalog' => $this->contractorCapabilitiesService->moduleCatalogForMaster(),
            'modulePresets' => $this->resolveModulePresetsPayload(),
        ]);
    }

    /**
     * Store a newly created contractor in storage.
     */
    public function store(StoreContractorRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slugBase = $data['slug'] ?: $data['name'];
        $niche = $this->normalizeNiche($data['business_niche']);
        $businessType = Contractor::normalizeBusinessType((string) ($data['business_type'] ?? ''), $niche);
        $plan = $this->resolvePlanOrNull($data['plan_id'] ?? null, $niche);
        $moduleCodes = $this->contractorCapabilitiesService->resolveModuleCodesForPersistence(
            $niche,
            $businessType,
            $data['module_codes'] ?? [],
        );
        $moduleIds = $this->contractorCapabilitiesService->resolveModuleIdsFromCodes($moduleCodes);

        $contractor = Contractor::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'cnpj' => $data['cnpj'],
            'slug' => $this->resolveUniqueSlug($slugBase),
            'plan_id' => $plan?->id,
            'timezone' => $data['timezone'],
            'brand_name' => $data['brand_name'] ?: $data['name'],
            'brand_primary_color' => $data['brand_primary_color'] ?: '#073341',
            'settings' => $this->buildSettings(
                existing: [],
                niche: $niche,
                planName: $plan?->name
            ),
            'business_type' => $businessType,
            'is_active' => $data['is_active'],
        ]);

        $contractor->modules()->sync($moduleIds);

        return back()->with('status', 'Contratante criado com sucesso.');
    }

    /**
     * Update the specified contractor in storage.
     */
    public function update(UpdateContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $data = $request->validated();
        $slugBase = $data['slug'] ?: $data['name'];
        $niche = $this->normalizeNiche($data['business_niche']);
        $businessType = Contractor::normalizeBusinessType((string) ($data['business_type'] ?? ''), $niche);
        $plan = $this->resolvePlanOrNull($data['plan_id'] ?? null, $niche);
        $moduleCodes = $this->contractorCapabilitiesService->resolveModuleCodesForPersistence(
            $niche,
            $businessType,
            $data['module_codes'] ?? [],
        );
        $moduleIds = $this->contractorCapabilitiesService->resolveModuleIdsFromCodes($moduleCodes);

        $contractor->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?: null,
            'cnpj' => $data['cnpj'],
            'slug' => $this->resolveUniqueSlug($slugBase, $contractor->id),
            'plan_id' => $plan?->id,
            'timezone' => $data['timezone'],
            'brand_name' => $data['brand_name'] ?: $data['name'],
            'brand_primary_color' => $data['brand_primary_color'] ?: '#073341',
            'settings' => $this->buildSettings(
                existing: $contractor->settings,
                niche: $niche,
                planName: $plan?->name
            ),
            'business_type' => $businessType,
            'is_active' => $data['is_active'],
        ])->save();

        $contractor->modules()->sync($moduleIds);

        return back()->with('status', 'Contratante atualizado com sucesso.');
    }

    /**
     * Remove the specified contractor from storage.
     */
    public function destroy(Request $request, Contractor $contractor): RedirectResponse
    {
        $this->authorizeManager($request);

        $contractor->delete();

        return redirect()
            ->route('master.contractors.index')
            ->with('status', 'Contratante removido com sucesso.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->isMaster(), 403);
    }

    private function resolvePlanOrNull(mixed $planId, string $niche): ?Plan
    {
        $safePlanId = (int) $planId;
        if ($safePlanId <= 0) {
            return null;
        }

        return Plan::query()
            ->where('niche', Plan::normalizeNiche($niche))
            ->findOrFail($safePlanId);
    }

    private function normalizeNiche(string $niche): string
    {
        return Contractor::normalizeNiche($niche);
    }

    private function resolveNicheLabel(string $niche): string
    {
        return match ($this->normalizeNiche($niche)) {
            Contractor::NICHE_SERVICES => 'Serviços',
            default => 'Comércio',
        };
    }

    private function resolveUniqueSlug(string $value, ?int $ignoreContractorId = null): string
    {
        $baseSlug = Str::slug(trim($value));
        if ($baseSlug === '') {
            $baseSlug = 'contratante';
        }

        $candidate = $baseSlug;
        $counter = 2;

        while ($this->slugExists($candidate, $ignoreContractorId)) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, ?int $ignoreContractorId = null): bool
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->when($ignoreContractorId, static fn ($query) => $query->where('id', '!=', $ignoreContractorId))
            ->exists();
    }

    /**
     * @param array<string, mixed>|mixed $existing
     * @return array<string, mixed>
     */
    private function buildSettings(mixed $existing, string $niche, ?string $planName): array
    {
        $settings = is_array($existing) ? $existing : [];
        $settings['business_niche'] = $this->normalizeNiche($niche);
        $settings['active_plan_name'] = trim((string) ($planName ?? '')) ?: 'Sem plano';
        $settings['require_2fa'] = true;
        $settings['require_email_verification'] = (bool) ($settings['require_email_verification'] ?? true);
        $settings['email_notifications_enabled'] = (bool) ($settings['email_notifications_enabled'] ?? true);

        return $settings;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function resolveModulePresetsPayload(): array
    {
        $payload = [];

        foreach (Contractor::availableNiches() as $niche) {
            foreach (Contractor::availableBusinessTypes($niche) as $businessType) {
                $payload[$businessType] = $this->contractorCapabilitiesService
                    ->defaultModuleCodes($niche, $businessType);
            }
        }

        return $payload;
    }
}
