<?php

namespace App\Http\Controllers\Admin;

use App\Application\Catalog\Services\AdminProductService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private readonly AdminProductService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        return $this->service->update($request, $product);
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        return $this->service->destroy($request, $product);
    }
}
