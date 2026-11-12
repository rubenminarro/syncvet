<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexWarehouseRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Branch;
use App\Services\WarehouseService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * branches/{branch}/warehouses: depósitos de una sucursal (FASE 2, entrega 2.1). La
 * sucursal sale de la URL; un branch_id en el cuerpo se ignora. La edición es plana
 * (WarehouseController).
 */
class BranchWarehouseController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected WarehouseService $warehouseService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouses.index', only: ['index']),
            new Middleware('permission:warehouses.store', only: ['store']),
        ];
    }

    public function index(IndexWarehouseRequest $request, Branch $branch)
    {
        $warehouses = $branch->warehouses()
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->where('is_active', true))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                // Búsqueda literal: "%" y "_" no son comodines (R-021).
                $query->where(fn ($q) => $q->whereContains('code', $search)->orWhereContains('name', $search));
            })
            ->orderByDesc('is_default')
            ->orderBy('code')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Depósitos obtenidos correctamente.',
            WarehouseResource::collection($warehouses->items()),
            200,
            $this->paginationMeta($warehouses)
        );
    }

    public function store(StoreWarehouseRequest $request, Branch $branch)
    {
        $warehouse = $this->warehouseService->create($branch, $request->validated());

        return $this->successResponse(
            'Depósito creado correctamente.',
            new WarehouseResource($warehouse),
            201
        );
    }
}
