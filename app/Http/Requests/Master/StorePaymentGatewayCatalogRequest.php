<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentGatewayCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMaster();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtolower(trim((string) $this->input('code', ''))),
            'name' => trim((string) $this->input('name', '')),
            'description' => trim((string) $this->input('description', '')),
            'checkout_mode' => strtolower(trim((string) $this->input('checkout_mode', 'manual'))),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => $this->filled('sort_order') ? (int) $this->input('sort_order') : 100,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9_\\-]+$/'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'checkout_mode' => ['required', Rule::in(['manual', 'automatic'])],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:1', 'max:9999'],
        ];
    }
}

