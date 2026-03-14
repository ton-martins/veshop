<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePdvSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(static function (array $row): array {
                return [
                    'product_id' => isset($row['product_id']) ? (int) $row['product_id'] : null,
                    'quantity' => isset($row['quantity']) ? (int) $row['quantity'] : null,
                ];
            })
            ->values()
            ->all();

        $this->merge([
            'client_id' => $this->filled('client_id') ? (int) $this->input('client_id') : null,
            'payment_method_id' => $this->filled('payment_method_id') ? (int) $this->input('payment_method_id') : null,
            'installments' => $this->filled('installments') ? (int) $this->input('installments') : null,
            'discount_amount' => $this->filled('discount_amount') ? (float) $this->input('discount_amount') : 0.0,
            'surcharge_amount' => $this->filled('surcharge_amount') ? (float) $this->input('surcharge_amount') : 0.0,
            'notes' => $this->normalizeNullableText($this->input('notes')),
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['nullable', 'integer', 'exists:clients,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'installments' => ['nullable', 'integer', 'min:2', 'max:24'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'surcharge_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $safe = trim((string) ($value ?? ''));

        return $safe !== '' ? $safe : null;
    }
}

