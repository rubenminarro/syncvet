<?php

namespace Tests\Feature\Console;

use App\Enums\AuditEvent;
use App\Models\Clinic;
use App\Models\User;
use App\Services\ClinicService;
use App\Support\CurrentClinic;
use App\Support\UserPassword;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\Concerns\InteractsWithAuth;
use Tests\TestCase;

/**
 * clinic:create (entrega 0.4): crea la clínica operativa por ClinicService::create,
 * en una transacción; la contraseña se pide (oculta y con confirmación) o se genera
 * y se muestra una sola vez, y nunca llega al log ni a la auditoría.
 */
class ClinicCreateCommandTest extends TestCase
{
    use InteractsWithAuth;

    // RUC de la ANDE: 80009735-1.
    private const OPTIONS = [
        '--name' => 'Veterinaria Central',
        '--legal-name' => 'Veterinaria Central S.A.',
        '--ruc' => '80009735',
        '--ruc-dv' => '1',
        '--admin-email' => 'Admin@Central.com.py',
        '--admin-first-name' => 'María',
        '--admin-last-name' => 'Benítez',
    ];

    private const PASSWORD = 'Interactiva#2026';

    public function test_without_interaction_it_creates_everything_and_shows_the_generated_password_once(): void
    {
        [$code, $output] = $this->runWithoutInteraction();

        $this->assertSame(0, $code, $output);

        $clinic = Clinic::where('ruc', '80009735')->sole();
        $this->assertSame(['Veterinaria Central', 'Veterinaria Central S.A.', '1'], [$clinic->name, $clinic->legal_name, $clinic->ruc_dv]);
        $this->assertSame(1, DB::table('branches')->where(['clinic_id' => $clinic->id, 'code' => 'SUC-01', 'establishment' => '001', 'is_default' => true])->count());
        $this->assertSame(1, DB::table('warehouses')->where(['clinic_id' => $clinic->id, 'code' => 'DEP-01', 'is_default' => true])->count());

        $admin = User::where('email', 'admin@central.com.py')->sole();
        $this->assertSame($clinic->id, $admin->clinic_id);
        $this->assertTrue($admin->hasRole('administrador'));

        $password = $this->generatedPassword($output);
        $this->assertSame(UserPassword::GENERATED_LENGTH, strlen($password));
        $this->assertNull(UserPassword::error($password), 'La contraseña generada no cumple la regla.');
        $this->assertTrue(Hash::check($password, $admin->password));
        $this->assertSame(1, substr_count($output, $password), 'La contraseña generada debe mostrarse una sola vez.');

        foreach (['SUC-01', 'DEP-01', 'admin@central.com.py', '80009735-1'] as $shown) {
            $this->assertStringContainsString($shown, $output);
        }
    }

    public function test_the_created_administrator_can_log_in(): void
    {
        [, $output] = $this->runWithoutInteraction();

        $this->login('admin@central.com.py', $this->generatedPassword($output))
            ->assertOk()
            ->assertJsonPath('data.user.clinic.name', 'Veterinaria Central')
            ->assertJsonPath('data.user.roles', ['administrador']);
    }

    public function test_the_creation_is_audited_in_the_new_clinic_without_user(): void
    {
        $this->runWithoutInteraction();
        $clinic = Clinic::where('ruc', '80009735')->sole();

        $log = DB::table('audit_logs')->where(['event' => AuditEvent::CREATED->value, 'auditable_type' => 'clinic'])->sole();
        $this->assertSame([$clinic->id, null], [$log->clinic_id, $log->user_id]);
    }

    public function test_with_interaction_it_asks_for_the_data_and_a_hidden_password_with_confirmation(): void
    {
        $this->artisan('clinic:create')
            ->expectsQuestion('Nombre de la clínica', 'Veterinaria Central')
            ->expectsQuestion('Razón social', 'Veterinaria Central S.A.')
            ->expectsQuestion('RUC, sin el dígito verificador', '80009735')
            ->expectsQuestion('Dígito verificador del RUC', '1')
            ->expectsQuestion('Email del administrador', 'admin@central.com.py')
            ->expectsQuestion('Nombre del administrador', 'María')
            ->expectsQuestion('Apellido del administrador', 'Benítez')
            ->expectsQuestion('Contraseña del administrador', self::PASSWORD)
            ->expectsQuestion('Repetí la contraseña', self::PASSWORD)
            ->doesntExpectOutputToContain(self::PASSWORD)
            ->assertExitCode(0);

        $this->assertTrue(Hash::check(self::PASSWORD, User::where('email', 'admin@central.com.py')->sole()->password));
    }

    /**
     * Una contraseña que no cumple la regla (Password::defaults()) se rechaza con su
     * motivo. En la consola se vuelve a pedir; en los tests Laravel corta en el primer
     * valor inválido (PromptValidationException), así que no se crea nada.
     */
    #[DataProvider('weakPasswords')]
    public function test_a_password_that_breaks_the_rule_is_rejected(string $password, string $message): void
    {
        $this->artisan('clinic:create', $this->optionsWithout())
            ->expectsQuestion('Contraseña del administrador', $password)
            ->expectsOutputToContain($message)
            ->assertExitCode(1);

        $this->assertNothingCreated();
    }

    /**
     * Cada una rompe una sola condición de la regla (R-009).
     */
    public static function weakPasswords(): array
    {
        return [
            'menos de 12' => ['Corta#2026', 'La contraseña debe tener al menos 12 caracteres.'],
            'sin mayúsculas' => ['sinmayusculas#2026', 'La contraseña debe tener mayúsculas y minúsculas.'],
            'sin minúsculas' => ['SINMINUSCULAS#2026', 'La contraseña debe tener mayúsculas y minúsculas.'],
            'sin números' => ['SinNumeros#Aqui', 'La contraseña debe tener al menos un número.'],
            'sin símbolos' => ['SinSimbolos2026', 'La contraseña debe tener al menos un símbolo.'],
        ];
    }

    /**
     * uncompromised(): una contraseña que cumple todo lo demás pero aparece en Have I
     * Been Pwned (simulado) se rechaza. Solo viaja el prefijo del SHA-1.
     */
    public function test_a_password_found_in_known_breaches_is_rejected(): void
    {
        $this->compromisedPasswords = ['Filtrada#2026'];

        $this->artisan('clinic:create', $this->optionsWithout())
            ->expectsQuestion('Contraseña del administrador', 'Filtrada#2026')
            ->expectsOutputToContain('Esa contraseña aparece en filtraciones de datos conocidas: elegí otra.')
            ->assertExitCode(1);

        $this->assertNothingCreated();
        Http::assertSent(fn ($request) => $request->url() === 'https://api.pwnedpasswords.com/range/' . substr(strtoupper(sha1('Filtrada#2026')), 0, 5));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'Filtrada') || str_contains($request->url(), strtoupper(sha1('Filtrada#2026'))));
    }

    public function test_a_confirmation_that_does_not_match_is_rejected(): void
    {
        $this->artisan('clinic:create', $this->optionsWithout())
            ->expectsQuestion('Contraseña del administrador', self::PASSWORD)
            ->expectsQuestion('Repetí la contraseña', self::PASSWORD . 'x')
            ->expectsOutputToContain('Las contraseñas no coinciden.')
            ->assertExitCode(1);

        $this->assertNothingCreated();
    }

    public function test_a_wrong_ruc_check_digit_fails_without_creating_anything(): void
    {
        [$code, $output] = $this->runWithoutInteraction(['--ruc-dv' => '2']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('CLINIC_RUC_INVALID', $output);
        $this->assertNothingCreated();
    }

    public function test_a_ruc_already_in_use_fails_without_creating_another_clinic(): void
    {
        $this->createClinic(['ruc' => '80009735', 'ruc_dv' => '1']);

        [$code, $output] = $this->runWithoutInteraction();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('CLINIC_RUC_TAKEN', $output);
        $this->assertSame(1, Clinic::count());
        $this->assertSame(0, User::count());
    }

    public function test_an_email_already_in_use_fails_without_creating_the_clinic(): void
    {
        $this->userIn($this->createClinic(), [])->forceFill(['email' => 'admin@central.com.py'])->save();

        [$code, $output] = $this->runWithoutInteraction();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('CLINIC_ADMIN_EMAIL_TAKEN', $output);
        $this->assertSame(0, Clinic::where('ruc', '80009735')->count());
    }

    public function test_an_email_with_non_ascii_characters_is_rejected(): void
    {
        [$code, $output] = $this->runWithoutInteraction(['--admin-email' => 'mariā@central.com.py']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('no puede tener acentos ni caracteres especiales (--admin-email)', $output);
        $this->assertNothingCreated();
    }

    public function test_without_interaction_every_option_is_required(): void
    {
        $code = Artisan::call('clinic:create', ['--no-interaction' => true]);
        $output = Artisan::output();

        $this->assertSame(1, $code);
        foreach (array_keys(self::OPTIONS) as $option) {
            $this->assertStringContainsString("({$option}).", $output);
        }
        $this->assertStringNotContainsString('Exception', $output);
        $this->assertNothingCreated();
    }

    /**
     * Si algo falla después de crear la clínica (aquí, el rol administrador no
     * existe), la transacción se revierte: no queda una clínica a medias.
     */
    public function test_a_failure_halfway_leaves_nothing(): void
    {
        Role::where('name', 'administrador')->delete();

        try {
            $this->runWithoutInteraction();
            $this->fail('El alta debía fallar sin el rol administrador.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('administrador', $e->getMessage());
        }

        $this->assertNothingCreated();
    }

    /**
     * La contraseña no llega a la auditoría (el alta del usuario la enmascara) ni al
     * log, tampoco cuando una falla inesperada deja una traza.
     */
    public function test_the_password_never_reaches_the_audit_or_the_log(): void
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
            $logged[] = $event->message . ' ' . ($event->context['exception'] ?? null);
        });

        try {
            $this->artisan('clinic:create', $this->optionsWithout())
                ->expectsQuestion('Contraseña del administrador', self::PASSWORD)
                ->expectsQuestion('Repetí la contraseña', self::PASSWORD)
                ->assertExitCode(0);

            $audit = DB::table('audit_logs')->get()->toJson();
            $this->assertStringContainsString('admin@central.com.py', $audit);
            $this->assertStringNotContainsString(self::PASSWORD, $audit);
            // Ni siquiera el hash: el alta del usuario guarda la contraseña enmascarada.
            $userLog = DB::table('audit_logs')->where(['event' => AuditEvent::CREATED->value, 'auditable_type' => 'user'])->sole();
            $this->assertSame('********', json_decode($userLog->new_values, true)['password']);

            // Falla inesperada con la contraseña ya escrita: el error se reporta al log.
            Role::where('name', 'administrador')->delete();
            try {
                $this->artisan('clinic:create', [...$this->optionsWithout(), '--ruc' => '80012345', '--ruc-dv' => '0', '--admin-email' => 'otra@central.com.py'])
                    ->expectsQuestion('Contraseña del administrador', self::PASSWORD)
                    ->expectsQuestion('Repetí la contraseña', self::PASSWORD)
                    ->run();
                $this->fail('El alta debía fallar sin el rol administrador.');
            } catch (\Throwable $e) {
                app(ExceptionHandler::class)->report($e);

                // getTrace() (lo que usaría un reporte externo) tampoco la tiene.
                $frame = collect($e->getTrace())->first(fn (array $frame) => ($frame['class'] ?? null) === ClinicService::class && $frame['function'] === 'create');
                $this->assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][1] ?? null);
            }

            $this->assertNotSame([], $logged, 'La falla inesperada debía quedar en el log.');
            $this->assertStringNotContainsString(self::PASSWORD, implode("\n", $logged));
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
        }
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runWithoutInteraction(array $overrides = []): array
    {
        $code = Artisan::call('clinic:create', [...self::OPTIONS, ...$overrides, '--no-interaction' => true]);

        return [$code, Artisan::output()];
    }

    /**
     * Las opciones salvo las indicadas: el comando pide esas por consola.
     */
    private function optionsWithout(string ...$asked): array
    {
        return array_diff_key(self::OPTIONS, array_flip($asked));
    }

    private function generatedPassword(string $output): string
    {
        $this->assertSame(1, preg_match('/guardala ahora\.\s*\n\s*(\S+)/u', $output, $match), $output);

        return $match[1];
    }

    private function assertNothingCreated(): void
    {
        $this->assertSame(0, Clinic::count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('branches')->count());
        $this->assertSame(0, DB::table('warehouses')->count());
        $this->assertFalse(CurrentClinic::has());
    }
}
