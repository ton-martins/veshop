<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Module;

class ContractorCapabilitiesService
{
    /**
     * @return array<int, Module>
     */
    public function availableModulesFor(string $niche, string $businessType): array
    {
        $normalizedNiche = Contractor::normalizeNiche($niche);
        $normalizedBusinessType = Contractor::normalizeBusinessType($businessType, $normalizedNiche);

        return Module::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (Module $module): bool => $this->moduleSupports($module, $normalizedNiche, $normalizedBusinessType))
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function defaultModuleCodes(string $niche, string $businessType): array
    {
        $modules = $this->availableModulesFor($niche, $businessType);
        $defaultCodes = collect($modules)
            ->filter(fn (Module $module): bool => (bool) $module->is_default)
            ->pluck('code')
            ->all();

        return $this->ensureMandatoryModuleCodes($defaultCodes, $niche);
    }

    /**
     * @param array<int, string> $submittedModuleCodes
     * @return array<int, string>
     */
    public function resolveModuleCodesForPersistence(string $niche, string $businessType, array $submittedModuleCodes): array
    {
        if ($submittedModuleCodes === []) {
            return $this->defaultModuleCodes($niche, $businessType);
        }

        $allowedCodes = collect($this->availableModulesFor($niche, $businessType))
            ->pluck('code')
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->values();

        $sanitizedSubmitted = collect($submittedModuleCodes)
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values();

        $filtered = $sanitizedSubmitted
            ->filter(fn (string $code): bool => $allowedCodes->contains($code))
            ->values()
            ->all();

        return $this->ensureMandatoryModuleCodes($filtered, $niche);
    }

    /**
     * @param array<int, string> $moduleCodes
     * @return array<int, int>
     */
    public function resolveModuleIdsFromCodes(array $moduleCodes): array
    {
        if ($moduleCodes === []) {
            return [];
        }

        return Module::query()
            ->where('is_active', true)
            ->whereIn('code', $moduleCodes)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function moduleCatalogForMaster(): array
    {
        return Module::query()
            ->where('is_active', true)
            ->orderByRaw("CASE scope WHEN 'global' THEN 0 WHEN 'niche' THEN 1 ELSE 2 END")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(static function (Module $module): array {
                $businessTypes = collect(is_array($module->business_types) ? $module->business_types : [])
                    ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
                    ->filter()
                    ->values()
                    ->all();

                return [
                    'code' => (string) $module->code,
                    'name' => (string) $module->name,
                    'description' => $module->description !== null ? (string) $module->description : null,
                    'scope' => (string) $module->scope,
                    'niche' => $module->niche !== null ? (string) $module->niche : null,
                    'business_types' => $businessTypes,
                    'is_default' => (bool) $module->is_default,
                    'sort_order' => (int) $module->sort_order,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function enabledModuleCodesForContractor(Contractor $contractor): array
    {
        $codes = $contractor->relationLoaded('modules')
            ? $contractor->modules
                ->where('is_active', true)
                ->pluck('code')
                ->all()
            : $contractor->modules()
                ->where('is_active', true)
                ->pluck('code')
                ->all();

        $normalized = collect($codes)
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalized !== []) {
            return $normalized;
        }

        $defaultModules = $this->defaultModuleCodes($contractor->niche(), $contractor->businessType());
        if ($defaultModules !== []) {
            return $defaultModules;
        }

        return $this->legacyFallbackModuleCodes($contractor->niche());
    }

    /**
     * @return array<int, string>
     */
    public function legacyFallbackModuleCodes(string $niche): array
    {
        return Contractor::normalizeNiche($niche) === Contractor::NICHE_SERVICES
            ? [Contractor::MODULE_SERVICES]
            : [Contractor::MODULE_COMMERCIAL];
    }

    /**
     * @param array<int, string> $moduleCodes
     * @return array<int, string>
     */
    private function ensureMandatoryModuleCodes(array $moduleCodes, string $niche): array
    {
        $mandatoryCodes = Contractor::normalizeNiche($niche) === Contractor::NICHE_SERVICES
            ? [Contractor::MODULE_SERVICES]
            : [Contractor::MODULE_COMMERCIAL];

        return collect(array_merge($moduleCodes, $mandatoryCodes))
            ->map(static fn (mixed $code): string => strtolower(trim((string) $code)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function moduleSupports(Module $module, string $niche, string $businessType): bool
    {
        $moduleNiche = $module->niche !== null ? Contractor::normalizeNiche((string) $module->niche) : null;
        if ($moduleNiche !== null && $moduleNiche !== $niche) {
            return false;
        }

        $businessTypes = collect(is_array($module->business_types) ? $module->business_types : [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($businessTypes !== [] && ! in_array($businessType, $businessTypes, true)) {
            return false;
        }

        return true;
    }
}
