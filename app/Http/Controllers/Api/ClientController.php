<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexClientRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\ShowClientResource;
use App\Models\Client;
use App\Services\ClientService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ClientController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ClientService $clientService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:clients.index', only: ['index']),
            new Middleware('permission:clients.store', only: ['store']),
            new Middleware('permission:clients.show', only: ['show']),
            new Middleware('permission:clients.update', only: ['update']),
            new Middleware('permission:clients.destroy', only: ['destroy']),
        ];
    }

    public function index(IndexClientRequest $request)
    {
        $clients = Client::query()
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->kind))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                $query->where(function ($q) use ($search) {
                    $q->where('document_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('business_name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        // También encuentra al dueño por el nombre o el código de su mascota.
                        ->orWhereHas('patients', fn ($p) => $p->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate($request->per_page ?? 10);

        return $this->successResponse(
            'Clientes obtenidos correctamente.',
            ClientResource::collection($clients->items()),
            200,
            [
                'pagination' => [
                    'total' => $clients->total(),
                    'perPage' => $clients->perPage(),
                    'currentPage' => $clients->currentPage(),
                    'lastPage' => $clients->lastPage(),
                ],
            ]
        );
    }

    public function store(StoreClientRequest $request)
    {
        $client = $this->clientService->create($request->validated());

        return $this->successResponse(
            'Cliente creado correctamente.',
            new ShowClientResource($client),
            201
        );
    }

    public function show(Client $client)
    {
        $client->loadMissing(['patients.species', 'patients.breed']);

        return $this->successResponse(
            'Cliente encontrado.',
            new ShowClientResource($client),
            200
        );
    }

    public function update(UpdateClientRequest $request, Client $client)
    {
        $client = $this->clientService->update($client, $request->validated());

        $client->loadMissing(['patients.species', 'patients.breed']);

        return $this->successResponse(
            'Cliente actualizado correctamente.',
            new ShowClientResource($client),
            200
        );
    }

    public function destroy(Client $client)
    {
        $this->clientService->delete($client);

        return $this->successResponse(
            'Cliente eliminado correctamente.',
            null,
            200
        );
    }
}
