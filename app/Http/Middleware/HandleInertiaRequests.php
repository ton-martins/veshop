<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use Throwable;

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
        $authenticatedUser = $request->user();

        if ($authenticatedUser) {
            $authenticatedUser->avatar_url = $this->resolveSharedUserAvatarUrl($authenticatedUser);
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $authenticatedUser,
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
        $persistedBranding = $this->resolvePersistedSystemBranding();
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
            $persistedBranding,
            $contractorBranding,
            array_filter(
                $sessionBranding,
                static fn ($value) => $value !== null && $value !== '',
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePersistedSystemBranding(): array
    {
        try {
            $stored = SystemSetting::getValue(SystemSetting::KEY_BRANDING, []);

            if (! is_array($stored)) {
                return [];
            }

            return $stored;
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveCurrentContractor(Request $request): mixed
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        if ($user->isMaster()) {
            $request->session()->forget('current_contractor_id');

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

        if ($user->isMaster()) {
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

    private function normalizePublicStorageUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;

        if (str_starts_with($normalized, '/storage/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        return $value;
    }

    private function resolveSharedUserAvatarUrl(mixed $user): ?string
    {
        if (! $user || ! isset($user->id)) {
            return null;
        }

        $normalized = $this->normalizePublicStorageUrl($user->avatar_url ?? null);

        if ($normalized && ! $this->isStoragePublicUrl($normalized)) {
            return $normalized;
        }

        if ($normalized && $this->publicStorageUrlExists($normalized)) {
            return $normalized;
        }

        $fallbackRelativePath = $this->resolveLatestUserAvatarRelativePath((int) $user->id);

        if ($fallbackRelativePath) {
            return '/storage/'.$fallbackRelativePath;
        }

        return null;
    }

    private function isStoragePublicUrl(string $value): bool
    {
        return str_starts_with($value, '/storage/') || str_starts_with($value, 'storage/');
    }

    private function publicStorageUrlExists(string $value): bool
    {
        $relativePath = $this->resolvePublicStorageRelativePath($value);

        if (! $relativePath) {
            return false;
        }

        return Storage::disk('public')->exists($relativePath);
    }

    private function resolvePublicStorageRelativePath(?string $publicUrl): ?string
    {
        if (! $publicUrl) {
            return null;
        }

        $path = parse_url($publicUrl, PHP_URL_PATH);
        $normalizedPath = is_string($path) && $path !== '' ? $path : $publicUrl;

        if (str_starts_with($normalizedPath, '/storage/')) {
            $relativePath = ltrim(substr($normalizedPath, strlen('/storage/')), '/');

            return $relativePath !== '' ? $relativePath : null;
        }

        if (str_starts_with($normalizedPath, 'storage/')) {
            $relativePath = ltrim(substr($normalizedPath, strlen('storage/')), '/');

            return $relativePath !== '' ? $relativePath : null;
        }

        return null;
    }

    private function resolveLatestUserAvatarRelativePath(int $userId): ?string
    {
        if ($userId <= 0) {
            return null;
        }

        $directory = "users/{$userId}/avatar";

        if (! Storage::disk('public')->exists($directory)) {
            return null;
        }

        $files = Storage::disk('public')->files($directory);

        if (empty($files)) {
            return null;
        }

        usort($files, static function (string $left, string $right): int {
            $leftModifiedAt = Storage::disk('public')->lastModified($left);
            $rightModifiedAt = Storage::disk('public')->lastModified($right);

            return $rightModifiedAt <=> $leftModifiedAt;
        });

        return $files[0] ?? null;
    }
}
