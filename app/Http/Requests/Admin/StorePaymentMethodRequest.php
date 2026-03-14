<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'payment_gateway_id' => $this->filled('payment_gateway_id')
                ? (int) $this->input('payment_gateway_id')
                : null,
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
        ];
    }
}

