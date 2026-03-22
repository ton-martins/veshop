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
        $this->updateServicesCatalogBusinessTypes(static function (array $businessTypes): array {
            if (! in_array('accounting', $businessTypes, true)) {
                $businessTypes[] = 'accounting';
            }

            sort($businessTypes);

            return array_values(array_unique($businessTypes));
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateServicesCatalogBusinessTypes(static function (array $businessTypes): array {
            return array_values(array_filter(
                $businessTypes,
                static fn (string $item): bool => $item !== 'accounting',
            ));
        });
    }

    /**
     * @param callable(array<int, string>): array<int, string> $mutator
     */
    private function updateServicesCatalogBusinessTypes(callable $mutator): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $module = DB::table('modules')
            ->where('code', 'services_catalog')
            ->first(['id', 'business_types']);

        if (! $module) {
            return;
        }

        $businessTypes = [];
        $rawValue = $module->business_types ?? null;

        if (is_string($rawValue) && trim($rawValue) !== '') {
            $decoded = json_decode($rawValue, true);
            if (is_array($decoded)) {
                $businessTypes = $decoded;
            }
        } elseif (is_array($rawValue)) {
            $businessTypes = $rawValue;
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $businessTypes,
        ))));

        $updated = $mutator($normalized);

        DB::table('modules')
            ->where('id', $module->id)
            ->update([
                'business_types' => json_encode($updated, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);
    }
};
