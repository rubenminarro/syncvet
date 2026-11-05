<?php

namespace App\Models;

use App\Support\CurrentClinic;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Usuario de una clínica (users.clinic_id). SIN ClinicScope, a propósito: el
 * login y Sanctum buscan al usuario antes de conocer su clínica, y el scope
 * fallaría (registro R-005). Por eso:
 *
 * - Toda consulta de usuarios fuera del login y de Sanctum filtra por clinic_id
 *   de forma explícita (scope local inClinic()).
 * - El route binding de {user} resuelve solo dentro de la clínica actual: un
 *   usuario de otra clínica responde 404.
 *
 * clinic_id no va en $fillable: lo fija ClinicService (o el alta de usuarios).
 */
class User extends Authenticatable
{
    use Auditable, HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $guard_name = 'api';

    protected $fillable = [
        'name',
        'email',
        'password',
        'first_name',
        'last_name',
        'current_branch_id',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    /**
     * Usuarios de la clínica en contexto (sin clínica, fallo cerrado).
     */
    public function scopeInClinic(Builder $query): void
    {
        $query->where($this->qualifyColumn('clinic_id'), CurrentClinic::id());
    }

    /**
     * {user} en una ruta: solo dentro de la clínica actual. Un id de otra clínica no
     * se encuentra (404 RESOURCE_NOT_FOUND).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveRouteBindingQuery($this->newQuery()->inClinic(), $value, $field)->first();
    }

    public function veterinarian(): HasOne
    {
        return $this->hasOne(Veterinarian::class);
    }

    public function currentBranch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'current_branch_id');
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }
}
