<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Support\BrazilData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMaster();
    }

    /**
     * Prepare data before validation.
     */
    protected function prepareForValidation(): void
    {
        $phone = BrazilData::normalizePhone($this->input('phone'));
        $cpf = BrazilData::normalizeCpfCnpj($this->input('cpf'));

        if ($this->has('is_active')) {
            $this->merge([
                'is_active' => $this->boolean('is_active'),
            ]);
        }

        $this->merge([
            'cpf' => $cpf !== '' ? $cpf : null,
            'phone' => $phone !== '' ? $phone : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var \App\Models\User|null $targetUser */
        $targetUser = $this->route('user');
        $targetUserId = $targetUser?->id;

        return [
            'contractor_ids' => ['required', 'array', 'min:1'],
            'contractor_ids.*' => ['integer', 'exists:contractors,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($targetUserId),
            ],
            'cpf' => ['nullable', 'string', 'size:14', 'regex:/^\d{3}\.\d{3}\.\d{3}-\d{2}$/'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\(\d{2}\)\s\d{5}-\d{4}$/'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::roles())],
            'job_title' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'array'],
            'address.cep' => ['nullable', 'string', 'regex:/^\d{5}-\d{3}$/'],
            'preferences' => ['nullable', 'array'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
