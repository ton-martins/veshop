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

        $now = now();

        DB::table('modules')->upsert([
            [
                'code' => 'collaborators',
                'name' => 'Colaboradores',
                'description' => 'Cadastro operacional de colaboradores e equipe',
                'scope' => 'global',
                'niche' => null,
                'business_types' => null,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 45,
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

        if (! Schema::hasTable('plan_module')) {
            return;
        }

        $moduleId = DB::table('modules')
            ->where('code', 'collaborators')
            ->value('id');

        if (! $moduleId) {
            return;
        }

        $planIds = DB::table('plans')
            ->whereIn('niche', ['commercial', 'services'])
            ->whereIn('name', ['Profissional', 'Escala'])
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();

        foreach ($planIds as $planId) {
            DB::table('plan_module')->updateOrInsert(
                [
                    'plan_id' => $planId,
                    'module_id' => (int) $moduleId,
                ],
                [
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $moduleId = DB::table('modules')
            ->where('code', 'collaborators')
            ->value('id');

        if ($moduleId && Schema::hasTable('plan_module')) {
            DB::table('plan_module')
                ->where('module_id', (int) $moduleId)
                ->delete();
        }

        if ($moduleId && Schema::hasTable('contractor_module')) {
            DB::table('contractor_module')
                ->where('module_id', (int) $moduleId)
                ->delete();
        }

        DB::table('modules')
            ->where('code', 'collaborators')
            ->delete();
    }
};
