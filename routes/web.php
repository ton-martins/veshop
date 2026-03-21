<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ContractorContextController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PublicShopController;
use App\Http\Controllers\Shop\Auth\ShopAuthenticatedSessionController;
use App\Http\Controllers\Shop\Auth\ShopEmailVerificationNotificationController;
use App\Http\Controllers\Shop\Auth\ShopEmailVerificationPromptController;
use App\Http\Controllers\Shop\Auth\ShopNewPasswordController;
use App\Http\Controllers\Shop\Auth\ShopPasswordResetLinkController;
use App\Http\Controllers\Shop\Auth\ShopRegisteredCustomerController;
use App\Http\Controllers\Shop\Auth\ShopVerifyEmailController;
use App\Http\Controllers\Shop\ShopAccountController;
use App\Http\Controllers\Shop\ShopFavoriteController;
use App\Http\Controllers\Shop\PaymentWebhookController;
use App\Models\Plan;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    $plansByNiche = Plan::query()
        ->where('is_active', true)
        ->where('show_on_landing', true)
        ->orderByRaw("CASE niche WHEN 'commercial' THEN 0 WHEN 'services' THEN 1 ELSE 99 END")
        ->orderBy('tier_rank')
        ->orderBy('sort_order')
        ->orderBy('name')
        ->get([
            'id',
            'niche',
            'name',
            'slug',
            'badge',
            'subtitle',
            'summary',
            'footer_message',
            'price_monthly',
            'user_limit',
            'features',
            'is_featured',
        ])
        ->groupBy('niche');

    $planSections = collect(Plan::availableNiches())
        ->map(static function (string $niche) use ($plansByNiche): array {
            $plans = collect($plansByNiche->get($niche, []))
                ->map(static function (Plan $plan): array {
                    $features = collect(is_array($plan->features) ? $plan->features : [])
                        ->filter(static fn ($feature) => ($feature['enabled'] ?? true) !== false)
                        ->map(static fn ($feature): array => [
                            'label' => trim((string) ($feature['label'] ?? '')),
                            'value' => trim((string) ($feature['value'] ?? '')),
                        ])
                        ->values()
                        ->all();

                    return [
                        'id' => $plan->id,
                        'niche' => $plan->niche,
                        'name' => $plan->name,
                        'slug' => $plan->slug,
                        'badge' => $plan->badge,
                        'subtitle' => $plan->subtitle,
                        'summary' => $plan->summary,
                        'footer_message' => $plan->footer_message,
                        'price_monthly' => $plan->price_monthly !== null ? (float) $plan->price_monthly : null,
                        'user_limit' => $plan->user_limit,
                        'features' => $features,
                        'is_featured' => (bool) $plan->is_featured,
                    ];
                })
                ->values()
                ->all();

            return [
                'value' => $niche,
                'label' => Plan::labelForNiche($niche),
                'title' => $niche === Plan::NICHE_SERVICES ? 'Planos para Serviços' : 'Planos para Comércio',
                'description' => $niche === Plan::NICHE_SERVICES
                    ? 'Estruturas para equipes de atendimento, agenda e ordens de serviço.'
                    : 'Estruturas para operação comercial, catálogo e controle de vendas.',
                'plans' => $plans,
            ];
        })
        ->values()
        ->all();

    return Inertia::render('Public/Landing', [
        'canLogin' => Route::has('login'),
        'planSections' => $planSections,
    ]);
});

Route::get('/termos-de-uso', static function () {
    return Inertia::render('Public/Legal/Terms');
})->name('legal.terms');

Route::get('/politica-de-privacidade', static function () {
    return Inertia::render('Public/Legal/Privacy');
})->name('legal.privacy');

Route::get('/home', DashboardRedirectController::class)
    ->middleware(['auth', '2fa', 'verified'])
    ->name('home');

Route::middleware(['auth', '2fa', 'verified', 'contractor.module:notifications'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

Route::get('/shop/{slug}', [PublicShopController::class, 'show'])
    ->name('shop.show');

Route::get('/shop/{slug}/produto/{product}', [PublicShopController::class, 'product'])
    ->whereNumber('product')
    ->name('shop.product.show');

Route::get('/shop/{slug}/entrar', [ShopAuthenticatedSessionController::class, 'create'])
    ->name('shop.auth.login');
Route::post('/shop/{slug}/entrar', [ShopAuthenticatedSessionController::class, 'store'])
    ->name('shop.auth.store');
Route::post('/shop/{slug}/sair', [ShopAuthenticatedSessionController::class, 'destroy'])
    ->middleware(['shop.auth', 'shop.contractor'])
    ->name('shop.auth.logout');

Route::get('/shop/{slug}/cadastro', [ShopRegisteredCustomerController::class, 'create'])
    ->name('shop.auth.register');
Route::post('/shop/{slug}/cadastro', [ShopRegisteredCustomerController::class, 'store'])
    ->name('shop.auth.register.store');
Route::get('/shop/{slug}/esqueci-senha', [ShopPasswordResetLinkController::class, 'create'])
    ->name('shop.password.request');
Route::post('/shop/{slug}/esqueci-senha', [ShopPasswordResetLinkController::class, 'store'])
    ->middleware('throttle:6,1')
    ->name('shop.password.email');
Route::get('/shop/{slug}/redefinir-senha/{token}', [ShopNewPasswordController::class, 'create'])
    ->name('shop.password.reset');
Route::post('/shop/{slug}/redefinir-senha', [ShopNewPasswordController::class, 'store'])
    ->name('shop.password.update');

Route::middleware(['shop.auth', 'shop.contractor'])->group(function () {
    Route::post('/shop/{slug}/favoritos/{product}', [ShopFavoriteController::class, 'store'])
        ->whereNumber('product')
        ->name('shop.favorites.store');

    Route::delete('/shop/{slug}/favoritos/{product}', [ShopFavoriteController::class, 'destroy'])
        ->whereNumber('product')
        ->name('shop.favorites.destroy');

    Route::get('/shop/{slug}/verificar-email', ShopEmailVerificationPromptController::class)
        ->name('shop.verification.notice');

    Route::post('/shop/{slug}/email/verificacao/reenviar', [ShopEmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('shop.verification.send');
});

Route::get('/shop/{slug}/verificar-email/{id}/{hash}', ShopVerifyEmailController::class)
    ->middleware(['signed', 'throttle:6,1'])
    ->name('shop.verification.verify');

Route::middleware(['shop.auth', 'shop.contractor', 'shop.verified'])->group(function () {
    Route::get('/shop/{slug}/conta', [ShopAccountController::class, 'show'])
        ->name('shop.account');

    Route::patch('/shop/{slug}/conta', [ShopAccountController::class, 'updateProfile'])
        ->name('shop.account.update');

    Route::post('/shop/{slug}/conta/notificacoes/ler', [ShopAccountController::class, 'markNotificationsAsRead'])
        ->name('shop.account.notifications.read');

    Route::post('/shop/{slug}/checkout', [PublicShopController::class, 'checkout'])
        ->name('shop.checkout');

    Route::get('/shop/{slug}/checkout/pagamento/{sale}', [PublicShopController::class, 'checkoutPaymentStatus'])
        ->whereNumber('sale')
        ->name('shop.checkout.payment.status');
});

Route::post('/shop/{slug}/pagamentos/webhook/{provider}', [PaymentWebhookController::class, 'handle'])
    ->name('shop.payments.webhook');

Route::get('/catalogo/{slug}', static function (string $slug) {
    return redirect()->route('shop.show', ['slug' => $slug], 301);
})->name('catalog.show');

Route::get('/catalogo/{slug}/produto/{product}', static function (string $slug, int $product) {
    return redirect()->route('shop.product.show', ['slug' => $slug, 'product' => $product], 301);
})->whereNumber('product')->name('catalog.product.show');

Route::redirect('/dashboard', '/home');

Route::middleware(['auth', '2fa'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/contractor/switch', [ContractorContextController::class, 'store'])->name('contractor.switch');
});

require __DIR__.'/master.php';
require __DIR__.'/admin.php';
require __DIR__.'/auth.php';
