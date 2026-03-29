<?php

namespace App\Services\Payments;

use App\Models\PaymentGateway;
use App\Models\SystemSetting;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentGatewayCatalogService
{
    /**
     * @var list<string>
     */
    private const CHECKOUT_MODES = ['manual', 'automatic'];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForMaster(): array
    {
        return $this->normalizeCatalog($this->loadStoredCatalog());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function activeAutomaticForAdmin(): array
    {
        return collect($this->allForMaster())
            ->filter(static fn (array $item): bool => (bool) ($item['is_active'] ?? false))
            ->filter(static fn (array $item): bool => (string) ($item['checkout_mode'] ?? '') === 'automatic')
            ->filter(static fn (array $item): bool => (bool) ($item['is_implemented'] ?? false))
            ->map(static fn (array $item): array => [
                'value' => (string) ($item['code'] ?? ''),
                'label' => (string) ($item['name'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function create(array $payload): array
    {
        $catalog = $this->normalizeCatalog($this->loadStoredCatalog());
        $prepared = $this->normalizeItem($payload, true);
        $code = (string) ($prepared['code'] ?? '');

        if ($code === '') {
            throw ValidationException::withMessages([
                'code' => 'Informe o código do gateway.',
            ]);
        }

        if (collect($catalog)->contains(static fn (array $item): bool => (string) ($item['code'] ?? '') === $code)) {
            throw ValidationException::withMessages([
                'code' => 'Já existe um gateway com este código.',
            ]);
        }

        $catalog[] = $prepared;
        $this->persistCatalog($catalog);

        return $prepared;
    }

    public function update(string $gatewayId, array $payload): array
    {
        $catalog = $this->normalizeCatalog($this->loadStoredCatalog());
        $index = collect($catalog)->search(static fn (array $item): bool => (string) ($item['id'] ?? '') === $gatewayId);
        if ($index === false) {
            throw ValidationException::withMessages([
                'gateway' => 'Gateway não encontrado.',
            ]);
        }

        $current = $catalog[$index];
        $isNative = (bool) ($current['is_native'] ?? false);
        $prepared = $this->normalizeItem($payload, false, $current);
        $newCode = (string) ($prepared['code'] ?? '');

        if ($newCode === '') {
            throw ValidationException::withMessages([
                'code' => 'Informe o código do gateway.',
            ]);
        }

        if ($isNative && $newCode !== (string) ($current['code'] ?? '')) {
            throw ValidationException::withMessages([
                'code' => 'Não é permitido alterar o código de gateway nativo.',
            ]);
        }

        $duplicateCode = collect($catalog)
            ->filter(static fn (array $item, int $itemIndex): bool => $itemIndex !== $index)
            ->contains(static fn (array $item): bool => (string) ($item['code'] ?? '') === $newCode);

        if ($duplicateCode) {
            throw ValidationException::withMessages([
                'code' => 'Já existe um gateway com este código.',
            ]);
        }

        $catalog[$index] = $prepared;
        $this->persistCatalog($catalog);

        return $prepared;
    }

    public function delete(string $gatewayId): void
    {
        $catalog = $this->normalizeCatalog($this->loadStoredCatalog());
        $index = collect($catalog)->search(static fn (array $item): bool => (string) ($item['id'] ?? '') === $gatewayId);
        if ($index === false) {
            throw ValidationException::withMessages([
                'gateway' => 'Gateway não encontrado.',
            ]);
        }

        $target = $catalog[$index];
        if ((bool) ($target['is_native'] ?? false)) {
            throw ValidationException::withMessages([
                'gateway' => 'Não é permitido excluir gateway nativo. Desative-o para ocultar no Admin.',
            ]);
        }

        unset($catalog[$index]);
        $this->persistCatalog(array_values($catalog));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultCatalog(): array
    {
        return [
            [
                'id' => 'manual',
                'code' => PaymentGateway::PROVIDER_MANUAL,
                'name' => 'Operação manual',
                'description' => 'Registro manual de pagamentos, sem captura automática.',
                'checkout_mode' => 'manual',
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'id' => 'mercado_pago',
                'code' => PaymentGateway::PROVIDER_MERCADO_PAGO,
                'name' => 'Mercado Pago',
                'description' => 'Checkout automático com Pix, cartão e boleto.',
                'checkout_mode' => 'automatic',
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadStoredCatalog(): array
    {
        $raw = SystemSetting::getValue(SystemSetting::KEY_PAYMENT_GATEWAY_CATALOG, []);

        return is_array($raw) ? $raw : [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function normalizeCatalog(array $items): array
    {
        $defaults = collect($this->defaultCatalog())
            ->map(fn (array $item): array => $this->normalizeItem($item, false))
            ->keyBy(static fn (array $item): string => (string) ($item['code'] ?? ''))
            ->all();

        $merged = $defaults;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $normalized = $this->normalizeItem($item, false);
            $code = (string) ($normalized['code'] ?? '');
            if ($code === '') {
                continue;
            }

            $merged[$code] = $normalized;
        }

        return collect($merged)
            ->map(function (array $item): array {
                $code = (string) ($item['code'] ?? '');
                $isNative = in_array($code, [PaymentGateway::PROVIDER_MANUAL, PaymentGateway::PROVIDER_MERCADO_PAGO], true);
                $isImplemented = in_array($code, PaymentGateway::SUPPORTED_PROVIDERS, true);

                return [
                    ...$item,
                    'is_native' => $isNative,
                    'is_implemented' => $isImplemented,
                    'implementation_status' => $isImplemented ? 'Disponível' : 'Implementação pendente',
                ];
            })
            ->sort(function (array $left, array $right): int {
                $leftSort = (int) ($left['sort_order'] ?? 9999);
                $rightSort = (int) ($right['sort_order'] ?? 9999);
                if ($leftSort !== $rightSort) {
                    return $leftSort <=> $rightSort;
                }

                return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>|null  $current
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item, bool $isCreate, ?array $current = null): array
    {
        $fallbackCode = (string) ($current['code'] ?? '');
        $rawCode = trim((string) ($item['code'] ?? $fallbackCode));
        $safeCode = strtolower(str_replace('-', '_', Str::slug($rawCode, '_')));
        if ($safeCode === '') {
            $safeCode = strtolower(trim($fallbackCode));
        }

        $safeMode = strtolower(trim((string) ($item['checkout_mode'] ?? ($current['checkout_mode'] ?? 'manual'))));
        if (! in_array($safeMode, self::CHECKOUT_MODES, true)) {
            $safeMode = 'manual';
        }

        $name = trim((string) ($item['name'] ?? ($current['name'] ?? '')));
        if ($name === '') {
            $name = ucfirst(str_replace('_', ' ', $safeCode));
        }

        $description = trim((string) ($item['description'] ?? ($current['description'] ?? '')));
        $rawSortOrder = $item['sort_order'] ?? ($current['sort_order'] ?? 100);
        $sortOrder = max(1, (int) $rawSortOrder);

        return [
            'id' => trim((string) ($current['id'] ?? ($item['id'] ?? ($isCreate ? (string) Str::uuid() : $safeCode)))),
            'code' => $safeCode,
            'name' => $name,
            'description' => $description,
            'checkout_mode' => $safeMode,
            'is_active' => (bool) ($item['is_active'] ?? ($current['is_active'] ?? true)),
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function persistCatalog(array $items): void
    {
        $payload = collect($items)
            ->map(function (array $item): array {
                return [
                    'id' => (string) ($item['id'] ?? ''),
                    'code' => (string) ($item['code'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'description' => (string) ($item['description'] ?? ''),
                    'checkout_mode' => (string) ($item['checkout_mode'] ?? 'manual'),
                    'is_active' => (bool) ($item['is_active'] ?? false),
                    'sort_order' => max(1, (int) ($item['sort_order'] ?? 100)),
                ];
            })
            ->filter(static fn (array $item): bool => $item['id'] !== '' && $item['code'] !== '')
            ->values()
            ->all();

        SystemSetting::putValue(SystemSetting::KEY_PAYMENT_GATEWAY_CATALOG, $payload);
    }
}

