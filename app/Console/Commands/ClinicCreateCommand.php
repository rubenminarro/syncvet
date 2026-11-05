<?php

namespace App\Console\Commands;

use App\Exceptions\ClinicException;
use App\Models\Branch;
use App\Models\Clinic;
use App\Models\Warehouse;
use App\Services\ClinicService;
use App\Support\CurrentClinic;
use App\Support\UserPassword;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Alta de una clínica (FASE 0, entrega 0.4): la clínica, su sucursal SUC-01, su
 * depósito DEP-01 y su primer usuario, con el rol administrador. Todo lo hace
 * ClinicService::create, en una transacción. No hay alta de clínicas por la API.
 *
 * - Con interacción pide lo que no vino por opción, y la contraseña oculta y con
 *   confirmación (UserPassword: al menos 12 caracteres, con mayúsculas, minúsculas,
 *   números y símbolos, y que no aparezca en filtraciones conocidas).
 * - Sin interacción (--no-interaction) exige todas las opciones y genera la
 *   contraseña, que muestra una sola vez. La contraseña nunca es una opción: no
 *   queda en el historial de la consola.
 * - Un dato inválido o un error de negocio (RUC o email repetido) termina con
 *   código 1 y el mensaje, sin traza y sin crear nada. El RUC y el email se
 *   verifican antes de pedir la contraseña.
 * - Si Have I Been Pwned no responde, la contraseña escrita se acepta y la consola
 *   lo avisa (R-011).
 */
class ClinicCreateCommand extends Command
{
    protected $signature = 'clinic:create
        {--name= : Nombre de la clínica}
        {--legal-name= : Razón social}
        {--ruc= : RUC, sin el dígito verificador}
        {--ruc-dv= : Dígito verificador del RUC}
        {--admin-email= : Email del administrador (es su login)}
        {--admin-first-name= : Nombre del administrador}
        {--admin-last-name= : Apellido del administrador}';

    protected $description = 'Crea una clínica con su sucursal, su depósito y su primer administrador';

    /**
     * Opción => [pregunta, reglas]. Los límites son los de las columnas.
     */
    private const FIELDS = [
        'name' => ['Nombre de la clínica', ['required', 'string', 'max:100']],
        'legal-name' => ['Razón social', ['required', 'string', 'max:150']],
        'ruc' => ['RUC, sin el dígito verificador', ['required', 'regex:/^\d{1,20}$/']],
        'ruc-dv' => ['Dígito verificador del RUC', ['required', 'regex:/^\d$/']],
        'admin-email' => ['Email del administrador', ['required', 'string', 'ascii', 'email', 'max:255']],
        'admin-first-name' => ['Nombre del administrador', ['required', 'string', 'max:100']],
        'admin-last-name' => ['Apellido del administrador', ['required', 'string', 'max:100']],
    ];

    public function handle(ClinicService $clinics): int
    {
        $values = $this->collectValues();

        $errors = $this->errors($values);

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        // RUC y email antes de la contraseña: un dato de negocio inválido no hace
        // escribirla para nada. create() los vuelve a verificar en su transacción.
        try {
            $clinics->ensureCanCreate($values['ruc'], $values['ruc-dv'], $values['admin-email']);
        } catch (ClinicException $e) {
            $this->components->error("{$e->getMessage()} ({$e->getErrorCode()})");

            return self::FAILURE;
        }

        $generated = ! $this->input->isInteractive();
        $password = $generated ? UserPassword::generate() : $this->askPassword();

        // Solo la escrita se consulta en Have I Been Pwned: la generada es aleatoria.
        if (UserPassword::leakCheckSkipped()) {
            $this->components->warn(UserPassword::LEAK_CHECK_SKIPPED_WARNING);
        }

        try {
            $clinic = $clinics->create(
                [
                    'name' => $values['name'],
                    'legal_name' => $values['legal-name'],
                    'ruc' => $values['ruc'],
                    'ruc_dv' => $values['ruc-dv'],
                ],
                [
                    'email' => $values['admin-email'],
                    'first_name' => $values['admin-first-name'],
                    'last_name' => $values['admin-last-name'],
                    'password' => $password,
                ]
            );
        } catch (ClinicException $e) {
            $this->components->error("{$e->getMessage()} ({$e->getErrorCode()})");

            return self::FAILURE;
        }

        $this->report($clinic, $values['admin-email']);

        if ($generated) {
            $this->components->warn('Contraseña generada del administrador. Se muestra solo esta vez: guardala ahora.');
            $this->line("  {$password}");
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * Las opciones recibidas y, con interacción, las que faltan.
     *
     * @return array<string, mixed>
     */
    private function collectValues(): array
    {
        $values = [];

        foreach (self::FIELDS as $option => [$label]) {
            $value = $this->option($option);

            if (($value === null || $value === '') && $this->input->isInteractive()) {
                $value = text(label: $label, validate: fn (string $answer) => $this->fieldError($option, trim($answer)));
            }

            $values[$option] = is_string($value) ? trim($value) : $value;
        }

        return $values;
    }

    private function askPassword(): string
    {
        $password = password(
            label: 'Contraseña del administrador',
            hint: 'Al menos 12 caracteres, con mayúsculas, minúsculas, números y símbolos.',
            validate: fn (string $answer) => UserPassword::error($answer)
        );

        password(
            label: 'Repetí la contraseña',
            validate: fn (string $answer) => $answer === $password ? null : 'Las contraseñas no coinciden.'
        );

        return $password;
    }

    private function fieldError(string $option, mixed $value): ?string
    {
        $validator = Validator::make([$option => $value], [$option => self::FIELDS[$option][1]], $this->messages());

        return $validator->fails() ? $validator->errors()->first($option) : null;
    }

    /**
     * @param array<string, mixed> $values
     * @return list<string>
     */
    private function errors(array $values): array
    {
        $rules = array_map(fn (array $field) => $field[1], self::FIELDS);

        return Validator::make($values, $rules, $this->messages())->errors()->all();
    }

    /**
     * Mensajes con voseo. Llevan la opción, para corregir el comando sin interacción.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        $messages = [];

        foreach (self::FIELDS as $option => [$label]) {
            $messages["{$option}.required"] = "Completá: {$label} (--{$option}).";
            $messages["{$option}.string"] = "{$label}: ingresá un texto (--{$option}).";
            $messages["{$option}.max"] = "{$label}: no puede tener más de :max caracteres (--{$option}).";
        }

        return [
            ...$messages,
            'ruc.regex' => 'El RUC lleva solo números (hasta 20), sin el dígito verificador (--ruc).',
            'ruc-dv.regex' => 'El dígito verificador del RUC es un solo número (--ruc-dv).',
            'admin-email.ascii' => 'El email del administrador no puede tener acentos ni caracteres especiales (--admin-email).',
            'admin-email.email' => 'Ingresá un email válido para el administrador (--admin-email).',
        ];
    }

    private function report(Clinic $clinic, string $email): void
    {
        [$branch, $warehouse] = CurrentClinic::run($clinic, fn () => [
            Branch::where('is_default', true)->first(),
            Warehouse::where('is_default', true)->first(),
        ]);

        $this->components->info('Clínica creada.');
        $this->components->twoColumnDetail('Clínica', "{$clinic->name} (id {$clinic->id})");
        $this->components->twoColumnDetail('RUC', "{$clinic->ruc}-{$clinic->ruc_dv}");
        $this->components->twoColumnDetail('Sucursal', "{$branch->code} {$branch->name} (establecimiento {$branch->establishment})");
        $this->components->twoColumnDetail('Depósito', "{$warehouse->code} {$warehouse->name}");
        $this->components->twoColumnDetail('Administrador', mb_strtolower($email));
        $this->newLine();
    }
}
