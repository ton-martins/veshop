<?php

namespace App\Http\Requests\Shop;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
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
            'customer_name' => trim((string) $this->input('customer_name')),
            'customer_phone' => $this->normalizeNullableText($this->input('customer_phone')),
            'customer_email' => $this->normalizeNullableText($this->input('customer_email')),
            'notes' => $this->normalizeNullableText($this->input('notes')),
            'payment_method_id' => $this->filled('payment_method_id') ? (int) $this->input('payment_method_id') : null,
            'delivery_mode' => $this->normalizeDeliveryMode($this->input('delivery_mode')),
            'shipping_postal_code' => $this->normalizeNullableText($this->input('shipping_postal_code')),
            'shipping_street' => $this->normalizeNullableText($this->input('shipping_street')),
            'shipping_number' => $this->normalizeNullableText($this->input('shipping_number')),
            'shipping_complement' => $this->normalizeNullableText($this->input('shipping_complement')),
            'shipping_district' => $this->normalizeNullableText($this->input('shipping_district')),
            'shipping_city' => $this->normalizeNullableText($this->input('shipping_city')),
            'shipping_state' => $this->normalizeState($this->input('shipping_state')),
            'items' => $items,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_phone' => ['nullable', 'string', 'max:32', 'required_without:customer_email'],
            'customer_email' => ['nullable', 'email', 'max:255', 'required_without:customer_phone'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'delivery_mode' => ['required', 'string', 'in:pickup,delivery'],
            'shipping_postal_code' => ['nullable', 'string', 'max:16', 'required_if:delivery_mode,delivery'],
            'shipping_street' => ['nullable', 'string', 'max:160', 'required_if:delivery_mode,delivery'],
            'shipping_number' => ['nullable', 'string', 'max:24', 'required_if:delivery_mode,delivery'],
            'shipping_complement' => ['nullable', 'string', 'max:120'],
            'shipping_district' => ['nullable', 'string', 'max:120', 'required_if:delivery_mode,delivery'],
            'shipping_city' => ['nullable', 'string', 'max:120', 'required_if:delivery_mode,delivery'],
            'shipping_state' => ['nullable', 'string', 'size:2', 'required_if:delivery_mode,delivery'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $safe = trim((string) ($value ?? ''));

        return $safe !== '' ? $safe : null;
    }

    private function normalizeDeliveryMode(mixed $value): string
    {
        $safe = strtolower(trim((string) ($value ?? '')));

        return in_array($safe, ['pickup', 'delivery'], true) ? $safe : 'pickup';
    }

    private function normalizeState(mixed $value): ?string
    {
        $safe = strtoupper(trim((string) ($value ?? '')));

        return $safe !== '' ? $safe : null;
    }
}
