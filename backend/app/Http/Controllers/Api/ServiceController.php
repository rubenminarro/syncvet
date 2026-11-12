<?php

namespace App\Http\Controllers\Api;

use App\Enums\ServiceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexServiceRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreServiceRequest;
use App\Http\Requests\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\ShowServiceResource;
use App\Models\Service;
use App\Services\ServiceCatalogService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Servicios del catálogo (FASE 2, entrega 2.4). Los administra el administrador; la
 * recepción y el veterinario listan y ven (services.index y services.show). El listado
 * trae los activos; status filtra por un estado (y manda), e include_inactive=1 sin
 * status trae activos e inactivos, como veterinarios.
 */
class ServiceController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected ServiceCatalogService $serviceCatalogService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:services.index', only: ['index']),
            new Middleware('permission:services.store', only: ['store']),
            new Middleware('permission:services.show', only: ['show']),
            new Middleware('permission:services.update', only: ['update']),
            new Middleware('permission:services.destroy', only: ['destroy']),
        ];
    }

    public function index(IndexServiceRequest $request)
    {
        // status manda; sin status, solo los activos, salvo include_inactive=1.
        $status = $request->enum('status', ServiceStatus::class)
            ?? ($request->boolean('include_inactive') ? null : ServiceStatus::ACTIVE);
        $category = $request->input('category');

        $services = Service::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($category !== null, fn ($query) => $query->where('category', $category))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                // Búsqueda literal: "%" y "_" no son comodines (R-021).
                $query->where(fn ($q) => $q->whereContains('code', $search)->orWhereContains('name', $search));
            })
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Servicios obtenidos correctamente.',
            ServiceResource::collection($services->items()),
            200,
            $this->paginationMeta($services)
        );
    }

    public function store(StoreServiceRequest $request)
    {
        $service = $this->serviceCatalogService->create($request->validated());

        return $this->successResponse('Servicio creado correctamente.', new ShowServiceResource($service), 201);
    }

    public function show(Service $service)
    {
        return $this->successResponse('Servicio encontrado.', new ShowServiceResource($service), 200);
    }

    public function update(UpdateServiceRequest $request, Service $service)
    {
        $service = $this->serviceCatalogService->update($service, $request->validated());

        return $this->successResponse('Servicio actualizado correctamente.', new ShowServiceResource($service), 200);
    }

    public function destroy(Service $service)
    {
        $this->serviceCatalogService->delete($service);

        return $this->successResponse('Servicio eliminado correctamente.', null, 200);
    }
}
