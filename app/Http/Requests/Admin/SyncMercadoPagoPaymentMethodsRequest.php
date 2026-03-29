<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncMercadoPagoPaymentMethodsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $methods = collect($this->input('methods', []))
            ->map(static function (mixed $row): array {
                $item = is_array($row) ? $row : [];

                return [
                    'code' => strtolower(trim((string) ($item['code'] ?? ''))),
                    'enabled' => (bool) ($item['enabled'] ?? false),
                    'fee_fixed' => ($item['fee_fixed'] ?? null) !== null && $item['fee_fixed'] !== ''
                        ? (float) $item['fee_fixed']
                        : null,
                    'fee_percent' => ($item['fee_percent'] ?? null) !== null && $item['fee_percent'] !== ''
                        ? (float) $item['fee_percent']
                        : null,
                    'allows_installments' => (bool) ($item['allows_installments'] ?? false),
                    'max_installments' => ($item['max_installments'] ?? null) !== null && $item['max_installments'] !== ''
                        ? (int) $item['max_installments']
                        : null,
                ];
            })
            ->filter(static fn (array $row): bool => $row['code'] !== '')
            ->values()
            ->all();

        $this->merge([
            'gateway_id' => $this->filled('gateway_id') ? (int) $this->input('gateway_id') : null,
            'gateway_is_sandbox' => $this->boolean('gateway_is_sandbox', true),
            'mercado_pago_access_token' => trim((string) $this->input('mercado_pago_access_token', '')),
            'mercado_pago_webhook_secret' => trim((string) $this->input('mercado_pago_webhook_secret', '')),
            'default_code' => strtolower(trim((string) $this->input('default_code', ''))),
            'methods' => $methods,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'gateway_is_sandbox' => ['nullable', 'boolean'],
            'mercado_pago_access_token' => ['nullable', 'string', 'max:4096'],
            'mercado_pago_webhook_secret' => ['nullable', 'string', 'max:255'],
            'default_code' => ['nullable', 'string', Rule::in(PaymentMethod::INTEGRATED_CODES)],
            'methods' => ['required', 'array', 'min:1'],
            'methods.*.code' => ['required', 'string', Rule::in(PaymentMethod::INTEGRATED_CODES)],
            'methods.*.enabled' => ['required', 'boolean'],
            'methods.*.fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'methods.*.fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'methods.*.allows_installments' => ['nullable', 'boolean'],
            'methods.*.max_installments' => ['nullable', 'integer', 'min:2', 'max:24'],
        ];
    }
}
