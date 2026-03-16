<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShopRegisteredCustomerController extends Controller
{
    public function create(string $slug): Response|RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $shopCustomer */
        $shopCustomer = Auth::guard('shop')->user();

        if ($shopCustomer && (int) $shopCustomer->contractor_id === (int) $contractor->id) {
            if ($contractor->requiresEmailVerification() && ! $shopCustomer->hasVerifiedEmail()) {
                return redirect()->route('shop.verification.notice', ['slug' => $contractor->slug]);
            }

            return redirect()->route('shop.account', ['slug' => $contractor->slug]);
        }

        if ($shopCustomer) {
            Auth::guard('shop')->logout();
        }

        return Inertia::render('Public/ShopAuthRegister', [
            'contractor' => $this->toContractorPayload($contractor),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request, string $slug): RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $phone = $this->normalizePhone($validated['phone'] ?? null);
        $requiresEmailVerification = $contractor->requiresEmailVerification();

        /** @var ShopCustomer $customer */
        $customer = DB::transaction(function () use ($contractor, $validated, $email, $phone, $requiresEmailVerification): ShopCustomer {
            /** @var ShopCustomer|null $existing */
            $existing = ShopCustomer::query()
                ->withTrashed()
                ->where('contractor_id', $contractor->id)
                ->where('email', $email)
                ->first();

            if ($existing && ! $existing->trashed()) {
                throw ValidationException::withMessages([
                    'email' => 'Já existe uma conta com este e-mail nesta loja.',
                ]);
            }

            $client = $this->resolveOrCreateClient($contractor, [
                'name' => $validated['name'],
                'email' => $email,
                'phone' => $phone,
            ]);

            $payload = [
                'client_id' => $client?->id,
                'name' => trim((string) $validated['name']),
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'password' => (string) $validated['password'],
                'is_active' => true,
                'email_verified_at' => $requiresEmailVerification ? null : now(),
            ];

            if ($existing) {
                $existing->restore();
                $existing->fill($payload)->save();

                return $existing;
            }

            return ShopCustomer::query()->create([
                'contractor_id' => $contractor->id,
                ...$payload,
            ]);
        });

        Auth::guard('shop')->login($customer, true);
        $request->session()->regenerate();

        $customer->forceFill([
            'last_login_at' => now(),
        ])->save();

        if ($requiresEmailVerification && ! $customer->hasVerifiedEmail()) {
            $customer->sendEmailVerificationNotification();

            return redirect()->route('shop.verification.notice', ['slug' => $contractor->slug])
                ->with('status', 'verification-link-sent');
        }

        return redirect()
            ->route('shop.account', ['slug' => $contractor->slug])
            ->with('status', 'Conta criada com sucesso.');
    }

    private function resolveActiveContractorBySlug(string $slug): Contractor
    {
        return Contractor::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function toContractorPayload(Contractor $contractor): array
    {
        return [
            'id' => $contractor->id,
            'slug' => $contractor->slug,
            'name' => $contractor->name,
            'brand_name' => $contractor->brand_name,
            'primary_color' => $contractor->brand_primary_color,
            'logo_url' => $contractor->brand_logo_url,
            'avatar_url' => $contractor->brand_avatar_url,
        ];
    }

    /**
     * @param array{name: string, email: string, phone: string} $data
     */
    private function resolveOrCreateClient(Contractor $contractor, array $data): ?Client
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));

        if ($name === '') {
            return null;
        }

        if ($email !== '') {
            $existingByEmail = Client::query()
                ->where('contractor_id', $contractor->id)
                ->where('email', $email)
                ->first();

            if ($existingByEmail) {
                $existingByEmail->fill([
                    'name' => $name,
                    'phone' => $phone !== '' ? $phone : $existingByEmail->phone,
                    'is_active' => true,
                ])->save();

                return $existingByEmail;
            }
        }

        if ($phone !== '') {
            $existingByPhone = Client::query()
                ->where('contractor_id', $contractor->id)
                ->where('phone', $phone)
                ->first();

            if ($existingByPhone) {
                $existingByPhone->fill([
                    'name' => $name,
                    'email' => $email !== '' ? $email : $existingByPhone->email,
                    'is_active' => true,
                ])->save();

                return $existingByPhone;
            }
        }

        return Client::query()->create([
            'contractor_id' => $contractor->id,
            'name' => $name,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'is_active' => true,
        ]);
    }

    private function normalizePhone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) ($value ?? ''));

        return is_string($digits) ? trim($digits) : '';
    }
}
