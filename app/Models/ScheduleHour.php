<?php

namespace App\Models;

use App\Models\Concerns\BelongsToClinic;
use App\Traits\Auditable;
use Database\Factories\ScheduleHourFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tramo del horario semanal de una agenda (FASE 3, entrega 3.1): weekday de 1 (lunes) a 7
 * (domingo) y las horas de reloj de Asunción (columnas time, sin zona: no son un instante,
 * así que no llevan AppDateTime). Se guardan como "HH:MM:SS" ("09:00" → "09:00:00"), igual en
 * SQLite y en MySQL, para comparar los tramos al reemplazar el horario. No se editan: el
 * reemplazo borra los que no están y crea los nuevos (ScheduleService::replaceHours).
 */
class ScheduleHour extends Model
{
    /** @use HasFactory<ScheduleHourFactory> */
    use BelongsToClinic, Auditable, HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'schedule_id',
        'weekday',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }

    protected function startsAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => self::clockTime($value));
    }

    protected function endsAt(): Attribute
    {
        return Attribute::make(set: fn ($value) => self::clockTime($value));
    }

    /**
     * "09:00" → "09:00:00"; "09:00:00" queda igual.
     */
    public static function clockTime(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1 ? "{$value}:00" : $value;
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }
}
