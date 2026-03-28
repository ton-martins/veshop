<?php

use App\Http\Controllers\PublicShopController;
use App\Http\Controllers\Shop\Auth\ShopAuthenticatedSessionController;
use App\Http\Controllers\Shop\Auth\ShopEmailVerificationNotificationController;
use App\Http\Controllers\Shop\Auth\ShopEmailVerificationPromptController;
use App\Http\Controllers\Shop\Auth\ShopNewPasswordController;
use App\Http\Controllers\Shop\Auth\ShopPasswordResetLinkController;
use App\Http\Controllers\Shop\Auth\ShopRegisteredCustomerController;
use App\Http\Controllers\Shop\Auth\ShopVerifyEmailController;
use App\Http\Controllers\Shop\PaymentWebhookController;
use App\Http\Controllers\Shop\ShopAccountController;
use App\Http\Controllers\Shop\ShopFavoriteController;
use Illuminate\Support\Facades\Route;

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

    Route::patch('/shop/{slug}/conta/senha', [ShopAccountController::class, 'updatePassword'])
        ->name('shop.account.password.update');

    Route::post('/shop/{slug}/conta/notificacoes/ler', [ShopAccountController::class, 'markNotificationsAsRead'])
        ->name('shop.account.notifications.read');

    Route::post('/shop/{slug}/checkout', [PublicShopController::class, 'checkout'])
        ->name('shop.checkout');

    Route::post('/shop/{slug}/servicos/agendar', [PublicShopController::class, 'bookService'])
        ->name('shop.services.book');

    Route::get('/shop/{slug}/checkout/pagamento/{sale}', [PublicShopController::class, 'checkoutPaymentStatus'])
        ->whereNumber('sale')
        ->name('shop.checkout.payment.status');
});

Route::post('/shop/{slug}/pagamentos/webhook/{provider}', [PaymentWebhookController::class, 'handle'])
    ->name('shop.payments.webhook');
