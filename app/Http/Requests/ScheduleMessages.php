<?php

namespace App\Http\Requests;

use App\Support\CurrentClinic;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Reglas y mensajes compartidos por el alta y la edición de agendas (FASE 3, entrega 3.1).
 * La validación revisa el formato y que cada referencia sea de la clínica; ScheduleService
 * revisa con las filas bloqueadas lo demás: al menos un veterinario o un servicio (P10), que
 * estén activos, la sucursal del veterinario, la sucursal activa y el nombre repetido.
 *
 * El horario semanal (hours) son tramos de hora de reloj de Asunción ("09:00"), en
 * múltiplos de 5 minutos, sin superponerse en el mismo día (tocarse sí) y hasta 6 por día.
 */
class ScheduleMessages
{
    public const NAME_MAX = 100;

    public const MAX_HOURS_PER_DAY = 6;

    /**
     * "HH:MM" de 00:00 a 23:55, con los minutos en múltiplos de 5.
     */
    public const CLOCK_TIME_REGEX = '/^(?:[01]\d|2[0-3]):[0-5][05]$/';

    /**
     * #RRGGBB.
     */
    public const COLOR_REGEX = '/^#[0-9A-Fa-f]{6}$/';

    public const OWNER_REQUIRED = 'Elegí un veterinario, un servicio o los dos.';

    public const VETERINARIAN_NOT_AVAILABLE = 'El veterinario no existe o está inactivo.';

    public const VETERINARIAN_NOT_IN_BRANCH = 'El veterinario no trabaja en esa sucursal.';

    public const SERVICE_NOT_AVAILABLE = 'El servicio no existe o está inactivo.';

    public const BRANCH_NOT_AVAILABLE = 'La sucursal no existe o está inactiva.';

    public const NAME_TAKEN = 'Ya hay una agenda con ese nombre en la sucursal.';

    public const WEEKDAYS = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];

    /**
     * Los datos de la agenda. En la edición, cada uno con "sometimes" delante.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function dataRules(): array
    {
        $clinicId = CurrentClinic::id();

        return [
            // Activa: lo revisa el Service (al editar se acepta la que ya tiene).
            'branch_id' => ['required', 'integer', Rule::exists('branches', 'id')->where('clinic_id', $clinicId)],
            'name' => ['required', 'string', 'min:2', 'max:' . self::NAME_MAX, 'regex:' . CatalogMessages::NAME_REGEX],
            'veterinarian_id' => [
                'nullable', 'integer',
                Rule::exists('veterinarians', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            'service_id' => [
                'nullable', 'integer',
                Rule::exists('services', 'id')->where('clinic_id', $clinicId)->whereNull('deleted_at'),
            ],
            'slot_minutes' => ['required', 'integer', 'min:5', 'max:240', 'multiple_of:5'],
            'color' => ['nullable', 'string', 'regex:' . self::COLOR_REGEX],
            'is_active' => ['required', 'boolean'],
            'hours' => ['array', 'max:' . (7 * self::MAX_HOURS_PER_DAY)],
        ];
    }

    /**
     * Los tramos del horario: van siempre que llega hours.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function hourRules(): array
    {
        return [
            'hours.*' => ['array:weekday,starts_at,ends_at'],
            'hours.*.weekday' => ['required', 'integer', 'between:1,7'],
            'hours.*.starts_at' => ['required', 'string', 'regex:' . self::CLOCK_TIME_REGEX],
            'hours.*.ends_at' => ['required', 'string', 'regex:' . self::CLOCK_TIME_REGEX],
        ];
    }

    public static function messages(): array
    {
        $clock = 'debe ser una hora HH:MM de 00:00 a 23:55, en múltiplos de 5 minutos.';

        return [
            'branch_id.required' => 'La sucursal es obligatoria.',
            'branch_id.integer' => self::BRANCH_NOT_AVAILABLE,
            'branch_id.exists' => self::BRANCH_NOT_AVAILABLE,
            'name.required' => 'El nombre de la agenda es obligatorio.',
            'name.string' => 'El nombre de la agenda debe tener el formato correcto.',
            'name.min' => 'El nombre de la agenda debe tener al menos 2 caracteres.',
            'name.max' => 'El nombre de la agenda no debe tener más de ' . self::NAME_MAX . ' caracteres.',
            'name.regex' => 'El nombre de la agenda debe tener al menos una letra y solo puede contener letras, números, espacios, puntos, guiones, apóstrofos y paréntesis.',
            'veterinarian_id.integer' => self::VETERINARIAN_NOT_AVAILABLE,
            'veterinarian_id.exists' => self::VETERINARIAN_NOT_AVAILABLE,
            'service_id.integer' => self::SERVICE_NOT_AVAILABLE,
            'service_id.exists' => self::SERVICE_NOT_AVAILABLE,
            'slot_minutes.required' => 'La duración del turno es obligatoria.',
            'slot_minutes.integer' => 'La duración del turno debe ser un número entero de minutos.',
            'slot_minutes.min' => 'La duración del turno debe estar entre 5 y 240 minutos.',
            'slot_minutes.max' => 'La duración del turno debe estar entre 5 y 240 minutos.',
            'slot_minutes.multiple_of' => 'La duración del turno debe ser un múltiplo de 5 minutos.',
            'color.string' => 'El color debe tener el formato #RRGGBB.',
            'color.regex' => 'El color debe tener el formato #RRGGBB.',
            'is_active.required' => 'El campo activo es obligatorio.',
            'is_active.boolean' => 'El campo activo debe ser verdadero o falso.',
            'hours.array' => 'El horario debe ser una lista de tramos.',
            'hours.max' => 'El horario no puede tener más de ' . (7 * self::MAX_HOURS_PER_DAY) . ' tramos.',
            'hours.*.array' => 'Cada tramo del horario lleva solo weekday, starts_at y ends_at.',
            'hours.*.weekday.required' => 'El día del tramo es obligatorio.',
            'hours.*.weekday.integer' => 'El día del tramo debe ser un número de 1 (lunes) a 7 (domingo).',
            'hours.*.weekday.between' => 'El día del tramo debe ser un número de 1 (lunes) a 7 (domingo).',
            'hours.*.starts_at.required' => 'La hora de inicio del tramo es obligatoria.',
            'hours.*.starts_at.string' => "La hora de inicio del tramo {$clock}",
            'hours.*.starts_at.regex' => "La hora de inicio del tramo {$clock}",
            'hours.*.ends_at.required' => 'La hora de fin del tramo es obligatoria.',
            'hours.*.ends_at.string' => "La hora de fin del tramo {$clock}",
            'hours.*.ends_at.regex' => "La hora de fin del tramo {$clock}",
        ];
    }

    /**
     * Cada tramo empieza antes de terminar; en el mismo día, los tramos no se superponen
     * (08:00–12:00 y 12:00–16:00 se tocan y pasan) y son hasta 6. Solo si los tramos ya
     * tienen el formato correcto.
     */
    public static function validateHours(Validator $validator, mixed $hours): void
    {
        if (! is_array($hours) || $validator->errors()->has('hours') || self::hasHourErrors($validator)) {
            return;
        }

        $byDay = [];

        foreach (array_values($hours) as $index => $hour) {
            if ($hour['starts_at'] >= $hour['ends_at']) {
                $validator->errors()->add("hours.{$index}.ends_at", 'La hora de fin del tramo debe ser posterior a la de inicio.');

                continue;
            }

            $byDay[(int) $hour['weekday']][] = $hour;
        }

        ksort($byDay);

        foreach ($byDay as $weekday => $dayHours) {
            $day = self::WEEKDAYS[$weekday];

            if (count($dayHours) > self::MAX_HOURS_PER_DAY) {
                $validator->errors()->add('hours', "El horario del {$day} tiene más de " . self::MAX_HOURS_PER_DAY . ' tramos.');

                continue;
            }

            // "HH:MM" se compara como texto. Ordenados por inicio, se superponen si uno
            // empieza antes de que termine el anterior.
            usort($dayHours, fn (array $a, array $b) => strcmp($a['starts_at'], $b['starts_at']));

            for ($i = 1; $i < count($dayHours); $i++) {
                if ($dayHours[$i]['starts_at'] < $dayHours[$i - 1]['ends_at']) {
                    $validator->errors()->add('hours', "El horario del {$day} tiene tramos que se superponen.");

                    break;
                }
            }
        }
    }

    private static function hasHourErrors(Validator $validator): bool
    {
        foreach (array_keys($validator->errors()->messages()) as $key) {
            if (str_starts_with($key, 'hours.')) {
                return true;
            }
        }

        return false;
    }
}
