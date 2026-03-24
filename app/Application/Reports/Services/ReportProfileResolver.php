<?php

namespace App\Application\Reports\Services;

use App\Models\Contractor;
use App\Models\ReportProfile;

class ReportProfileResolver
{
    public const OVERVIEW_PROFILE_KEY = 'overview';

    /**
     * @return array{
     *   top_items_limit: int,
     *   timeline_days_limit: int,
     *   visible_metric_keys: array<int, string>
     * }
     */
    public function resolveOverviewProfile(Contractor $contractor): array
    {
        $default = $this->defaultOverviewProfile();
        $matchingProfiles = $this->loadMatchingProfiles($contractor, self::OVERVIEW_PROFILE_KEY);

        $resolved = $default;

        foreach ($matchingProfiles as $profile) {
            $config = is_array($profile->config) ? $profile->config : [];
            $resolved = array_replace_recursive($resolved, $config);
        }

        return $this->sanitizeOverviewProfile($resolved);
    }

    /**
     * @return array{
     *   top_items_limit: int,
     *   timeline_days_limit: int,
     *   visible_metric_keys: array<int, string>
     * }
     */
    private function defaultOverviewProfile(): array
    {
        return [
            'top_items_limit' => 6,
            'timeline_days_limit' => 21,
            'visible_metric_keys' => [],
        ];
    }

    /**
     * @return array<int, ReportProfile>
     */
    private function loadMatchingProfiles(Contractor $contractor, string $profileKey): array
    {
        $niche = $contractor->niche();
        $businessType = $contractor->businessType();
        $planId = $contractor->plan_id !== null ? (int) $contractor->plan_id : null;

        return ReportProfile::query()
            ->where('profile_key', $profileKey)
            ->where('is_active', true)
            ->where(function ($query) use ($contractor, $niche, $businessType, $planId): void {
                $query
                    ->where('scope', ReportProfile::SCOPE_GLOBAL)
                    ->orWhere(static function ($scopeQuery) use ($niche): void {
                        $scopeQuery
                            ->where('scope', ReportProfile::SCOPE_NICHE)
                            ->where('niche', $niche);
                    })
                    ->orWhere(static function ($scopeQuery) use ($niche, $businessType): void {
                        $scopeQuery
                            ->where('scope', ReportProfile::SCOPE_BUSINESS_TYPE)
                            ->where('niche', $niche)
                            ->where('business_type', $businessType);
                    })
                    ->orWhere(static function ($scopeQuery) use ($contractor): void {
                        $scopeQuery
                            ->where('scope', ReportProfile::SCOPE_CONTRACTOR)
                            ->where('contractor_id', $contractor->id);
                    });

                if ($planId !== null) {
                    $query->orWhere(static function ($scopeQuery) use ($planId): void {
                        $scopeQuery
                            ->where('scope', ReportProfile::SCOPE_PLAN)
                            ->where('plan_id', $planId);
                    });
                }
            })
            ->orderByRaw(
                "CASE scope
                    WHEN 'global' THEN 10
                    WHEN 'niche' THEN 20
                    WHEN 'business_type' THEN 30
                    WHEN 'plan' THEN 40
                    WHEN 'contractor' THEN 50
                    ELSE 5
                END"
            )
            ->orderBy('id')
            ->get()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array{
     *   top_items_limit: int,
     *   timeline_days_limit: int,
     *   visible_metric_keys: array<int, string>
     * }
     */
    private function sanitizeOverviewProfile(array $profile): array
    {
        $topItemsLimit = (int) ($profile['top_items_limit'] ?? 6);
        $timelineDaysLimit = (int) ($profile['timeline_days_limit'] ?? 21);
        $visibleMetricKeys = collect(is_array($profile['visible_metric_keys'] ?? null) ? $profile['visible_metric_keys'] : [])
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'top_items_limit' => min(max($topItemsLimit, 3), 20),
            'timeline_days_limit' => min(max($timelineDaysLimit, 7), 90),
            'visible_metric_keys' => $visibleMetricKeys,
        ];
    }
}
