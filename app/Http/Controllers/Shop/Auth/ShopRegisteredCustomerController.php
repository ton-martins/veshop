<?php

namespace App\Http\Controllers\Shop\Auth;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Contractor;
use App\Models\ShopCustomer;
use App\Services\ShopVerificationNotificationService;
use App\Support\BrazilData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ShopRegisteredCustomerController extends Controller
{
    public function __construct(
        private readonly ShopVerificationNotificationService $verificationNotificationService
    ) {
    }

    public function create(string $slug): Response|RedirectResponse
    {
        $contractor = $this->resolveActiveContractorBySlug($slug);
        /** @var ShopCustomer|null $shopCustomer */
        $shopCustomer = Auth::guard('shop')->user();

        if ($shopCustomer && (int) $shopCustomer->contractor_id === (int) $contractor->id) {
            if ($contractor->requiresEmailVerification() && ! $shopCustomer->hasVerifiedEmail()) {
                return redirect()->route('shop.verification.notice', ['slug' => $contractor->slug]);
            }

            return redirect()->route('shop.show', [
                'slug' => $contractor->slug,
                'conta' => 1,
            ]);
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
            'phone' => ['nullable', 'string', 'regex:/^\(\d{2}\)\s\d{5}-\d{4}$/'],
            'cep' => ['required', 'string', 'regex:/^\d{5}-\d{3}$/'],
            'street' => ['required', 'string', 'max:160'],
            'number' => ['required', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'neighborhood' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'state' => ['required', 'string', Rule::in(BrazilData::STATE_CODES)],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [], [
            'number' => 'número',
        ]);

        $email = strtolower(trim((string) $validated['email']));
        $phone = BrazilData::normalizePhone($validated['phone'] ?? null);
        $cep = BrazilData::normalizeCep($validated['cep'] ?? null);
        $state = BrazilData::normalizeState($validated['state'] ?? null);
        $street = trim((string) ($validated['street'] ?? ''));
        $number = trim((string) ($validated['number'] ?? ''));
        $complement = trim((string) ($validated['complement'] ?? ''));
        $neighborhood = trim((string) ($validated['neighborhood'] ?? ''));
        $city = trim((string) ($validated['city'] ?? ''));
        $requiresEmailVerification = $contractor->requiresEmailVerification();

        /** @var ShopCustomer $customer */
        $customer = DB::transaction(function () use (
            $contractor,
            $validated,
            $email,
            $phone,
            $cep,
            $street,
            $number,
            $complement,
            $neighborhood,
            $city,
            $state,
            $requiresEmailVerification
        ): ShopCustomer {
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
                'cep' => $cep,
                'street' => $street,
                'number' => $number,
                'complement' => $complement,
                'neighborhood' => $neighborhood,
                'city' => $city,
                'state' => $state,
            ]);

            $payload = [
                'client_id' => $client?->id,
                'name' => trim((string) $validated['name']),
                'email' => $email,
                'phone' => $phone !== '' ? $phone : null,
                'cep' => $cep !== '' ? $cep : null,
                'street' => $street !== '' ? $street : null,
                'number' => $number !== '' ? $number : null,
                'complement' => $complement !== '' ? $complement : null,
                'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
                'city' => $city !== '' ? $city : null,
                'state' => $state !== '' ? $state : null,
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
            $dispatchResult = $this->verificationNotificationService->dispatch(
                $contractor,
                $customer,
                'shop_register'
            );

            if ($dispatchResult === ShopVerificationNotificationService::RESULT_FAILED) {
                return redirect()->route('shop.verification.notice', ['slug' => $contractor->slug])
                    ->with('status', 'Conta criada, mas não foi possível enviar o e-mail agora. Use "Reenviar verificação".');
            }

            return redirect()->route('shop.verification.notice', ['slug' => $contractor->slug])
                ->with('status', 'verification-link-sent');
        }

        return redirect()
            ->route('shop.show', [
                'slug' => $contractor->slug,
                'conta' => 1,
            ])
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
            'logo_url' => $this->normalizePublicAssetUrl($contractor->brand_logo_url),
            'avatar_url' => $this->normalizePublicAssetUrl($contractor->brand_avatar_url),
        ];
    }

    private function normalizePublicAssetUrl(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $path = parse_url($value, PHP_URL_PATH);
        $normalized = is_string($path) && $path !== '' ? $path : $value;

        if (str_starts_with($normalized, '/storage/')) {
            return $normalized;
        }

        if (str_starts_with($normalized, 'storage/')) {
            return '/'.ltrim($normalized, '/');
        }

        return $value;
    }

    /**
     * @param array{
     *  name: string,
     *  email: string,
     *  phone: string,
     *  cep: string,
     *  street: string,
     *  number: string,
     *  complement: string,
     *  neighborhood: string,
     *  city: string,
     *  state: string
     * } $data
     */
    private function resolveOrCreateClient(Contractor $contractor, array $data): ?Client
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $cep = trim((string) ($data['cep'] ?? ''));
        $street = trim((string) ($data['street'] ?? ''));
        $number = trim((string) ($data['number'] ?? ''));
        $complement = trim((string) ($data['complement'] ?? ''));
        $neighborhood = trim((string) ($data['neighborhood'] ?? ''));
        $city = trim((string) ($data['city'] ?? ''));
        $state = trim((string) ($data['state'] ?? ''));

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
                    'cep' => $cep !== '' ? $cep : $existingByEmail->cep,
                    'street' => $street !== '' ? $street : $existingByEmail->street,
                    'number' => $number !== '' ? $number : $existingByEmail->number,
                    'complement' => $complement !== '' ? $complement : $existingByEmail->complement,
                    'neighborhood' => $neighborhood !== '' ? $neighborhood : $existingByEmail->neighborhood,
                    'city' => $city !== '' ? $city : $existingByEmail->city,
                    'state' => $state !== '' ? $state : $existingByEmail->state,
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
                    'cep' => $cep !== '' ? $cep : $existingByPhone->cep,
                    'street' => $street !== '' ? $street : $existingByPhone->street,
                    'number' => $number !== '' ? $number : $existingByPhone->number,
                    'complement' => $complement !== '' ? $complement : $existingByPhone->complement,
                    'neighborhood' => $neighborhood !== '' ? $neighborhood : $existingByPhone->neighborhood,
                    'city' => $city !== '' ? $city : $existingByPhone->city,
                    'state' => $state !== '' ? $state : $existingByPhone->state,
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
            'cep' => $cep !== '' ? $cep : null,
            'street' => $street !== '' ? $street : null,
            'number' => $number !== '' ? $number : null,
            'complement' => $complement !== '' ? $complement : null,
            'neighborhood' => $neighborhood !== '' ? $neighborhood : null,
            'city' => $city !== '' ? $city : null,
            'state' => $state !== '' ? $state : null,
            'is_active' => true,
        ]);
    }

}
