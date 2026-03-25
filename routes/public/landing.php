<?php

use App\Http\Controllers\PublicSite\LandingController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', LandingController::class)->name('landing');

Route::get('/termos-de-uso', static function () {
    return Inertia::render('Public/Legal/Terms');
})->name('legal.terms');

Route::get('/politica-de-privacidade', static function () {
    return Inertia::render('Public/Legal/Privacy');
})->name('legal.privacy');

Route::get('/mockups/loja-app-nativo', static function () {
    return Inertia::render('Public/StorefrontNativeMockup');
})->name('mockups.storefront.native');

Route::get('/catalogo/{slug}', static function (string $slug) {
    return redirect()->route('shop.show', ['slug' => $slug], 301);
})->name('catalog.show');

Route::get('/catalogo/{slug}/produto/{product}', static function (string $slug, int $product) {
    return redirect()->route('shop.product.show', ['slug' => $slug, 'product' => $product], 301);
})->whereNumber('product')->name('catalog.product.show');
