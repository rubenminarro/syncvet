<?php

namespace App\Http\Controllers\Api;

use App\Enums\VeterinarianStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexVeterinarianRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreVeterinarianRequest;
use App\Http\Requests\UpdateVeterinarianRequest;
use App\Http\Resources\ShowVeterinarianResource;
use App\Http\Resources\VeterinarianResource;
use App\Models\Veterinarian;
use App\Services\VeterinarianService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Veterinarios (FASE 2, entrega 2.3). Los administra el administrador; la recepción y
 * el veterinario solo listan (veterinarians.index). El listado trae los activos, salvo
 * status=inactive.
 */
class VeterinarianController extends Controller implements HasMiddleware
{
    use ApiResponse;

    private const RELATIONS = ['user.branches'];

    public function __construct(
        protected VeterinarianService $veterinarianService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:veterinarians.index', only: ['index']),
            new Middleware('permission:veterinarians.store', only: ['store']),
            new Middleware('permission:veterinarians.show', only: ['show']),
            new Middleware('permission:veterinarians.update', only: ['update']),
            new Middleware('permission:veterinarians.destroy', only: ['destroy']),
        ];
    }

    public function index(IndexVeterinarianRequest $request)
    {
        $status = $request->enum('status', VeterinarianStatus::class) ?? VeterinarianStatus::ACTIVE;

        $veterinarians = Veterinarian::query()
            ->with(self::RELATIONS)
            ->where('status', $status->value)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                // Búsqueda literal: "%" y "_" no son comodines (R-021).
                $query->where(fn ($q) => $q->whereContains('code', $search)
                    ->orWhereContains('license_number', $search)
                    ->orWhereHas('user', fn ($user) => $user->where(fn ($u) => $u->whereContains('first_name', $search)
                        ->orWhereContains('last_name', $search)
                        ->orWhereContains('name', $search))));
            })
            // P5: las sucursales del veterinario son las de su usuario.
            ->when($request->filled('branch_id'), fn ($query) => $query->whereHas(
                'user.branches',
                fn ($q) => $q->whereKey($request->integer('branch_id'))
            ))
            ->orderBy('code')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Veterinarios obtenidos correctamente.',
            VeterinarianResource::collection($veterinarians->items()),
            200,
            [
                'pagination' => [
                    'total' => $veterinarians->total(),
                    'perPage' => $veterinarians->perPage(),
                    'currentPage' => $veterinarians->currentPage(),
                    'lastPage' => $veterinarians->lastPage(),
                ],
            ]
        );
    }

    public function store(StoreVeterinarianRequest $request)
    {
        $veterinarian = $this->veterinarianService->create($request->validated());

        return $this->successResponse('Veterinario creado correctamente.', new ShowVeterinarianResource($veterinarian->load(self::RELATIONS)), 201);
    }

    public function show(Veterinarian $veterinarian)
    {
        return $this->successResponse('Veterinario encontrado.', new ShowVeterinarianResource($veterinarian->load(self::RELATIONS)), 200);
    }

    public function update(UpdateVeterinarianRequest $request, Veterinarian $veterinarian)
    {
        $veterinarian = $this->veterinarianService->update($veterinarian, $request->validated());

        return $this->successResponse('Veterinario actualizado correctamente.', new ShowVeterinarianResource($veterinarian->load(self::RELATIONS)), 200);
    }

    public function destroy(Veterinarian $veterinarian)
    {
        $this->veterinarianService->delete($veterinarian);

        return $this->successResponse('Veterinario eliminado correctamente.', null, 200);
    }
}
