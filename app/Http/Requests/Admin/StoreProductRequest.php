<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Prepare incoming data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'sku' => $this->normalizeNullableText($this->input('sku')),
            'description' => $this->normalizeNullableText($this->input('description')),
            'image_url' => $this->normalizeNullableText($this->input('image_url')),
            'remove_image' => $this->boolean('remove_image'),
            'category_id' => $this->normalizeNullableInteger($this->input('category_id')),
            'stock_quantity' => $this->normalizeInteger($this->input('stock_quantity')),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'sku' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:4000'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'stock_quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['required', Rule::in(Product::UNITS)],
            'image_url' => ['nullable', 'url', 'max:2048'],
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $safe = trim((string) ($value ?? ''));

        return $safe !== '' ? $safe : null;
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }

    private function normalizeInteger(mixed $value): int
    {
        $parsed = (int) $value;

        return max(0, $parsed);
    }
}
