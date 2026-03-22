<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $pdvModule = DB::table('modules')
            ->where('code', 'pdv')
            ->first(['id', 'business_types']);

        if (! $pdvModule) {
            return;
        }

        $businessTypes = $this->normalizeBusinessTypes($pdvModule->business_types ?? null);
        $targetBusinessTypes = [
            'store',
            'confectionery',
            'barbershop',
            'auto_electric',
            'mechanic',
            'accounting',
            'general_services',
        ];

        $mergedBusinessTypes = array_values(array_unique(array_merge($businessTypes, $targetBusinessTypes)));

        DB::table('modules')
            ->where('id', (int) $pdvModule->id)
            ->update([
                'niche' => null,
                'business_types' => json_encode($mergedBusinessTypes, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('plans') && Schema::hasTable('plan_module')) {
            $servicePlanIds = DB::table('plans')
                ->where('niche', 'services')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($servicePlanIds !== []) {
                $now = now();
                $rows = collect($servicePlanIds)
                    ->map(static fn (int $planId): array => [
                        'plan_id' => $planId,
                        'module_id' => (int) $pdvModule->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::table('plan_module')->upsert(
                    $rows,
                    ['plan_id', 'module_id'],
                    ['updated_at']
                );
            }
        }

        if (Schema::hasTable('contractors') && Schema::hasTable('contractor_module')) {
            $contractorIds = DB::table('contractors')
                ->join('plans', 'plans.id', '=', 'contractors.plan_id')
                ->where('plans.niche', 'services')
                ->whereNull('contractors.deleted_at')
                ->pluck('contractors.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($contractorIds !== []) {
                $now = now();
                $rows = collect($contractorIds)
                    ->map(static fn (int $contractorId): array => [
                        'contractor_id' => $contractorId,
                        'module_id' => (int) $pdvModule->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])
                    ->all();

                DB::table('contractor_module')->upsert(
                    $rows,
                    ['contractor_id', 'module_id'],
                    ['updated_at']
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $pdvModule = DB::table('modules')
            ->where('code', 'pdv')
            ->first(['id']);

        if (! $pdvModule) {
            return;
        }

        DB::table('modules')
            ->where('id', (int) $pdvModule->id)
            ->update([
                'niche' => 'commercial',
                'business_types' => json_encode(['store', 'confectionery'], JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        if (Schema::hasTable('plan_module') && Schema::hasTable('plans')) {
            $servicePlanIds = DB::table('plans')
                ->where('niche', 'services')
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($servicePlanIds !== []) {
                DB::table('plan_module')
                    ->where('module_id', (int) $pdvModule->id)
                    ->whereIn('plan_id', $servicePlanIds)
                    ->delete();
            }
        }

        if (Schema::hasTable('contractor_module') && Schema::hasTable('contractors') && Schema::hasTable('plans')) {
            $serviceContractorIds = DB::table('contractors')
                ->join('plans', 'plans.id', '=', 'contractors.plan_id')
                ->where('plans.niche', 'services')
                ->pluck('contractors.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->filter(static fn (int $id): bool => $id > 0)
                ->values()
                ->all();

            if ($serviceContractorIds !== []) {
                DB::table('contractor_module')
                    ->where('module_id', (int) $pdvModule->id)
                    ->whereIn('contractor_id', $serviceContractorIds)
                    ->delete();
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function normalizeBusinessTypes(mixed $rawValue): array
    {
        if (is_string($rawValue) && $rawValue !== '') {
            $decoded = json_decode($rawValue, true);
            if (is_array($decoded)) {
                $rawValue = $decoded;
            }
        }

        if (! is_array($rawValue)) {
            return [];
        }

        return collect($rawValue)
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};

