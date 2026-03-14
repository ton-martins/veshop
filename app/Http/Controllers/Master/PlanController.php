<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePlanRequest;
use App\Http\Requests\Master\UpdatePlanRequest;
use App\Models\Contractor;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED_ICONS = [
        'Sparkles',
        'Crown',
        'Rocket',
        'ShieldCheck',
        'Star',
        'BadgeCheck',
        'Users',
        'Server',
        'Database',
        'Bell',
        'HardDrive',
        'Gauge',
        'CheckCircle2',
    ];

    /**
     * Display a listing of plans.
     */
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $niche = $this->normalizeNiche((string) $request->string('niche')->toString(), false);

        $query = Plan::query()->withCount('contractors');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%")
                    ->orWhere('badge', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($niche !== null) {
            $query->where('niche', $niche);
        }

        $plans = $query
            ->orderByRaw("CASE niche WHEN 'commercial' THEN 0 WHEN 'services' THEN 1 ELSE 99 END")
            ->orderByDesc('is_featured')
            ->orderBy('tier_rank')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(function (Plan $plan): array {
                $features = $this->normalizeFeatures($plan->features);

                return [
                    'id' => $plan->id,
                    'niche' => $plan->niche,
                    'niche_label' => Plan::labelForNiche($plan->niche),
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'badge' => $plan->badge,
                    'subtitle' => $plan->subtitle,
                    'summary' => $plan->summary,
                    'footer_message' => $plan->footer_message,
                    'price_monthly' => $plan->price_monthly !== null ? (float) $plan->price_monthly : null,
                    'max_admin_users' => $plan->max_admin_users,
                    'user_limit' => $plan->user_limit,
                    'storage_limit_gb' => $plan->storage_limit_gb,
                    'audit_log_retention_days' => $plan->audit_log_retention_days,
                    'description' => $plan->description,
                    'features' => $features,
                    'features_text' => $this->featuresToText($features),
                    'is_active' => (bool) $plan->is_active,
                    'is_featured' => (bool) $plan->is_featured,
                    'show_on_landing' => (bool) $plan->show_on_landing,
                    'status_label' => $plan->is_active ? 'Ativo' : 'Inativo',
                    'tier_rank' => (int) $plan->tier_rank,
                    'sort_order' => (int) $plan->sort_order,
                    'contractors_count' => (int) $plan->contractors_count,
                    'active_contractors_count' => (int) $plan->contractors_count,
                    'created_at' => optional($plan->created_at)?->format('d/m/Y H:i'),
                ];
            });

        $stats = [
            'total' => Plan::query()->count(),
            'active' => Plan::query()->where('is_active', true)->count(),
            'commercial' => Plan::query()->where('niche', Plan::NICHE_COMMERCIAL)->count(),
            'services' => Plan::query()->where('niche', Plan::NICHE_SERVICES)->count(),
            'subscriptions' => Contractor::query()->whereNotNull('plan_id')->count(),
            'avg_ticket' => Contractor::query()
                ->join('plans', 'plans.id', '=', 'contractors.plan_id')
                ->avg('plans.price_monthly'),
        ];

        return Inertia::render('Master/Plans/Index', [
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'niche' => $niche,
            ],
            'stats' => [
                'total' => (int) $stats['total'],
                'active' => (int) $stats['active'],
                'commercial' => (int) $stats['commercial'],
                'services' => (int) $stats['services'],
                'subscriptions' => (int) $stats['subscriptions'],
                'avg_ticket' => $stats['avg_ticket'] !== null ? round((float) $stats['avg_ticket'], 2) : null,
            ],
            'niches' => collect(Plan::availableNiches())
                ->map(static fn (string $item): array => [
                    'value' => $item,
                    'label' => Plan::labelForNiche($item),
                ])
                ->values()
                ->all(),
        ]);
    }

    /**
     * Store a newly created plan in storage.
     */
    public function store(StorePlanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $slugBase = $data['slug'] ?: $data['name'];
        $features = $this->parseFeatures(
            featuresText: $data['features_text'] ?? null,
            featuresInput: $data['features'] ?? null
        );
        $userLimit = $data['user_limit'] ?? null;
        $tierRank = $data['tier_rank'] ?? 0;
        $niche = $this->normalizeNiche($data['niche']);

        $plan = Plan::query()->create([
            'niche' => $niche,
            'name' => $data['name'],
            'slug' => $this->resolveUniqueSlug($slugBase, $niche),
            'badge' => $this->nullIfBlank($data['badge'] ?? null),
            'subtitle' => $this->nullIfBlank($data['subtitle'] ?? null),
            'summary' => $this->nullIfBlank($data['summary'] ?? null),
            'footer_message' => $this->nullIfBlank($data['footer_message'] ?? null),
            'price_monthly' => $data['price_monthly'] ?? null,
            'max_admin_users' => $data['max_admin_users'] ?? $userLimit,
            'user_limit' => $userLimit,
            'storage_limit_gb' => $data['storage_limit_gb'] ?? null,
            'audit_log_retention_days' => $data['audit_log_retention_days'] ?? null,
            'description' => $this->nullIfBlank($data['description'] ?? null),
            'features' => $features,
            'is_active' => $data['is_active'],
            'is_featured' => $data['is_featured'] ?? false,
            'show_on_landing' => $data['show_on_landing'] ?? false,
            'tier_rank' => $tierRank,
            'sort_order' => $data['sort_order'] ?? $tierRank,
        ]);

        $this->syncPlanNameIntoContractors($plan);

        return back()->with('status', 'Plano criado com sucesso.');
    }

    /**
     * Update the specified plan in storage.
     */
    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();
        $slugBase = $data['slug'] ?: $data['name'];
        $features = $this->parseFeatures(
            featuresText: $data['features_text'] ?? null,
            featuresInput: $data['features'] ?? null
        );
        $userLimit = $data['user_limit'] ?? null;
        $tierRank = $data['tier_rank'] ?? 0;
        $niche = $this->normalizeNiche($data['niche']);

        $plan->fill([
            'niche' => $niche,
            'name' => $data['name'],
            'slug' => $this->resolveUniqueSlug($slugBase, $niche, $plan->id),
            'badge' => $this->nullIfBlank($data['badge'] ?? null),
            'subtitle' => $this->nullIfBlank($data['subtitle'] ?? null),
            'summary' => $this->nullIfBlank($data['summary'] ?? null),
            'footer_message' => $this->nullIfBlank($data['footer_message'] ?? null),
            'price_monthly' => $data['price_monthly'] ?? null,
            'max_admin_users' => $data['max_admin_users'] ?? $userLimit,
            'user_limit' => $userLimit,
            'storage_limit_gb' => $data['storage_limit_gb'] ?? null,
            'audit_log_retention_days' => $data['audit_log_retention_days'] ?? null,
            'description' => $this->nullIfBlank($data['description'] ?? null),
            'features' => $features,
            'is_active' => $data['is_active'],
            'is_featured' => $data['is_featured'] ?? false,
            'show_on_landing' => $data['show_on_landing'] ?? false,
            'tier_rank' => $tierRank,
            'sort_order' => $data['sort_order'] ?? $tierRank,
        ])->save();

        $this->syncPlanNameIntoContractors($plan);

        return back()->with('status', 'Plano atualizado com sucesso.');
    }

    /**
     * Remove the specified plan from storage.
     */
    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        $this->authorizeManager($request);

        if ($plan->contractors()->exists()) {
            return back()->withErrors([
                'general' => 'Nao e possivel excluir um plano com contratantes vinculados.',
            ]);
        }

        $plan->delete();

        return redirect()
            ->route('master.plans.index')
            ->with('status', 'Plano removido com sucesso.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->isMaster(), 403);
    }

    private function resolveUniqueSlug(string $value, string $niche, ?int $ignorePlanId = null): string
    {
        $safeNiche = $this->normalizeNiche($niche);
        $baseSlug = Str::slug(trim($value));
        if ($baseSlug === '') {
            $baseSlug = 'plano';
        }

        $candidate = $baseSlug;
        $counter = 2;

        while ($this->slugExists($candidate, $safeNiche, $ignorePlanId)) {
            $candidate = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    private function slugExists(string $slug, string $niche, ?int $ignorePlanId = null): bool
    {
        return Plan::query()
            ->withTrashed()
            ->where('niche', $niche)
            ->where('slug', $slug)
            ->when($ignorePlanId, static fn ($query) => $query->where('id', '!=', $ignorePlanId))
            ->exists();
    }

    private function normalizeNiche(string $niche, bool $withDefault = true): ?string
    {
        $safeNiche = strtolower(trim($niche));
        if ($safeNiche === '') {
            return $withDefault ? Plan::defaultNiche() : null;
        }

        if (in_array($safeNiche, Plan::availableNiches(), true)) {
            return $safeNiche;
        }

        return $withDefault ? Plan::defaultNiche() : null;
    }

    private function resolveAllowedIcon(?string $icon): string
    {
        $safeIcon = trim((string) $icon);
        if ($safeIcon === '') {
            return 'Sparkles';
        }

        return in_array($safeIcon, self::ALLOWED_ICONS, true)
            ? $safeIcon
            : 'Sparkles';
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $safe = trim((string) ($value ?? ''));

        return $safe !== '' ? $safe : null;
    }

    /**
     * @return list<array{label: string, value: string, icon: string, enabled: bool}>
     */
    private function parseFeatures(mixed $featuresText = null, mixed $featuresInput = null): array
    {
        if (is_array($featuresInput)) {
            return collect($featuresInput)
                ->map(fn ($item) => $this->normalizeFeatureRecord($item))
                ->filter(static fn (?array $item) => $item !== null)
                ->values()
                ->all();
        }

        return collect(preg_split('/\R+/', (string) ($featuresText ?? '')) ?: [])
            ->map(static fn ($line) => trim((string) $line))
            ->filter()
            ->map(function ($line) {
                $label = $line;
                $value = '';

                if (str_contains($line, ':')) {
                    [$before, $after] = explode(':', $line, 2);
                    $label = trim($before);
                    $value = trim($after);
                }

                return $this->normalizeFeatureRecord([
                    'label' => $label,
                    'value' => $value,
                    'icon' => 'CheckCircle2',
                    'enabled' => true,
                ]);
            })
            ->filter(static fn (?array $item) => $item !== null)
            ->values()
            ->all();
    }

    /**
     * @param mixed $feature
     * @return array{label: string, value: string, icon: string, enabled: bool}|null
     */
    private function normalizeFeatureRecord(mixed $feature): ?array
    {
        if (! is_array($feature)) {
            return null;
        }

        $label = trim((string) ($feature['label'] ?? ''));
        $value = trim((string) ($feature['value'] ?? ''));
        if ($label === '' && $value === '') {
            return null;
        }

        return [
            'label' => $label !== '' ? $label : $value,
            'value' => $value,
            'icon' => $this->resolveAllowedIcon((string) ($feature['icon'] ?? 'CheckCircle2')),
            'enabled' => (bool) ($feature['enabled'] ?? true),
        ];
    }

    /**
     * @return list<array{label: string, value: string, icon: string, enabled: bool}>
     */
    private function normalizeFeatures(mixed $features): array
    {
        return collect(is_array($features) ? $features : [])
            ->map(fn ($item) => $this->normalizeFeatureRecord($item))
            ->filter(static fn (?array $item) => $item !== null)
            ->values()
            ->all();
    }

    /**
     * @param list<array{label: string, value: string, icon: string, enabled: bool}> $features
     */
    private function featuresToText(array $features): string
    {
        return collect($features)
            ->map(static function (array $feature): string {
                $label = trim((string) ($feature['label'] ?? ''));
                $value = trim((string) ($feature['value'] ?? ''));

                if ($label === '') {
                    return $value;
                }

                return $value !== '' ? "{$label}: {$value}" : $label;
            })
            ->filter()
            ->implode("\n");
    }

    private function syncPlanNameIntoContractors(Plan $plan): void
    {
        $contractors = Contractor::query()
            ->where('plan_id', $plan->id)
            ->get();

        foreach ($contractors as $contractor) {
            $settings = is_array($contractor->settings) ? $contractor->settings : [];
            $settings['active_plan_name'] = $plan->name;

            $contractor->forceFill([
                'settings' => $settings,
            ])->save();
        }
    }
}
