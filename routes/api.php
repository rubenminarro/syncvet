<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\BranchWarehouseController;
use App\Http\Controllers\Api\BreedController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPatientController;
use App\Http\Controllers\Api\ClinicLogoController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PatientPhotoController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SpeciesController;
use App\Http\Controllers\Api\SystemSettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VeterinarianController;
use App\Http\Controllers\Api\WarehouseController;
use Illuminate\Support\Facades\Route;

// Autenticación. login es público; logout y me exigen token (middleware de AuthController).
// me fija la clínica; logout no, para que el usuario cierre su sesión aunque la clínica esté
// suspendida. Sin permiso de Spatie: cualquier usuario autenticado.
Route::post('login', [AuthController::class, 'login'])->name('auth.login');
Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
Route::get('me', [AuthController::class, 'me'])->name('auth.me');
// Perfil (FASE 2, entrega 2.2b): la sucursal actual y la contraseña propia, con token y clínica.
Route::patch('me/current-branch', [AuthController::class, 'updateCurrentBranch'])->name('auth.current-branch');
Route::patch('me/password', [AuthController::class, 'updatePassword'])->name('auth.password');

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

    // Usuarios (FASE 2, entregas 2.2a y 2.2b): solo el administrador. La baja, solo sin uso.
    Route::apiResource('users', UserController::class);
    Route::get('roles', [RoleController::class, 'index'])->name('roles.index');

    // Veterinarios (FASE 2, entrega 2.3): uno por usuario con el rol veterinario. La baja, solo sin
    // uso; un alta del mismo usuario restaura la ficha eliminada.
    Route::apiResource('veterinarians', VeterinarianController::class);

    // Servicios (FASE 2, entrega 2.4): el catálogo con código SRV-. Un servicio usado no cambia de
    // nombre ni se elimina: se inactiva.
    Route::apiResource('services', ServiceController::class);

    // Configuración de la clínica (FASE 2, entrega 2.5): sus datos, las claves de system_settings y
    // el logo (disco privado; la descarga es el archivo, como la foto del paciente).
    Route::get('system-settings', [SystemSettingsController::class, 'show'])->name('system-settings.show');
    Route::match(['put', 'patch'], 'system-settings', [SystemSettingsController::class, 'update'])->name('system-settings.update');
    Route::post('system-settings/logo', [ClinicLogoController::class, 'store'])->name('system-settings.upload-logo');
    Route::get('system-settings/logo', [ClinicLogoController::class, 'show'])->name('system-settings.show-logo');
    Route::delete('system-settings/logo', [ClinicLogoController::class, 'destroy'])->name('system-settings.delete-logo');
});
