<?php

namespace App\Application\Branding\Services;

use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Models\Contractor;
use App\Support\BrazilData;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminContractorBrandingService
{
    use ResolvesCurrentContractor;

    /**
     * Show contractor branding form.
     */
    public function edit(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $settings = (array) ($contractor->settings ?? []);
        $storageLimitGb = $this->resolveStorageLimitGb($settings);

        return Inertia::render('Admin/Branding/Index', [
            'profileContractor' => [
                'id' => $contractor->id,
                'name' => $contractor->name,
                'email' => $contractor->email,
                'phone' => $contractor->phone,
                'timezone' => $contractor->timezone,
                'brand_name' => $contractor->brand_name,
                'brand_primary_color' => $contractor->brand_primary_color,
                'brand_logo_url' => $contractor->brand_logo_url,
                'brand_avatar_url' => $contractor->brand_avatar_url,
                'business_niche' => $contractor->niche(),
                'active_plan_name' => $contractor->activePlanName(),
            ],
            'security' => [
                'require_2fa' => true,
                'require_email_verification' => (bool) ($settings['require_email_verification'] ?? true),
                'email_notifications_enabled' => (bool) ($settings['email_notifications_enabled'] ?? true),
                'admin_inactivity_timeout' => $this->resolveAdminInactivityTimeout($settings),
            ],
            'niches' => [
                'current' => $contractor->niche(),
                'options' => [
                    [
                        'value' => Contractor::NICHE_COMMERCIAL,
                        'label' => 'Comércio',
                        'description' => 'PDV, produtos, categorias, pedidos, estoque e financeiro.',
                    ],
                    [
                        'value' => Contractor::NICHE_SERVICES,
                        'label' => 'Serviços',
                        'description' => 'Catálogo de serviços, ordens de serviço e agenda de serviços.',
                    ],
                ],
            ],
            'defaults' => [
                'name' => (string) config('branding.name', config('app.name', 'Veshop')),
                'primary_color' => (string) config('branding.primary_color', '#073341'),
                'logo_url' => (string) config('branding.logo_url', ''),
                'avatar_url' => '',
            ],
            'timezones' => $this->timezoneOptions(),
            'storageConfigured' => (bool) config('filesystems.disks.public.root'),
            'storageUsage' => $this->resolveStorageUsage($contractor, $storageLimitGb),
            'supportAccess' => [
                'canApprove' => false,
                'pending' => [],
                'active' => [],
            ],
        ]);
    }

    /**
     * Update contractor branding.
     */
    public function update(Request $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $phone = BrazilData::normalizePhone($request->input('phone'));
        $request->merge([
            'phone' => $phone !== '' ? $phone : null,
        ]);

        $validated = $request->validate([
            'brand_name' => ['required', 'string', 'max:255'],
            'brand_primary_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('contractors', 'email')->ignore($contractor->id),
            ],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^\(\d{2}\)\s\d{4,5}-\d{4}$/'],
            'timezone' => ['required', 'string', Rule::in(DateTimeZone::listIdentifiers(DateTimeZone::ALL))],
            'brand_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'brand_avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_brand_logo' => ['nullable', 'boolean'],
            'remove_brand_avatar' => ['nullable', 'boolean'],
            'require_email_verification' => ['required', 'boolean'],
            'email_notifications_enabled' => ['required', 'boolean'],
            'admin_inactivity_timeout' => ['required', 'string', Rule::in(['15', '30', '60', 'keep_active'])],
        ]);

        $settings = (array) ($contractor->settings ?? []);
        $settings['require_2fa'] = true;
        $settings['require_email_verification'] = (bool) $validated['require_email_verification'];
        $settings['email_notifications_enabled'] = (bool) $validated['email_notifications_enabled'];
        $settings['admin_inactivity_timeout'] = $validated['admin_inactivity_timeout'];

        if (($validated['remove_brand_logo'] ?? false) === true) {
            $this->deleteStoredFileFromPublicUrl($contractor->brand_logo_url);
            $contractor->brand_logo_url = null;
        }

        if (($validated['remove_brand_avatar'] ?? false) === true) {
            $this->deleteStoredFileFromPublicUrl($contractor->brand_avatar_url);
            $contractor->brand_avatar_url = null;
        }

        if ($request->hasFile('brand_logo')) {
            $this->deleteStoredFileFromPublicUrl($contractor->brand_logo_url);
            $logoPath = $request->file('brand_logo')->store("contractors/{$contractor->id}/branding", 'public');
            $contractor->brand_logo_url = Storage::disk('public')->url($logoPath);
        }

        if ($request->hasFile('brand_avatar')) {
            $this->deleteStoredFileFromPublicUrl($contractor->brand_avatar_url);
            $avatarPath = $request->file('brand_avatar')->store("contractors/{$contractor->id}/branding", 'public');
            $contractor->brand_avatar_url = Storage::disk('public')->url($avatarPath);
        }

        $contractor->fill([
            'brand_name' => $validated['brand_name'],
            'brand_primary_color' => $validated['brand_primary_color'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'timezone' => $validated['timezone'],
            'settings' => $settings,
        ]);

        $contractor->save();

        return back()->with('status', 'Branding atualizada com sucesso.');
    }

    private function resolveAdminInactivityTimeout(array $settings): string
    {
        $value = trim((string) ($settings['admin_inactivity_timeout'] ?? '60'));

        return in_array($value, ['15', '30', '60', 'keep_active'], true)
            ? $value
            : '60';
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function timezoneOptions(): array
    {
        $preferred = [
            'America/Sao_Paulo',
            'America/Bahia',
            'America/Fortaleza',
            'America/Belem',
            'America/Manaus',
            'America/Campo_Grande',
            'America/Cuiaba',
            'America/Porto_Velho',
            'America/Boa_Vista',
            'America/Rio_Branco',
            'America/Noronha',
        ];

        return collect($preferred)
            ->filter(static fn (string $timezone): bool => in_array($timezone, DateTimeZone::listIdentifiers(), true))
            ->map(fn (string $timezone): array => [
                'value' => $timezone,
                'label' => $this->formatTimezoneLabel($timezone),
            ])
            ->values()
            ->all();
    }

    private function formatTimezoneLabel(string $timezone): string
    {
        $labels = [
            'America/Sao_Paulo' => 'América/São Paulo',
            'America/Belem' => 'América/Belém',
            'America/Cuiaba' => 'América/Cuiabá',
        ];

        if (array_key_exists($timezone, $labels)) {
            return $labels[$timezone];
        }

        $base = str_replace('_', ' ', $timezone);

        return str_replace('America/', 'América/', $base);
    }

    private function resolveStorageLimitGb(array $settings): ?int
    {
        $value = $settings['storage_limit_gb'] ?? null;
        if ($value === null || $value === '') {
            return null;
        }

        $parsed = (int) $value;

        return $parsed > 0 ? $parsed : null;
    }

    /**
     * @return array<string, int|float|null>
     */
    private function resolveStorageUsage(Contractor $contractor, ?int $limitGb): array
    {
        $basePath = "contractors/{$contractor->id}";
        $usedBytes = 0;

        if (Storage::disk('public')->exists($basePath)) {
            $allFiles = Storage::disk('public')->allFiles($basePath);

            foreach ($allFiles as $filePath) {
                $usedBytes += (int) Storage::disk('public')->size($filePath);
            }
        }

        $limitBytes = $limitGb ? $limitGb * 1024 * 1024 * 1024 : null;
        $percent = $limitBytes && $limitBytes > 0
            ? min(100, ($usedBytes / $limitBytes) * 100)
            : null;

        return [
            'used_bytes' => $usedBytes,
            'limit_gb' => $limitGb,
            'limit_bytes' => $limitBytes,
            'percent' => $percent,
        ];
    }

    private function deleteStoredFileFromPublicUrl(?string $publicUrl): void
    {
        if (! $publicUrl) {
            return;
        }

        $prefix = '/storage/';
        if (! str_starts_with($publicUrl, $prefix)) {
            return;
        }

        $relativePath = ltrim(substr($publicUrl, strlen($prefix)), '/');
        if ($relativePath === '') {
            return;
        }

        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }
}
