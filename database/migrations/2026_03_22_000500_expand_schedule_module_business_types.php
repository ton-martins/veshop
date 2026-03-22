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
        $this->updateScheduleBusinessTypes(static function (array $businessTypes): array {
            $required = [
                'barbershop',
                'auto_electric',
                'mechanic',
                'accounting',
                'general_services',
            ];

            $merged = array_values(array_unique(array_merge($businessTypes, $required)));
            sort($merged);

            return $merged;
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateScheduleBusinessTypes(static function (array $businessTypes): array {
            $allowed = [
                'barbershop',
                'general_services',
            ];

            $filtered = array_values(array_filter(
                $businessTypes,
                static fn (string $value): bool => in_array($value, $allowed, true),
            ));
            sort($filtered);

            return $filtered;
        });
    }

    /**
     * @param callable(array<int, string>): array<int, string> $mutator
     */
    private function updateScheduleBusinessTypes(callable $mutator): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        $module = DB::table('modules')
            ->where('code', 'schedule')
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

