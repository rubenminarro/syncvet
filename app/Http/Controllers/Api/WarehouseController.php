<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * warehouses/{warehouse}: edición de un depósito (FASE 2, entrega 2.1). No cambia de
 * sucursal (branch_id prohibited); sin baja: se inactiva.
 */
class WarehouseController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected WarehouseService $warehouseService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:warehouses.update', only: ['update']),
        ];
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse)
    {
        $warehouse = $this->warehouseService->update($warehouse, $request->validated());

        return $this->successResponse(
            'Depósito actualizado correctamente.',
            new WarehouseResource($warehouse),
            200
        );
    }
}
