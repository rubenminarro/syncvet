<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexCatalogRequest;
use App\Http\Requests\StoreSpeciesRequest;
use App\Http\Requests\UpdateSpeciesRequest;
use App\Http\Resources\SpeciesResource;
use App\Models\Species;
use App\Services\SpeciesService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Especies: las globales y las de la clínica (FASE 1, entrega 1.4). El listado
 * trae, por defecto, las activas con sus razas activas (los selectores del alta
 * de paciente); include_inactive=1 trae todas.
 */
class SpeciesController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected SpeciesService $speciesService
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

    public function index(IndexCatalogRequest $request)
    {
        $onlyActive = ! $request->boolean('include_inactive');

        $species = Species::query()
            ->when($onlyActive, fn ($query) => $query->where('is_active', true))
            ->with(['breeds' => fn ($query) => $query->when($onlyActive, fn ($q) => $q->where('is_active', true))->orderBy('name')])
            ->orderBy('name')
            ->get();

        return $this->successResponse(
            'Especies obtenidas correctamente.',
            SpeciesResource::collection($species),
            200
        );
    }

    public function store(StoreSpeciesRequest $request)
    {
        $species = $this->speciesService->create($request->validated());

        return $this->successResponse(
            'Especie creada correctamente.',
            new SpeciesResource($species->load('breeds')),
            201
        );
    }

    public function update(UpdateSpeciesRequest $request, Species $species)
    {
        $species = $this->speciesService->update($species, $request->validated());

        return $this->successResponse(
            'Especie actualizada correctamente.',
            new SpeciesResource($species->load(['breeds' => fn ($query) => $query->orderBy('name')])),
            200
        );
    }

    public function destroy(Species $species)
    {
        $this->speciesService->delete($species);

        return $this->successResponse(
            'Especie eliminada correctamente.',
            null,
            200
        );
    }
}
