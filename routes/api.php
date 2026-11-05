<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\ClientPatientController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\SpeciesController;
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

    // Catálogos
    Route::get('species', [SpeciesController::class, 'index'])->name('species.index');
});
