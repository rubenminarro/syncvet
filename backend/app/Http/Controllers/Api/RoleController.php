<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Traits\ApiResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Roles que se asignan a un usuario (FASE 2, entrega 2.2a): la lista cerrada de
 * UserRole, con su etiqueta, sin paginar.
 */
class RoleController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public static function middleware(): array
    {
        return [
            new Middleware('permission:roles.index', only: ['index']),
        ];
    }

    public function index()
    {
        return $this->successResponse('Roles obtenidos correctamente.', RoleResource::collection(UserRole::cases()), 200);
    }
}
