<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePdvClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => $this->normalizeNullableText($this->input('email')),
            'phone' => $this->normalizeNullableText($this->input('phone')),
            'document' => $this->normalizeNullableText($this->input('document')),
            'city' => $this->normalizeNullableText($this->input('city')),
            'state' => $this->normalizeNullableText(strtoupper((string) $this->input('state'))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'document' => ['nullable', 'string', 'max:32'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2'],
        ];
    }

    private function normalizeNullableText(mixed $value): ?string
    {
        $safe = trim((string) ($value ?? ''));

        return $safe !== '' ? $safe : null;
    }
}

