<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $provider = strtolower(trim((string) $this->input('provider', '')));
        $mercadoPagoWebhookSecret = trim((string) $this->input('mercado_pago_webhook_secret', ''));

        $credentials = null;
        if ($provider === PaymentGateway::PROVIDER_MERCADO_PAGO && $mercadoPagoWebhookSecret !== '') {
            $credentials = [
                'webhook_secret' => $mercadoPagoWebhookSecret,
            ];
        }

        $this->merge([
            'provider' => $provider,
            'name' => trim((string) $this->input('name', '')),
            'is_active' => $this->boolean('is_active', true),
            'is_default' => $this->boolean('is_default', false),
            'is_sandbox' => $this->boolean('is_sandbox', true),
            'mercado_pago_webhook_secret' => $mercadoPagoWebhookSecret !== '' ? $mercadoPagoWebhookSecret : null,
            'credentials' => $credentials,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(PaymentGateway::SUPPORTED_PROVIDERS)],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'is_default' => ['required', 'boolean'],
            'is_sandbox' => ['required', 'boolean'],
            'mercado_pago_webhook_secret' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
        ];
    }
}

