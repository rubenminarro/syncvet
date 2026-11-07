<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBranchRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ShowBranchResource;
use App\Models\Branch;
use App\Services\BranchService;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Sucursales (FASE 2, entrega 2.1). Sin baja: se inactivan (P9). El listado trae
 * las activas; include_inactive=1, todas. La recepción y el veterinario solo
 * listan (pregunta 19).
 */
class BranchController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected BranchService $branchService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:branches.index', only: ['index']),
            new Middleware('permission:branches.store', only: ['store']),
            new Middleware('permission:branches.show', only: ['show']),
            new Middleware('permission:branches.update', only: ['update']),
        ];
    }

    public function index(IndexBranchRequest $request)
    {
        $branches = Branch::query()
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
            'Sucursales obtenidas correctamente.',
            BranchResource::collection($branches->items()),
            200,
            [
                'pagination' => [
                    'total' => $branches->total(),
                    'perPage' => $branches->perPage(),
                    'currentPage' => $branches->currentPage(),
                    'lastPage' => $branches->lastPage(),
                ],
            ]
        );
    }

    public function store(StoreBranchRequest $request)
    {
        $branch = $this->branchService->create($request->validated());

        return $this->successResponse(
            'Sucursal creada correctamente.',
            new ShowBranchResource($this->withWarehouses($branch)),
            201
        );
    }

    public function show(Branch $branch)
    {
        return $this->successResponse(
            'Sucursal encontrada.',
            new ShowBranchResource($this->withWarehouses($branch)),
            200
        );
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $branch = $this->branchService->update($branch, $request->validated());

        return $this->successResponse(
            'Sucursal actualizada correctamente.',
            new ShowBranchResource($this->withWarehouses($branch)),
            200
        );
    }

    private function withWarehouses(Branch $branch): Branch
    {
        return $branch->load(['warehouses' => fn ($query) => $query->orderByDesc('is_default')->orderBy('code')]);
    }
}
