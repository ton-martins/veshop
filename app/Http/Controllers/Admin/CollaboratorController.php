<?php

namespace App\Http\Controllers\Admin;

use App\Application\People\Services\AdminCollaboratorService;
use App\Http\Controllers\Controller;
use App\Models\Collaborator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class CollaboratorController extends Controller
{
    public function __construct(
        private readonly AdminCollaboratorService $service,
    ) {}

    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->service->store($request);
    }

    public function update(Request $request, Collaborator $collaborator): RedirectResponse
    {
        return $this->service->update($request, $collaborator);
    }

    public function destroy(Request $request, Collaborator $collaborator): RedirectResponse
    {
        return $this->service->destroy($request, $collaborator);
    }
}
