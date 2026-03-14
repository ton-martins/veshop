<?php

namespace Database\Seeders;

use App\Models\Contractor;
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
                'active_plan_name' => 'Pro',
            ],
            [
                'slug' => 'veshop-store',
                'name' => 'Veshop Store',
                'email' => 'store@veshop.com.br',
                'cnpj' => '12345678000192',
                'brand_name' => 'Veshop Store',
                'niche' => Contractor::NICHE_COMMERCIAL,
                'active_plan_name' => 'Business',
            ],
            [
                'slug' => 'veshop-services',
                'name' => 'Veshop Services',
                'email' => 'services@veshop.com.br',
                'cnpj' => '12345678000193',
                'brand_name' => 'Veshop Services',
                'niche' => Contractor::NICHE_SERVICES,
                'active_plan_name' => 'Business',
            ],
        ];

        foreach ($contractors as $data) {
            $contractor = Contractor::firstOrNew(['slug' => $data['slug']]);

            if (! $contractor->exists) {
                $contractor->uuid = (string) Str::uuid();
            }

            $contractor->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => null,
                'cnpj' => $data['cnpj'],
                'timezone' => 'America/Sao_Paulo',
                'address' => null,
                'brand_name' => $data['brand_name'],
                'brand_primary_color' => '#073341',
                'brand_logo_url' => null,
                'brand_avatar_url' => null,
                'settings' => [
                    'business_niche' => $data['niche'],
                    'active_plan_name' => $data['active_plan_name'],
                    'require_2fa' => true,
                    'require_email_verification' => true,
                    'email_notifications_enabled' => true,
                ],
            ])->save();
        }
    }
}
