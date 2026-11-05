<?php

namespace App\Models;

use App\Enums\ClinicStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Clínica (D1, registro R-002). Sin scope de clínica: es el contexto de todas
 * las demás consultas (App\Support\CurrentClinic). Se suspende, no se elimina.
 */
class Clinic extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'ruc',
        'ruc_dv',
        'timezone',
        'status',
    ];

    protected $attributes = [
        'timezone' => 'America/Asuncion',
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClinicStatus::class,
        ];
    }

    public function isActive(): bool
    {
        return $this->status === ClinicStatus::ACTIVE;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }
}
