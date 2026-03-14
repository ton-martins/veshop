<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Contractor;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request): Response
    {
        $this->authorizeManager($request);

        $search = trim((string) $request->string('search')->toString());
        $role = trim((string) $request->string('role')->toString());
        $status = trim((string) $request->string('status')->toString());
        $contractorId = trim((string) $request->string('contractor_id')->toString());

        $query = User::query()->with('contractors:id,name');

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('cpf', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($role !== '') {
            $query->where('role', $role);
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        if ($contractorId !== '') {
            $query->whereHas('contractors', static function ($relationQuery) use ($contractorId): void {
                $relationQuery->where('contractors.id', $contractorId);
            });
        }

        return Inertia::render('Users/Index', [
            'users' => $query
                ->orderByDesc('id')
                ->paginate(10)
                ->withQueryString()
                ->through(static function (User $user): array {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'cpf' => $user->cpf,
                        'phone' => $user->phone,
                        'role' => $user->role,
                        'job_title' => $user->job_title,
                        'is_active' => (bool) $user->is_active,
                        'contractors' => $user->contractors
                            ->map(static fn (Contractor $contractor): array => [
                                'id' => $contractor->id,
                                'name' => $contractor->name,
                            ])
                            ->values()
                            ->all(),
                        'created_at' => optional($user->created_at)?->format('d/m/Y H:i'),
                    ];
                }),
            'filters' => [
                'search' => $search,
                'role' => $role,
                'status' => $status,
                'contractor_id' => $contractorId,
            ],
            'roles' => User::roles(),
            'contractors' => Contractor::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(Request $request): Response
    {
        $this->authorizeManager($request);

        return Inertia::render('Users/Create', [
            'roles' => User::roles(),
            'contractors' => Contractor::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $contractorIds = $data['contractor_ids'] ?? [];
        unset($data['contractor_ids']);
        $data['password_changed_at'] = now();

        $user = User::query()->create($data);
        $this->syncUserContractors($user, $contractorIds);

        return redirect()
            ->route('master.users.index')
            ->with('status', 'Usuário criado com sucesso.');
    }

    /**
     * Show the form for editing a user.
     */
    public function edit(Request $request, User $user): Response
    {
        $this->authorizeManager($request);
        $user->loadMissing('contractors:id,name');

        return Inertia::render('Users/Edit', [
            'userData' => [
                'id' => $user->id,
                'contractor_ids' => $user->contractors->pluck('id')->all(),
                'name' => $user->name,
                'email' => $user->email,
                'cpf' => $user->cpf,
                'phone' => $user->phone,
                'role' => $user->role,
                'job_title' => $user->job_title,
                'address' => $user->address ?? [],
                'preferences' => $user->preferences ?? [],
                'avatar_url' => $user->avatar_url,
                'is_active' => (bool) $user->is_active,
            ],
            'roles' => User::roles(),
            'contractors' => Contractor::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * Update a user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $contractorIds = $data['contractor_ids'] ?? [];
        unset($data['contractor_ids']);

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password_changed_at'] = now();
        }

        if ((int) $request->user()->id === (int) $user->id && isset($data['is_active']) && ! $data['is_active']) {
            return back()->withErrors([
                'is_active' => 'Você não pode desativar o próprio usuário.',
            ]);
        }

        if (
            (int) $request->user()->id === (int) $user->id &&
            isset($data['role']) &&
            $data['role'] !== User::ROLE_MASTER
        ) {
            return back()->withErrors([
                'role' => 'Você não pode remover seu próprio perfil master.',
            ]);
        }

        $user->fill($data)->save();
        $this->syncUserContractors($user, $contractorIds);

        return redirect()
            ->route('master.users.index')
            ->with('status', 'Usuário atualizado com sucesso.');
    }

    /**
     * Remove a user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorizeManager($request);

        if ((int) $request->user()->id === (int) $user->id) {
            return back()->withErrors([
                'general' => 'Você não pode excluir o próprio usuário.',
            ]);
        }

        if ($user->role === User::ROLE_MASTER && User::query()->where('role', User::ROLE_MASTER)->count() <= 1) {
            return back()->withErrors([
                'general' => 'Não é possível excluir o último usuário master do sistema.',
            ]);
        }

        $user->delete();

        return redirect()
            ->route('master.users.index')
            ->with('status', 'Usuário removido com sucesso.');
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user()?->isMaster(), 403);
    }

    /**
     * @param array<int, int|string> $contractorIds
     */
    private function syncUserContractors(User $user, array $contractorIds): void
    {
        $normalizedIds = collect($contractorIds)
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->unique()
            ->values();

        $user->contractors()->sync($normalizedIds->all());
    }
}
