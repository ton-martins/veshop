<?php

namespace App\Http\Controllers\Admin;

use App\Application\Pdv\Services\AdminPdvService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CloseCashSessionRequest;
use App\Http\Requests\Admin\OpenCashSessionRequest;
use App\Http\Requests\Admin\StorePdvClientRequest;
use App\Http\Requests\Admin\StorePdvSaleRequest;
use App\Http\Requests\Admin\UpdatePdvFeaturedProductsRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class PdvController extends Controller
{
    public function __construct(
        private readonly AdminPdvService $service,
    ) {}

    public function index(Request $request): Response|RedirectResponse
    {
        return $this->service->index($request);
    }

    public function openCashSession(OpenCashSessionRequest $request): RedirectResponse
    {
        return $this->service->openCashSession($request);
    }

    public function closeCashSession(CloseCashSessionRequest $request): RedirectResponse
    {
        return $this->service->closeCashSession($request);
    }

    public function storeSale(StorePdvSaleRequest $request): RedirectResponse
    {
        return $this->service->storeSale($request);
    }

    public function updateFeaturedProducts(UpdatePdvFeaturedProductsRequest $request): RedirectResponse
    {
        return $this->service->updateFeaturedProducts($request);
    }

    public function storeClient(StorePdvClientRequest $request): RedirectResponse
    {
        return $this->service->storeClient($request);
    }
}
