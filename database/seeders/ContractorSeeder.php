<?php

namespace Database\Seeders;

use App\Models\Contractor;
use App\Models\Plan;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContractorSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $contractors = [
            [
                'slug' => 'veshop-mix',
                'name' => 'Veshop Mix',
                'email' => 'mix@veshop.com.br',
                'cnpj' => '12345678000191',
                'brand_name' => 'Veshop Mix',
                'niche' => Contractor::NICHE_COMMERCIAL,
                'plan_niche' => Plan::NICHE_COMMERCIAL,
                'plan_slug' => 'pro',
            ],
            [
                'slug' => 'veshop-store',
                'name' => 'Veshop Store',
                'email' => 'store@veshop.com.br',
                'cnpj' => '12345678000192',
                'brand_name' => 'Veshop Store',
                'niche' => Contractor::NICHE_COMMERCIAL,
                'plan_niche' => Plan::NICHE_COMMERCIAL,
                'plan_slug' => 'business',
            ],
            [
                'slug' => 'veshop-services',
                'name' => 'Veshop Services',
                'email' => 'services@veshop.com.br',
                'cnpj' => '12345678000193',
                'brand_name' => 'Veshop Services',
                'niche' => Contractor::NICHE_SERVICES,
                'plan_niche' => Plan::NICHE_SERVICES,
                'plan_slug' => 'business',
            ],
        ];

        foreach ($contractors as $data) {
            $contractor = Contractor::firstOrNew(['slug' => $data['slug']]);
            $plan = Plan::query()
                ->where('slug', $data['plan_slug'])
                ->where('niche', $data['plan_niche'])
                ->first();

            if (! $contractor->exists) {
                $contractor->uuid = (string) Str::uuid();
            }

            $contractor->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => null,
                'cnpj' => $data['cnpj'],
                'plan_id' => $plan?->id,
                'timezone' => 'America/Sao_Paulo',
                'address' => null,
                'brand_name' => $data['brand_name'],
                'brand_primary_color' => '#073341',
                'brand_logo_url' => null,
                'brand_avatar_url' => null,
                'settings' => [
                    'business_niche' => $data['niche'],
                    'active_plan_name' => $plan?->name ?? 'Sem plano',
                    'require_2fa' => true,
                    'require_email_verification' => true,
                    'email_notifications_enabled' => true,
                ],
                'is_active' => true,
            ])->save();
        }
    }
}
