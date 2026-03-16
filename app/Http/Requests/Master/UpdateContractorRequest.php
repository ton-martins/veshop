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

        $moduleCodes = $this->input('module_codes', []);
        $moduleCodes = is_array($moduleCodes) ? $moduleCodes : [];
        $moduleCodes = collect($moduleCodes)
            ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'slug' => trim((string) $this->input('slug', '')),
            'email' => strtolower(trim((string) $this->input('email', ''))),
            'phone' => trim((string) $this->input('phone', '')),
            'cnpj' => $cnpj !== '' ? $cnpj : null,
            'brand_name' => trim((string) $this->input('brand_name', '')),
            'brand_primary_color' => $brandPrimaryColor,
            'business_type' => strtolower(trim((string) $this->input('business_type', ''))),
            'module_codes' => $moduleCodes,
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
            'module_codes' => ['nullable', 'array'],
            'module_codes.*' => [
                'string',
                'max:80',
                Rule::exists('modules', 'code')->where(static function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
