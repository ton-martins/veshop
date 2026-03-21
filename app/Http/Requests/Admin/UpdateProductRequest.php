<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $rawVariations = $this->input('variations', []);
        if (is_string($rawVariations)) {
            $decoded = json_decode($rawVariations, true);
            $rawVariations = is_array($decoded) ? $decoded : [];
        }

        $rawRemoveGalleryIds = $this->input('remove_gallery_ids', []);
        if (is_string($rawRemoveGalleryIds)) {
            $decoded = json_decode($rawRemoveGalleryIds, true);
            $rawRemoveGalleryIds = is_array($decoded) ? $decoded : [];
        }

        $this->merge([
            'sku' => $this->normalizeNullableText($this->input('sku')),
            'description' => $this->normalizeNullableText($this->input('description')),
            'image_url' => $this->normalizeNullableText($this->input('image_url')),
            'remove_image' => $this->boolean('remove_image'),
            'remove_gallery_ids' => $this->normalizeIntegerList($rawRemoveGalleryIds),
            'category_id' => $this->normalizeNullableInteger($this->input('category_id')),
            'stock_quantity' => $this->normalizeInteger($this->input('stock_quantity')),
            'variations' => $this->normalizeVariations($rawVariations),
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
            'gallery_files' => ['nullable', 'array', 'max:5'],
            'gallery_files.*' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:6144'],
            'remove_gallery_ids' => ['nullable', 'array'],
            'remove_gallery_ids.*' => ['integer', 'min:1'],
            'variations' => ['nullable', 'array', 'max:60'],
            'variations.*.id' => ['nullable', 'integer', 'min:1'],
            'variations.*.name' => ['required_with:variations', 'string', 'max:180'],
            'variations.*.sku' => ['nullable', 'string', 'max:80'],
            'variations.*.sale_price' => ['required_with:variations', 'numeric', 'min:0'],
            'variations.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'variations.*.stock_quantity' => ['required_with:variations', 'integer', 'min:0'],
            'variations.*.is_active' => ['nullable', 'boolean'],
            'variations.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'variations.*.attributes' => ['nullable', 'array'],
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

    /**
     * @param mixed $value
     * @return list<int>
     */
    private function normalizeIntegerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param mixed $value
     * @return list<array<string, mixed>>
     */
    private function normalizeVariations(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(static fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): array {
                $rawAttributes = $row['attributes'] ?? [];
                if (is_string($rawAttributes)) {
                    $decoded = json_decode($rawAttributes, true);
                    $rawAttributes = is_array($decoded) ? $decoded : [];
                }

                $attributes = collect(is_array($rawAttributes) ? $rawAttributes : [])
                    ->mapWithKeys(static function (mixed $attributeValue, mixed $attributeKey): array {
                        $key = trim((string) $attributeKey);
                        $value = trim((string) $attributeValue);

                        if ($key === '' || $value === '') {
                            return [];
                        }

                        return [$key => $value];
                    })
                    ->all();

                return [
                    'id' => isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'sku' => $this->normalizeNullableText($row['sku'] ?? null),
                    'sale_price' => isset($row['sale_price']) ? (float) $row['sale_price'] : null,
                    'cost_price' => isset($row['cost_price']) && $row['cost_price'] !== '' ? (float) $row['cost_price'] : null,
                    'stock_quantity' => isset($row['stock_quantity']) ? max(0, (int) $row['stock_quantity']) : 0,
                    'is_active' => filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true,
                    'sort_order' => isset($row['sort_order']) ? max(0, (int) $row['sort_order']) : 0,
                    'attributes' => $attributes,
                ];
            })
            ->filter(static fn (array $row): bool => $row['name'] !== '')
            ->values()
            ->all();
    }
}
