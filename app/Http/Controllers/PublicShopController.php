<?php

namespace App\Http\Controllers;

use App\Application\Storefront\Services\PublicShopService;
use App\Http\Requests\Shop\StoreShopCheckoutRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PublicShopController extends Controller
{
    public function __construct(
        private readonly PublicShopService $service,
    ) {}

    public function show(string $slug): Response
    {
        return $this->service->show($slug);
    }

    public function product(string $slug, int $product): RedirectResponse
    {
        return $this->service->product($slug, $product);
    }

    public function bookService(Request $request, string $slug): RedirectResponse
    {
        return $this->service->bookService($request, $slug);
    }

    public function checkout(StoreShopCheckoutRequest $request, string $slug): RedirectResponse
    {
        return $this->service->checkout($request, $slug);
    }

    public function checkoutPaymentStatus(Request $request, string $slug, int $sale): JsonResponse
    {
        return $this->service->checkoutPaymentStatus($request, $slug, $sale);
    }
}
