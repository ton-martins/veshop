<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
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
        $isActive = $this->boolean('is_active', true);

        $this->merge([
            'is_active' => $isActive,
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
            'contractor_ids' => ['required', 'array', 'min:1'],
            'contractor_ids.*' => ['integer', 'exists:contractors,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'cpf' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(User::roles())],
            'job_title' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'array'],
            'preferences' => ['nullable', 'array'],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
