<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $paymentGatewayId = $this->filled('payment_gateway_id')
            ? (int) $this->input('payment_gateway_id')
            : null;
        $checkoutMode = strtolower(trim((string) $this->input('checkout_mode', $paymentGatewayId !== null ? 'integrated' : 'manual')));
        $gatewayProvider = strtolower(trim((string) $this->input('gateway_provider', $checkoutMode === 'integrated' ? 'mercado_pago' : 'manual')));
        $mercadoPagoAccessToken = trim((string) $this->input('mercado_pago_access_token', ''));
        $mercadoPagoWebhookSecret = trim((string) $this->input('mercado_pago_webhook_secret', ''));

        $this->merge([
            'payment_gateway_id' => $paymentGatewayId,
            'code' => strtolower(trim((string) $this->input('code', ''))),
            'name' => trim((string) $this->input('name', '')),
            'is_active' => $this->boolean('is_active', true),
            'is_default' => $this->boolean('is_default', false),
            'allows_installments' => $this->boolean('allows_installments', false),
            'max_installments' => $this->filled('max_installments')
                ? (int) $this->input('max_installments')
                : null,
            'fee_fixed' => $this->filled('fee_fixed')
                ? (float) $this->input('fee_fixed')
                : null,
            'fee_percent' => $this->filled('fee_percent')
                ? (float) $this->input('fee_percent')
                : null,
            'sort_order' => $this->filled('sort_order')
                ? (int) $this->input('sort_order')
                : 0,
            'checkout_mode' => $checkoutMode,
            'gateway_provider' => $gatewayProvider,
            'gateway_name' => trim((string) $this->input('gateway_name', '')),
            'gateway_is_active' => $this->boolean('gateway_is_active', true),
            'gateway_is_default' => $this->boolean('gateway_is_default', false),
            'gateway_is_sandbox' => $this->boolean('gateway_is_sandbox', true),
            'mercado_pago_access_token' => $mercadoPagoAccessToken !== '' ? $mercadoPagoAccessToken : null,
            'mercado_pago_webhook_secret' => $mercadoPagoWebhookSecret !== '' ? $mercadoPagoWebhookSecret : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payment_gateway_id' => ['nullable', 'integer', 'exists:payment_gateways,id'],
            'code' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'allows_installments' => ['required', 'boolean'],
            'max_installments' => ['nullable', 'integer', 'min:2', 'max:24'],
            'fee_fixed' => ['nullable', 'numeric', 'min:0'],
            'fee_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'checkout_mode' => ['nullable', 'string', Rule::in(['manual', 'integrated'])],
            'gateway_provider' => ['nullable', 'string', Rule::in(['manual', 'mercado_pago'])],
            'gateway_name' => ['nullable', 'string', 'max:120'],
            'gateway_is_active' => ['nullable', 'boolean'],
            'gateway_is_default' => ['nullable', 'boolean'],
            'gateway_is_sandbox' => ['nullable', 'boolean'],
            'mercado_pago_access_token' => ['nullable', 'string', 'max:255'],
            'mercado_pago_webhook_secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
