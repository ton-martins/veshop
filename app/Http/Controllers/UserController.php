<?php

namespace App\Http\Controllers;

use App\Application\Identity\Services\MasterUserService;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(
        private readonly MasterUserService $service,
    ) {}

    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        return $this->service->index($request);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        return $this->service->store($request->validated(), $request->file('avatar'));
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        return $this->service->update($request, $user, $request->validated(), $request->file('avatar'));
    }

    /**
     * Remove a user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        return $this->service->destroy($request, $user);
    }
}
