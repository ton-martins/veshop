<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $now = now();

        DB::table('modules')->upsert([
            [
                'code' => 'services_storefront',
                'name' => "Loja virtual de servi\u{00E7}os",
                'description' => "Cat\u{00E1}logo e agendamentos online do nicho servi\u{00E7}os",
                'scope' => 'specific',
                'niche' => 'services',
                'business_types' => json_encode([
                    'barbershop',
                    'auto_electric',
                    'mechanic',
                    'accounting',
                    'general_services',
                ], JSON_UNESCAPED_UNICODE),
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 165,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['code'], [
            'name',
            'description',
            'scope',
            'niche',
            'business_types',
            'is_default',
            'is_active',
            'sort_order',
            'updated_at',
        ]);

        $storefrontModuleId = DB::table('modules')->where('code', 'services_storefront')->value('id');
        if (! $storefrontModuleId) {
            return;
        }

        if (Schema::hasTable('plans') && Schema::hasTable('plan_module')) {
            $servicePlanIds = DB::table('plans')
                ->where('niche', 'services')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($servicePlanIds !== []) {
                $rows = collect($servicePlanIds)
                    ->map(static fn (int $planId) => [
                        'plan_id' => $planId,
                        'module_id' => (int) $storefrontModuleId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->values()
                    ->all();

                DB::table('plan_module')->upsert($rows, ['plan_id', 'module_id'], ['updated_at']);
            }
        }

        if (Schema::hasTable('contractor_module')) {
            $servicesModuleId = DB::table('modules')->where('code', 'services')->value('id');

            if ($servicesModuleId) {
                $contractorIds = DB::table('contractor_module')
                    ->where('module_id', (int) $servicesModuleId)
                    ->pluck('contractor_id')
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0)
                    ->values()
                    ->all();

                if ($contractorIds !== []) {
                    $rows = collect($contractorIds)
                        ->map(static fn (int $contractorId) => [
                            'contractor_id' => $contractorId,
                            'module_id' => (int) $storefrontModuleId,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])
                        ->values()
                        ->all();

                    DB::table('contractor_module')->upsert($rows, ['contractor_id', 'module_id'], ['updated_at']);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $moduleId = DB::table('modules')->where('code', 'services_storefront')->value('id');
        if (! $moduleId) {
            return;
        }

        if (Schema::hasTable('plan_module')) {
            DB::table('plan_module')->where('module_id', (int) $moduleId)->delete();
        }

        if (Schema::hasTable('contractor_module')) {
            DB::table('contractor_module')->where('module_id', (int) $moduleId)->delete();
        }

        DB::table('modules')->where('id', (int) $moduleId)->delete();
    }
};
