<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $currentContractor = $this->resolveCurrentContractor($request);

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'contractorContext' => fn () => $this->resolveContractorContext($request, $currentContractor),
            'systemBranding' => fn () => $this->resolveSystemBranding($request, $currentContractor),
        ];
    }

    /**
     * Resolve branding used by frontend layouts.
     *
     * @return array<string, mixed>
     */
    private function resolveSystemBranding(Request $request, mixed $currentContractor = null): array
    {
        $defaultBranding = config('branding', []);
        $contractorBranding = [];
        $sessionBranding = $request->session()->get('system_branding', []);

        if (! is_array($sessionBranding)) {
            $sessionBranding = [];
        }

        if ($currentContractor) {
            $contractorBranding = array_filter([
                'name' => $currentContractor->brand_name ?: $currentContractor->name,
                'logo_url' => $currentContractor->brand_logo_url,
                'avatar_url' => $currentContractor->brand_avatar_url,
                'primary_color' => $currentContractor->brand_primary_color,
            ], static fn ($value) => $value !== null && $value !== '');
        }

        return array_replace(
            $defaultBranding,
            $contractorBranding,
            array_filter(
                $sessionBranding,
                static fn ($value) => $value !== null && $value !== '',
            ),
        );
    }

    private function resolveCurrentContractor(Request $request): mixed
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
            $selectedBySession = $availableContractors->firstWhere('id', $sessionContractorId);

            if ($selectedBySession) {
                return $selectedBySession;
            }
        }

        $resolvedContractor = $availableContractors->first();

        if ($resolvedContractor) {
            $request->session()->put('current_contractor_id', $resolvedContractor->id);
        }

        return $resolvedContractor;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveContractorContext(Request $request, mixed $currentContractor = null): array
    {
        $user = $request->user();

        if (! $user) {
            return [
                'current' => null,
                'available' => [],
            ];
        }

        $user->loadMissing('contractors');

        $available = $user->contractors
            ->map(fn ($contractor): array => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'slug' => $contractor->slug,
                'brand_name' => $contractor->brand_name,
                'brand_primary_color' => $contractor->brand_primary_color,
                'brand_logo_url' => $contractor->brand_logo_url,
                'brand_avatar_url' => $contractor->brand_avatar_url,
                'business_niche' => $contractor->niche(),
                'business_niche_label' => $this->resolveNicheLabel($contractor->niche()),
                'active_plan_name' => $contractor->activePlanName(),
                'enabled_modules' => $contractor->enabledModules(),
            ])
            ->values()
            ->all();

        $current = $currentContractor ? [
            'id' => $currentContractor->id,
            'name' => $currentContractor->name,
            'slug' => $currentContractor->slug,
            'brand_name' => $currentContractor->brand_name,
            'brand_primary_color' => $currentContractor->brand_primary_color,
            'brand_logo_url' => $currentContractor->brand_logo_url,
            'brand_avatar_url' => $currentContractor->brand_avatar_url,
            'business_niche' => $currentContractor->niche(),
            'business_niche_label' => $this->resolveNicheLabel($currentContractor->niche()),
            'active_plan_name' => $currentContractor->activePlanName(),
            'enabled_modules' => $currentContractor->enabledModules(),
        ] : null;

        return [
            'current' => $current,
            'available' => $available,
        ];
    }

    private function resolveNicheLabel(string $niche): string
    {
        return match ($niche) {
            'services' => 'Serviços',
            default => 'Comércio',
        };
    }
}
