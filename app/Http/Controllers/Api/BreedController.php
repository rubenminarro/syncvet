<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCatalogRequest;
use App\Http\Requests\StoreBreedRequest;
use App\Http\Requests\UpdateBreedRequest;
use App\Http\Resources\BreedResource;
use App\Models\Breed;
use App\Models\Species;
use App\Services\BreedService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * species/{species}/breeds: razas de una especie (FASE 1, entrega 1.4). Usan los
 * permisos de especie (R-015; ROUTES_WITH_ANOTHER_PERMISSION en los tests). Con
 * scopeBindings(), una raza de otra especie en la URL responde 404.
 */
class BreedController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected BreedService $breedService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:species.index', only: ['index']),
            new Middleware('permission:species.store', only: ['store']),
            new Middleware('permission:species.update', only: ['update']),
            new Middleware('permission:species.destroy', only: ['destroy']),
        ];
    }

    public function index(IndexCatalogRequest $request, Species $species)
    {
        $breeds = $species->breeds()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            'Razas obtenidas correctamente.',
            BreedResource::collection($breeds),
            200
        );
    }

    public function store(StoreBreedRequest $request, Species $species)
    {
        $breed = $this->breedService->create($species, $request->validated());

        return $this->successResponse(
            'Raza creada correctamente.',
            new BreedResource($breed),
            201
        );
    }

    public function update(UpdateBreedRequest $request, Species $species, Breed $breed)
    {
        $breed = $this->breedService->update($breed, $request->validated());

        return $this->successResponse(
            'Raza actualizada correctamente.',
            new BreedResource($breed),
            200
        );
    }

    public function destroy(Species $species, Breed $breed)
    {
        $this->breedService->delete($breed);

        return $this->successResponse(
            'Raza eliminada correctamente.',
            null,
            200
        );
    }
}
