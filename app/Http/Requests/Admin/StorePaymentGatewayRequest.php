<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePaymentGatewayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $provider = strtolower(trim((string) $this->input('provider', '')));
        $mercadoPagoAccessToken = trim((string) $this->input('mercado_pago_access_token', ''));
        $mercadoPagoWebhookSecret = trim((string) $this->input('mercado_pago_webhook_secret', ''));

        $credentials = null;
        if ($provider === 'mercado_pago') {
            $credentials = [
                'access_token' => $mercadoPagoAccessToken,
                'webhook_secret' => $mercadoPagoWebhookSecret,
            ];
        }

        $this->merge([
            'provider' => $provider,
            'name' => trim((string) $this->input('name', '')),
            'is_active' => $this->boolean('is_active', true),
            'is_default' => $this->boolean('is_default', false),
            'is_sandbox' => $this->boolean('is_sandbox', true),
            'mercado_pago_access_token' => $mercadoPagoAccessToken !== '' ? $mercadoPagoAccessToken : null,
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
            'mercado_pago_access_token' => ['nullable', 'string', 'max:255'],
            'mercado_pago_webhook_secret' => ['nullable', 'string', 'max:255'],
            'credentials' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->input('provider') !== 'mercado_pago') {
                return;
            }

            if (! $this->filled('mercado_pago_access_token')) {
                $validator->errors()->add(
                    'mercado_pago_access_token',
                    'Informe o access token do Mercado Pago.'
                );
            }
        });
    }
}
