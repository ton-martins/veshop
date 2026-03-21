<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
            'slug' => trim((string) $this->input('slug', '')),
            'parent_id' => $this->normalizeNullableInteger($this->input('parent_id')),
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
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function normalizeNullableInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }
}
