<?php

namespace App\Http\Controllers\Admin;

use App\Application\CRM\Services\AdminClientService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class ClientController extends Controller
{
    public function __construct(
        private readonly AdminClientService $service,
    ) {}

    /**
     * Display a listing of clients.
     */
    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    /**
     * Update the specified client in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        return $this->service->update($request, $client);
    }

    /**
     * Remove the specified client from storage.
     */
    public function destroy(Request $request, Client $client): RedirectResponse
    {
        return $this->service->destroy($request, $client);
    }
}
