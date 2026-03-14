<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BrandingController extends Controller
{
    public function edit(): Response
    {
        $branding = $this->resolveBranding();

        return Inertia::render('Master/Branding/Index', [
            'branding' => $branding,
            'defaults' => $this->defaultBranding(),
            'storageConfigured' => (bool) config('filesystems.disks.public.root'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:180'],
            'primary_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'accent_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:3072'],
            'icon' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,webp,svg', 'max:1024'],
            'landing_about_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'landing_why_choose_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'landing_work_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_icon' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_landing_about_image' => ['nullable', 'boolean'],
            'remove_landing_why_choose_image' => ['nullable', 'boolean'],
            'remove_landing_work_image' => ['nullable', 'boolean'],
        ]);

        $branding = $this->resolveBranding();
        $branding['name'] = trim((string) $validated['name']);
        $branding['tagline'] = trim((string) ($validated['tagline'] ?? ''));
        $branding['primary_color'] = (string) $validated['primary_color'];
        $branding['accent_color'] = (string) $validated['accent_color'];

        $removeMappings = [
            'remove_logo' => ['logo_url'],
            'remove_icon' => ['icon_url'],
            'remove_favicon' => ['favicon_url'],
            'remove_landing_about_image' => ['landing_images', 'about'],
            'remove_landing_why_choose_image' => ['landing_images', 'why_choose'],
            'remove_landing_work_image' => ['landing_images', 'work'],
        ];

        foreach ($removeMappings as $flag => $segments) {
            if (($validated[$flag] ?? false) !== true) {
                continue;
            }

            $currentUrl = Arr::get($branding, implode('.', $segments));
            $this->deleteStoredFileFromPublicUrl(is_string($currentUrl) ? $currentUrl : null);
            Arr::set($branding, implode('.', $segments), null);
        }

        $fileMappings = [
            'logo' => ['logo_url'],
            'icon' => ['icon_url'],
            'favicon' => ['favicon_url'],
            'landing_about_image' => ['landing_images', 'about'],
            'landing_why_choose_image' => ['landing_images', 'why_choose'],
            'landing_work_image' => ['landing_images', 'work'],
        ];

        foreach ($fileMappings as $fileField => $segments) {
            if (! $request->hasFile($fileField)) {
                continue;
            }

            $currentUrl = Arr::get($branding, implode('.', $segments));
            $this->deleteStoredFileFromPublicUrl(is_string($currentUrl) ? $currentUrl : null);

            $storedPath = $request->file($fileField)->store('system/branding', 'public');
            Arr::set($branding, implode('.', $segments), Storage::disk('public')->url($storedPath));
        }

        SystemSetting::putValue(SystemSetting::KEY_BRANDING, $branding);

        return back()->with('status', 'Branding do sistema atualizada com sucesso.');
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveBranding(): array
    {
        $defaults = $this->defaultBranding();
        $stored = SystemSetting::getValue(SystemSetting::KEY_BRANDING, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        $resolved = array_replace($defaults, $stored);
        $resolved['landing_images'] = array_replace(
            Arr::get($defaults, 'landing_images', []),
            Arr::get($stored, 'landing_images', []),
        );

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultBranding(): array
    {
        return [
            'name' => (string) config('branding.name', config('app.name', 'Veshop')),
            'tagline' => (string) config('branding.tagline', 'ERP para comércio e serviços'),
            'primary_color' => (string) config('branding.primary_color', '#073341'),
            'accent_color' => (string) config('branding.accent_color', '#81D86F'),
            'logo_url' => (string) config('branding.logo_url', ''),
            'icon_url' => (string) config('branding.icon_url', '/brand/icone-veshop.png'),
            'favicon_url' => (string) config('branding.favicon_url', '/brand/favicon-veshop.ico'),
            'landing_images' => [
                'about' => '/landing/images/about.png',
                'why_choose' => '/landing/images/working.jpg',
                'work' => '/landing/images/group-working.jpg',
            ],
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
