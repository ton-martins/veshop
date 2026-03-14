<?php

namespace App\Http\Requests\Master;

use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isMaster();
    }

    /**
     * Prepare incoming data before validation.
     */
    protected function prepareForValidation(): void
    {
        $cnpj = preg_replace('/\D+/', '', (string) $this->input('cnpj', ''));
        $brandPrimaryColor = strtoupper(trim((string) $this->input('brand_primary_color', '')));
        if ($brandPrimaryColor !== '' && ! str_starts_with($brandPrimaryColor, '#')) {
            $brandPrimaryColor = "#{$brandPrimaryColor}";
        }

        $this->merge([
            'slug' => trim((string) $this->input('slug', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'phone' => trim((string) $this->input('phone', '')),
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'brand_name' => trim((string) $this->input('brand_name', '')),
            'brand_primary_color' => $brandPrimaryColor,
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
        $niche = strtolower(trim((string) $this->input('business_niche', Contractor::defaultNiche())));

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:contractors,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'cnpj' => ['nullable', 'string', 'size:14', 'unique:contractors,cnpj'],
            'slug' => ['nullable', 'string', 'max:180', 'unique:contractors,slug'],
            'timezone' => ['required', 'timezone'],
            'brand_name' => ['nullable', 'string', 'max:180'],
            'brand_primary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'business_niche' => ['required', Rule::in(Contractor::availableNiches())],
            'plan_id' => [
                'nullable',
                'integer',
                Rule::exists('plans', 'id')->where(static function ($query) use ($niche) {
                    $query
                        ->where('niche', $niche)
                        ->whereNull('deleted_at');
                }),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
