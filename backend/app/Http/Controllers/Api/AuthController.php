<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\UpdateCurrentBranchRequest;
use App\Http\Requests\UpdateOwnPasswordRequest;
use App\Http\Resources\MeResource;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Login, logout, usuario autenticado, su sucursal actual y su contraseña (las dos
 * últimas, FASE 2, entrega 2.2b). Sin permisos de Spatie: cualquier usuario
 * autenticado (ROUTES_WITHOUT_PERMISSION en ClinicRolesTest).
 */
class AuthController extends Controller implements HasMiddleware
{
    use ApiResponse;

    public function __construct(
        protected AuthService $authService
    ) {
    }

    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum', except: ['login']),
            // logout sin SetCurrentClinic: con la clínica suspendida el usuario igual puede
            // revocar su token (AuthService::logout).
            new Middleware('clinic', except: ['login', 'logout']),
        ];
    }

    public function login(LoginRequest $request)
    {
        $session = $this->authService->login(
            $request->validated('email'),
            $request->validated('password'),
            (string) $request->ip()
        );

        return $this->successResponse('Sesión iniciada.', [
            'token' => $session['token'],
            'token_type' => 'Bearer',
            'expires_at' => $session['expires_at']->toISOString(),
            'user' => new MeResource($session['user']),
        ]);
    }

    public function logout(Request $request)
    {
        $this->authService->logout($request->user());

        return $this->successResponse('Sesión cerrada.');
    }

    public function me(Request $request)
    {
        return $this->successResponse('Datos del usuario.', new MeResource($this->authService->profile($request->user())));
    }

    public function updateCurrentBranch(UpdateCurrentBranchRequest $request)
    {
        $user = $this->authService->setCurrentBranch($request->user(), $request->integer('branch_id'));

        return $this->successResponse('Sucursal actual actualizada.', new MeResource($user));
    }

    public function updatePassword(UpdateOwnPasswordRequest $request)
    {
        $this->authService->changeOwnPassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return $this->successResponse('Contraseña actualizada.');
    }
}
