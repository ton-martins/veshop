<?php

namespace App\Application\Storefront\Services;

use App\Models\AddressCity;
use App\Models\AddressState;
use App\Support\BrazilData;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AddressDirectoryService
{
    /**
     * @var array<string, string>
     */
    private const STATE_NAMES = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function stateOptions(): array
    {
        $this->ensureStatesSeeded();

        return AddressState::query()
            ->orderBy('code')
            ->get(['code', 'name'])
            ->map(static fn (AddressState $state): array => [
                'value' => (string) $state->code,
                'label' => sprintf('%s - %s', (string) $state->code, (string) $state->name),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    public function cityNamesByState(string $stateCode): array
    {
        $state = $this->findOrCreateState($stateCode);
        if (! $state) {
            return [];
        }

        $hasCities = AddressCity::query()
            ->where('address_state_id', $state->id)
            ->exists();

        if (! $hasCities || ! $state->cities_synced_at) {
            $this->syncCitiesForState($state, true);
        }

        return AddressCity::query()
            ->where('address_state_id', $state->id)
            ->orderBy('name')
            ->pluck('name')
            ->map(static fn (mixed $city): string => trim((string) $city))
            ->filter(static fn (string $city): bool => $city !== '')
            ->values()
            ->all();
    }

    /**
     * @return array{state_code: string, state_name: string, city: string}|null
     */
    public function resolveCanonicalCity(string $stateCode, string $city): ?array
    {
        $state = $this->findOrCreateState($stateCode);
        if (! $state) {
            return null;
        }

        $normalizedCity = $this->normalizeCityName($city);
        if ($normalizedCity === '') {
            return null;
        }

        $cityRecord = AddressCity::query()
            ->where('address_state_id', $state->id)
            ->where('normalized_name', $normalizedCity)
            ->first();

        if (! $cityRecord) {
            $this->syncCitiesForState($state, true);

            $cityRecord = AddressCity::query()
                ->where('address_state_id', $state->id)
                ->where('normalized_name', $normalizedCity)
                ->first();
        }

        if (! $cityRecord) {
            return null;
        }

        return [
            'state_code' => (string) $state->code,
            'state_name' => (string) $state->name,
            'city' => (string) $cityRecord->name,
        ];
    }

    private function ensureStatesSeeded(): void
    {
        $knownCount = AddressState::query()->count();
        if ($knownCount >= count(BrazilData::STATE_CODES)) {
            return;
        }

        $now = now();
        $fallbackPayload = array_map(static fn (string $code): array => [
            'code' => $code,
            'name' => self::STATE_NAMES[$code] ?? $code,
            'ibge_code' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], BrazilData::STATE_CODES);

        AddressState::query()->upsert(
            $fallbackPayload,
            ['code'],
            ['name', 'updated_at']
        );
    }

    private function findOrCreateState(string $stateCode): ?AddressState
    {
        $safeCode = strtoupper(trim($stateCode));
        if (! in_array($safeCode, BrazilData::STATE_CODES, true)) {
            return null;
        }

        $this->ensureStatesSeeded();

        $existing = AddressState::query()
            ->where('code', $safeCode)
            ->first();

        if ($existing) {
            return $existing;
        }

        return AddressState::query()->create([
            'code' => $safeCode,
            'name' => self::STATE_NAMES[$safeCode] ?? $safeCode,
        ]);
    }

    private function syncCitiesForState(AddressState $state, bool $force = false): bool
    {
        if (! $force && $state->cities_synced_at) {
            return true;
        }

        $cities = $this->fetchCitiesFromIbge((string) $state->code);
        if ($cities === []) {
            return AddressCity::query()
                ->where('address_state_id', $state->id)
                ->exists();
        }

        $now = now();
        $payload = array_map(function (array $row) use ($state, $now): array {
            return [
                'address_state_id' => (int) $state->id,
                'name' => $row['name'],
                'normalized_name' => $row['normalized_name'],
                'ibge_code' => $row['ibge_code'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }, $cities);

        AddressCity::query()->upsert(
            $payload,
            ['address_state_id', 'normalized_name'],
            ['name', 'ibge_code', 'updated_at']
        );

        $state->forceFill([
            'cities_synced_at' => $now,
        ])->save();

        return true;
    }

    /**
     * @return array<int, array{name: string, normalized_name: string, ibge_code?: string|null}>
     */
    private function fetchCitiesFromIbge(string $stateCode): array
    {
        try {
            $response = Http::baseUrl($this->baseUrl())
                ->timeout($this->timeoutSeconds())
                ->acceptJson()
                ->get(sprintf('/api/v1/localidades/estados/%s/municipios', strtoupper($stateCode)));

            if (! $response->successful()) {
                return [];
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return [];
            }

            return collect($payload)
                ->filter(static fn (mixed $row): bool => is_array($row))
                ->map(function (array $row): ?array {
                    $name = trim((string) ($row['nome'] ?? $row['name'] ?? ''));
                    if ($name === '') {
                        return null;
                    }

                    $normalized = $this->normalizeCityName($name);
                    if ($normalized === '') {
                        return null;
                    }

                    $ibgeCode = trim((string) ($row['id'] ?? $row['codigo_ibge'] ?? $row['codigoIbge'] ?? ''));

                    return [
                        'name' => $name,
                        'normalized_name' => $normalized,
                        'ibge_code' => $ibgeCode !== '' ? $ibgeCode : null,
                    ];
                })
                ->filter()
                ->unique(static fn (array $row): string => $row['normalized_name'])
                ->sortBy(static fn (array $row): string => Str::ascii(mb_strtolower($row['name'])))
                ->values()
                ->all();
        } catch (\Throwable $exception) {
            Log::warning('address_directory.ibge_cities_fetch_failed', [
                'state' => strtoupper($stateCode),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function normalizeCityName(string $value): string
    {
        $safe = Str::ascii(mb_strtolower(trim($value)));
        $safe = preg_replace('/[^a-z0-9\s-]+/', ' ', $safe) ?? '';
        $safe = preg_replace('/\s+/', ' ', $safe) ?? '';

        return trim($safe);
    }

    private function baseUrl(): string
    {
        return rtrim((string) config('services.ibge.base_url', 'https://servicodados.ibge.gov.br'), '/');
    }

    private function timeoutSeconds(): int
    {
        return max(2, (int) config('services.ibge.timeout', 10));
    }
}
