<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\ListFilters;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\ShowUserResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\UserService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Usuarios de la clínica (FASE 2, entregas 2.2a y 2.2b). Solo el administrador (P2).
 * El listado muestra solo los activos, salvo include_inactive=1. User no
 * tiene el scope de la clínica: el listado filtra con inClinic() y el route binding
 * de {user} responde 404 a un usuario de otra clínica.
 */
class UserController extends Controller implements HasMiddleware
{
    use ApiResponse;

    private const RELATIONS = ['roles', 'branches'];

    public function __construct(
        protected UserService $userService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('permission:users.index', only: ['index']),
            new Middleware('permission:users.store', only: ['store']),
            new Middleware('permission:users.show', only: ['show']),
            new Middleware('permission:users.update', only: ['update']),
            new Middleware('permission:users.destroy', only: ['destroy']),
        ];
    }

    public function index(IndexUserRequest $request)
    {
        $users = User::query()
            ->inClinic()
            ->with(self::RELATIONS)
            ->when(! $request->boolean('include_inactive'), fn ($query) => $query->active())
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->search;

                // Búsqueda literal: "%" y "_" no son comodines (R-021).
                $query->where(fn ($q) => $q->whereContains('first_name', $search)
                    ->orWhereContains('last_name', $search)
                    ->orWhereContains('email', $search));
            })
            ->when($request->filled('role'), fn ($query) => $query->role($request->string('role')->toString(), 'api'))
            ->when($request->filled('branch_id'), fn ($query) => $query->whereHas(
                'branches',
                fn ($q) => $q->whereKey($request->integer('branch_id'))
            ))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->paginate($request->integer('per_page', ListFilters::DEFAULT_PER_PAGE));

        return $this->successResponse(
            'Usuarios obtenidos correctamente.',
            UserResource::collection($users->items()),
            200,
            $this->paginationMeta($users)
        );
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create($request->validated());

        return $this->successResponse('Usuario creado correctamente.', new ShowUserResource($user->load(self::RELATIONS)), 201);
    }

    public function show(User $user)
    {
        return $this->successResponse('Usuario encontrado.', new ShowUserResource($user->load(self::RELATIONS)), 200);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->validated(), $request->user());

        return $this->successResponse('Usuario actualizado correctamente.', new ShowUserResource($user->load(self::RELATIONS)), 200);
    }

    public function destroy(Request $request, User $user)
    {
        $this->userService->delete($user, $request->user());

        return $this->successResponse('Usuario eliminado correctamente.', null, 200);
    }
}
