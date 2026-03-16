<?php

namespace App\Http\Requests\Admin;

use App\Support\BrazilData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\(\d{2}\)\s\d{5}-\d{4}$/'],
            'document' => ['nullable', 'string', 'regex:/^(\d{3}\.\d{3}\.\d{3}-\d{2}|\d{2}\.\d{3}\.\d{3}\/\d{4}-\d{2})$/'],
            'cep' => ['nullable', 'string', 'regex:/^\d{5}-\d{3}$/'],
            'street' => ['nullable', 'string', 'max:160'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', Rule::in(BrazilData::STATE_CODES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = BrazilData::normalizePhone($this->input('phone'));
        $document = BrazilData::normalizeCpfCnpj($this->input('document'));
        $cep = BrazilData::normalizeCep($this->input('cep'));
        $state = BrazilData::normalizeState($this->input('state'));

        $this->merge([
            'phone' => $phone !== '' ? $phone : null,
            'document' => $document !== '' ? $document : null,
            'cep' => $cep !== '' ? $cep : null,
            'state' => $state !== '' ? $state : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
