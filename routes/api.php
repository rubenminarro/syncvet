<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BranchWarehouseController;
use App\Http\Controllers\Api\BreedController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPatientController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientPhotoController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SpeciesController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

// Autenticación. login es público; logout y me exigen token (middleware de AuthController).
// me fija la clínica; logout no, para que el usuario cierre su sesión aunque la clínica esté
// suspendida. Sin permiso de Spatie: cualquier usuario autenticado.
Route::post('login', [AuthController::class, 'login'])->name('auth.login');
Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('me', [AuthController::class, 'me'])->name('auth.me');

// 'clinic' (SetCurrentClinic) fija la clínica del usuario antes del route model binding.
Route::middleware(['auth:sanctum', 'clinic'])->scopeBindings()->group(function () {

    // Clientes (tutores)
    Route::apiResource('clients', ClientController::class);

    // Pacientes de un cliente: el client_id sale de la URL.
    Route::apiResource('clients.patients', ClientPatientController::class)->only(['index', 'store']);

    // Pacientes: editar, cambiar de dueño y eliminar fuera del cliente.
    Route::apiResource('patients', PatientController::class)->only(['index', 'show', 'update', 'destroy']);
    Route::post('patients/{patient}/change-owner', [PatientController::class, 'changeOwner'])->name('patients.change-owner');

    // Foto del paciente (FASE 1, entrega 1.5): disco privado; la descarga es el archivo.
    Route::post('patients/{patient}/photo', [PatientPhotoController::class, 'store'])->name('patients.upload-photo');
    Route::get('patients/{patient}/photo', [PatientPhotoController::class, 'show'])->name('patients.show-photo');
    Route::delete('patients/{patient}/photo', [PatientPhotoController::class, 'destroy'])->name('patients.delete-photo');

    // Catálogos: especies y sus razas (FASE 1, entrega 1.4). Las razas usan los
    // permisos de especie (R-015); scopeBindings() da 404 a una raza de otra especie.
    Route::apiResource('species', SpeciesController::class)->except(['show']);
    Route::apiResource('species.breeds', BreedController::class)->except(['show']);

    // Sucursales y depósitos (FASE 2, entrega 2.1): sin baja, se inactivan. Los depósitos se
    // crean y se listan en su sucursal y se editan fuera de ella (no cambian de sucursal).
    Route::apiResource('branches', BranchController::class)->except(['destroy']);
    Route::apiResource('branches.warehouses', BranchWarehouseController::class)->only(['index', 'store']);
    Route::apiResource('warehouses', WarehouseController::class)->only(['update']);

    // Usuarios (FASE 2, entrega 2.2a): solo el administrador. La baja y los inactivos son de la 2.2b.
    Route::apiResource('users', UserController::class)->except(['destroy']);
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
});
