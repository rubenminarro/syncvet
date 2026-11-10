<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Support\CatalogName;
use App\Traits\Auditable;
use Database\Factories\ScheduleFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agenda (FASE 3, entrega 3.1; P9 a P11): de una sucursal, con un veterinario, un servicio o
 * los dos, su horario semanal (ScheduleHour) y sus bloqueos (ScheduleBlock). La arma solo el
 * administrador (ScheduleService).
 *
 * Sin SoftDeletes (pregunta 13): se elimina solo sin turnos, con sus tramos y sus bloqueos,
 * y deja de ser uso de su veterinario y de su servicio. online_booking no forma parte de la
 * API (la columna queda en false) y no es asignable.
 */
class Schedule extends Model
{
    /** @use HasFactory<ScheduleFactory> */
    use BelongsToClinic, Auditable, HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'veterinarian_id',
        'service_id',
        'slot_minutes',
        'color',
        'is_active',
    ];

    /**
     * Los valores por defecto de las columnas: una agenda recién creada ya los muestra.
     */
    protected $attributes = [
        'slot_minutes' => 30,
        'online_booking' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'slot_minutes' => 'integer',
            'online_booking' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * El nombre, sin espacios en los extremos ni repetidos (CatalogName::clean), como
     * sucursales y servicios.
     */
    protected function name(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? CatalogName::clean($value) : $value);
    }

    /**
     * El color (#RRGGBB) se guarda en mayúsculas.
     */
    protected function color(): Attribute
    {
        return Attribute::make(set: fn ($value) => is_string($value) ? strtoupper($value) : $value);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * El veterinario, también eliminado (la ficha eliminada con una agenda no existe: la
     * agenda es uso; el withTrashed solo evita un null si se cargara uno sin la API).
     */
    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function hours(): HasMany
    {
        return $this->hasMany(ScheduleHour::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }
}
