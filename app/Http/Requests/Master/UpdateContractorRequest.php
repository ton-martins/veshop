<?php

namespace App\Http\Requests\Master;

use App\Models\Contractor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractorRequest extends FormRequest
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
            'business_type' => strtolower(trim((string) $this->input('business_type', ''))),
            'override_user_limit' => $this->nullableInteger('override_user_limit'),
            'override_storage_limit_gb' => $this->nullableInteger('override_storage_limit_gb'),
            'override_audit_log_retention_days' => $this->nullableInteger('override_audit_log_retention_days'),
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
        /** @var Contractor|null $contractor */
        $contractor = $this->route('contractor');
        $contractorId = $contractor?->id;
        $niche = strtolower(trim((string) $this->input('business_niche', Contractor::defaultNiche())));
        $allowedBusinessTypes = Contractor::availableBusinessTypes($niche);

        return [
            'name' => ['required', 'string', 'max:180'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('contractors', 'email')->ignore($contractorId),
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'cnpj' => [
                'nullable',
                'string',
                'size:14',
                Rule::unique('contractors', 'cnpj')->ignore($contractorId),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('contractors', 'slug')->ignore($contractorId),
            ],
            'timezone' => ['required', 'timezone'],
            'brand_name' => ['nullable', 'string', 'max:180'],
            'brand_primary_color' => ['nullable', 'regex:/^#([A-Fa-f0-9]{6})$/'],
            'business_niche' => ['required', Rule::in(Contractor::availableNiches())],
            'business_type' => ['required', Rule::in($allowedBusinessTypes)],
            'plan_id' => [
                'nullable',
                'integer',
                Rule::exists('plans', 'id')->where(static function ($query) use ($niche) {
                    $query
                        ->where('niche', $niche)
                        ->whereNull('deleted_at');
                }),
            ],
            'override_user_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'override_storage_limit_gb' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'override_audit_log_retention_days' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function nullableInteger(string $key): ?int
    {
        if (! $this->has($key)) {
            return null;
        }

        $rawValue = $this->input($key);
        if ($rawValue === '' || $rawValue === null) {
            return null;
        }

        return (int) $rawValue;
    }
}
