<?php

namespace App\Application\Storefront\Queries;

use App\Application\Shared\Contracts\Query;
use App\Models\Plan;

class BuildLandingPageQuery implements Query
{
    /**
     * @return array<string, mixed>
     */
    public function execute(mixed ...$args): array
    {
        $plansByNiche = Plan::query()
            ->where('is_active', true)
            ->where('show_on_landing', true)
            ->orderByRaw("CASE niche WHEN 'commercial' THEN 0 WHEN 'services' THEN 1 ELSE 99 END")
            ->orderBy('tier_rank')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'niche',
                'name',
                'slug',
                'badge',
                'subtitle',
                'summary',
                'footer_message',
                'price_monthly',
                'user_limit',
                'features',
                'is_featured',
            ])
            ->groupBy('niche');

        $planSections = collect(Plan::availableNiches())
            ->map(static function (string $niche) use ($plansByNiche): array {
                $plans = collect($plansByNiche->get($niche, []))
                    ->map(static function (Plan $plan): array {
                        $features = collect(is_array($plan->features) ? $plan->features : [])
                            ->filter(static fn ($feature) => ($feature['enabled'] ?? true) !== false)
                            ->map(static fn ($feature): array => [
                                'label' => trim((string) ($feature['label'] ?? '')),
                                'value' => trim((string) ($feature['value'] ?? '')),
                            ])
                            ->values()
                            ->all();

                        return [
                            'id' => $plan->id,
                            'niche' => $plan->niche,
                            'name' => $plan->name,
                            'slug' => $plan->slug,
                            'badge' => $plan->badge,
                            'subtitle' => $plan->subtitle,
                            'summary' => $plan->summary,
                            'footer_message' => $plan->footer_message,
                            'price_monthly' => $plan->price_monthly !== null ? (float) $plan->price_monthly : null,
                            'user_limit' => $plan->user_limit,
                            'features' => $features,
                            'is_featured' => (bool) $plan->is_featured,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'value' => $niche,
                    'label' => Plan::labelForNiche($niche),
                    'title' => $niche === Plan::NICHE_SERVICES ? 'Planos para Serviços' : 'Planos para Comércio',
                    'description' => $niche === Plan::NICHE_SERVICES
                        ? 'Estruturas para equipes de atendimento, agenda e ordens de serviço.'
                        : 'Estruturas para operação comercial, catálogo e controle de vendas.',
                    'plans' => $plans,
                ];
            })
            ->values()
            ->all();

        return [
            'planSections' => $planSections,
        ];
    }
}
