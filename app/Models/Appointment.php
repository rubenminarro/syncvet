<?php

namespace App\Models;

use App\Casts\AppDateTime;
use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Turno (FASE 3, entrega 3.2a): de una agenda, con paciente, con un cliente sin paciente o
 * con un nombre y un teléfono de contacto (P4). La sucursal y el veterinario se copian de la
 * agenda (AppointmentService); el estado empieza en "a confirmar" y cambia con las
 * transiciones de la 3.2b. is_overbooked lo fija el Service: true solo si al guardarlo se
 * superponía con otro turno (sobreturno, P2). No se elimina: se cancela.
 *
 * Deuda 2 (revisado en la 3.2a): las relaciones con la agenda, la sucursal, el veterinario,
 * el servicio, el paciente, el cliente y los usuarios coinciden con las claves foráneas; los
 * instantes usan AppDateTime; los valores por defecto de la base están en $attributes.
 */
class Appointment extends Model
{
    use BelongsToClinic, Auditable;

    protected $fillable = [
        'branch_id',
        'schedule_id',
        'client_id',
        'patient_id',
        'service_id',
        'veterinarian_id',
        'contact_name',
        'contact_phone',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'is_overbooked',
        'series_id',
        'reason',
        'notes',
        'created_by',
        'confirmed_at',
        'checked_in_at',
        'attended_at',
        'no_show_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    /**
     * Los valores por defecto de las columnas: un turno recién creado ya los muestra.
     */
    protected $attributes = [
        'status' => 'pending_confirmation',
        'source' => 'reception',
        'is_overbooked' => false,
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => AppDateTime::class,
            'ends_at' => AppDateTime::class,
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'is_overbooked' => 'boolean',
            'confirmed_at' => AppDateTime::class,
            'checked_in_at' => AppDateTime::class,
            // FASE 3, entrega 3.2b (pregunta 20).
            'attended_at' => AppDateTime::class,
            'no_show_at' => AppDateTime::class,
            'cancelled_at' => AppDateTime::class,
        ];
    }

    /**
     * Si el turno es de hoy en la hora de Asunción (la zona de la aplicación): fichar y deshacer
     * el ausente solo se hacen el día del turno (FASE 3, entrega 3.2b).
     */
    public function startsToday(): bool
    {
        $timezone = config('app.timezone');

        return $this->starts_at->copy()->setTimezone($timezone)->toDateString() === now($timezone)->toDateString();
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class)->withTrashed();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
