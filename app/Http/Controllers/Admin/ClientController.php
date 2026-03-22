<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesCurrentContractor;
use App\Http\Requests\Admin\StoreClientRequest;
use App\Http\Requests\Admin\UpdateClientRequest;
use App\Models\Client;
use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ClientController extends Controller
{
    use ResolvesCurrentContractor;

    /**
     * Display a listing of clients.
     */
    public function index(Request $request): Response
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());

        $query = Client::query()
            ->where('contractor_id', $contractor->id);

        if ($search !== '') {
            $query->where(function ($innerQuery) use ($search): void {
                $innerQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $clients = $query
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(static function (Client $client): array {
                return [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->phone,
                    'document' => $client->document,
                    'cep' => $client->cep,
                    'street' => $client->street,
                    'number' => $client->number,
                    'complement' => $client->complement,
                    'neighborhood' => $client->neighborhood,
                    'city' => $client->city,
                    'state' => $client->state,
                    'is_active' => (bool) $client->is_active,
                    'status_label' => $client->is_active ? 'Ativo' : 'Inativo',
                    'created_at' => optional($client->created_at)?->format('d/m/Y H:i'),
                ];
            });

        $totalClients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->count();

        $activeClients = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('is_active', true)
            ->count();

        $newClientsThisMonth = Client::query()
            ->where('contractor_id', $contractor->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $uniqueCities = Client::query()
            ->where('contractor_id', $contractor->id)
            ->whereNotNull('city')
            ->distinct('city')
            ->count('city');

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'stats' => [
                'total' => $totalClients,
                'active' => $activeClients,
                'new_month' => $newClientsThisMonth,
                'cities' => $uniqueCities,
            ],
        ]);
    }

    /**
     * Store a newly created client in storage.
     */
    public function store(StoreClientRequest $request): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $data = $request->validated();
        $data['contractor_id'] = $contractor->id;

        Client::query()->create($data);

        return back()->with('status', 'Cliente criado com sucesso.');
    }

    /**
     * Update the specified client in storage.
     */
    public function update(UpdateClientRequest $request, Client $client): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $client = $this->resolveOwnedClient($contractor, $client);
        $client->fill($request->validated())->save();

        return back()->with('status', 'Cliente atualizado com sucesso.');
    }

    /**
     * Remove the specified client from storage.
     */
    public function destroy(Request $request, Client $client): RedirectResponse
    {
        $contractor = $this->resolveCurrentContractor($request);
        abort_unless($contractor, 404, 'Contratante ativo não encontrado.');

        $client = $this->resolveOwnedClient($contractor, $client);
        $client->delete();

        return back()->with('status', 'Cliente removido com sucesso.');
    }


    private function resolveOwnedClient(Contractor $contractor, Client $client): Client
    {
        abort_unless((int) $client->contractor_id === (int) $contractor->id, 404);

        return $client;
    }
}


