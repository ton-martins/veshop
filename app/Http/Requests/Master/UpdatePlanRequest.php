<?php

namespace App\Http\Requests\Master;

use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRequest extends FormRequest
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
        $slug = trim((string) $this->input('slug', ''));
        $rawNiche = strtolower(trim((string) $this->input('niche', '')));
        $mergePayload = [
            'niche' => $rawNiche !== '' ? $rawNiche : Plan::defaultNiche(),
            'slug' => $slug !== '' ? $slug : null,
            'badge' => trim((string) $this->input('badge', '')),
            'subtitle' => trim((string) $this->input('subtitle', '')),
            'summary' => trim((string) $this->input('summary', '')),
            'footer_message' => trim((string) $this->input('footer_message', '')),
            'is_active' => $this->boolean('is_active', true),
            'is_featured' => $this->boolean('is_featured', false),
            'show_on_landing' => $this->boolean('show_on_landing', false),
            'user_limit' => $this->nullableInteger('user_limit'),
            'storage_limit_gb' => $this->nullableInteger('storage_limit_gb'),
            'audit_log_retention_days' => $this->nullableInteger('audit_log_retention_days'),
            'tier_rank' => $this->nullableInteger('tier_rank'),
        ];

        if ($this->has('module_codes')) {
            $moduleCodes = $this->input('module_codes', []);
            $moduleCodes = is_array($moduleCodes) ? $moduleCodes : [];
            $moduleCodes = collect($moduleCodes)
                ->map(static fn (mixed $value): string => strtolower(trim((string) $value)))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $mergePayload['module_codes'] = $moduleCodes;
        }

        $this->merge($mergePayload);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Plan|null $plan */
        $plan = $this->route('plan');
        $planId = $plan?->id;
        $niche = strtolower(trim((string) $this->input('niche', Plan::defaultNiche())));

        return [
            'niche' => ['required', Rule::in(Plan::availableNiches())],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('plans', 'name')
                    ->where(static fn ($query) => $query->where('niche', $niche))
                    ->ignore($planId),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:140',
                Rule::unique('plans', 'slug')
                    ->where(static fn ($query) => $query->where('niche', $niche))
                    ->ignore($planId),
            ],
            'badge' => ['nullable', 'string', 'max:80'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'footer_message' => ['nullable', 'string', 'max:2000'],
            'price_monthly' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'max_admin_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'user_limit' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'storage_limit_gb' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'audit_log_retention_days' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'features_text' => ['nullable', 'string', 'max:8000'],
            'features' => ['nullable', 'array'],
            'features.*.label' => ['nullable', 'string', 'max:120'],
            'features.*.value' => ['nullable', 'string', 'max:255'],
            'features.*.icon' => ['nullable', 'string', 'max:64'],
            'features.*.enabled' => ['nullable', 'boolean'],
            'module_codes' => ['nullable', 'array'],
            'module_codes.*' => [
                'string',
                'max:80',
                Rule::exists('modules', 'code')->where(static function ($query) {
                    $query->where('is_active', true);
                }),
            ],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'show_on_landing' => ['required', 'boolean'],
            'tier_rank' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000000'],
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
