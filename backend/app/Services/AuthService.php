<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Exceptions\AuthException;
use App\Exceptions\ClinicException;
use App\Http\Requests\UpdateCurrentBranchRequest;
use App\Models\Branch;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Login y logout con tokens de Sanctum (FASE 0, entrega 0.3, registro R-004).
 *
 * - El mismo error para un email desconocido, un usuario eliminado y una
 *   contraseña incorrecta, y el mismo trabajo: siempre se verifica un hash.
 * - El email se busca en minúsculas (User lo guarda así).
 * - Límite: 5 intentos FALLIDOS por minuto por usuario e IP (por email en
 *   minúsculas si el email no existe). Un login correcto limpia el contador.
 * - Cada login crea un token que vence a las 12 horas (sanctum.expiration); un
 *   usuario puede tener varios vigentes. El logout revoca solo el actual.
 * - Clínica suspendida: 403 CLINIC_NOT_AVAILABLE, no se crea el token y queda
 *   LOGIN_FAILED con el motivo; no cuenta como intento fallido.
 * - Usuario inactivo (FASE 2, entrega 2.2b): con la contraseña correcta, 403
 *   USER_INACTIVE, sin token, LOGIN_FAILED con el motivo y sin contar como intento
 *   fallido; se revisa después de la clínica suspendida. Con una incorrecta, el
 *   INVALID_CREDENTIALS de siempre: no revela que la cuenta existe.
 * - Perfil (2.2b): la sucursal actual y la contraseña propia, con su propio límite
 *   de intentos (5 por minuto por usuario).
 * - La contraseña lleva #[\SensitiveParameter]: no aparece en las trazas.
 */
class AuthService
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    /**
     * Motivo de LOGIN_FAILED cuando las credenciales son correctas pero la clínica
     * está suspendida.
     */
    public const REASON_CLINIC_SUSPENDED = 'clínica suspendida';

    /**
     * Motivo de LOGIN_FAILED cuando las credenciales son correctas pero el usuario
     * está inactivo (2.2b).
     */
    public const REASON_USER_INACTIVE = 'usuario inactivo';

    public const USER_INACTIVE_MESSAGE = 'Tu usuario está inactivo: consultá con el administrador de la clínica.';

    public const WRONG_CURRENT_PASSWORD = 'La contraseña actual no es correcta.';

    /**
     * Hash de relleno: se verifica cuando no hay usuario, para que el tiempo de
     * respuesta no revele qué emails tienen cuenta. Es un bcrypt de un texto
     * aleatorio descartado, con el costo de producción (BCRYPT_ROUNDS=12 en
     * .env.example). Precalculado: calcularlo en el pedido costaría un bcrypt más
     * la primera vez en cada proceso. Si cambia el costo de producción, regenerarlo
     * (lo exige LoginCredentialsTest).
     */
    public const DUMMY_HASH = '$2y$12$bmb7tTevNIQhrrtxW.fPJuYN71slLaVAEFUpdtc.ZsQA9CarOWF8u';

    /**
     * Hash de relleno calculado con un costo distinto del de DUMMY_HASH, por costo.
     *
     * @var array<int, string>
     */
    private static array $dummyHashes = [];

    public function __construct(
        protected AuditLogger $audit
    ) {
    }

    /**
     * @return array{token: string, expires_at: Carbon, user: User}
     */
    public function login(string $email, #[\SensitiveParameter] string $password, string $ip): array
    {
        $email = User::normalizeEmail($email);

        // SoftDeletes: un usuario eliminado no aparece y se comporta como un email desconocido.
        $user = User::where('email', $email)->first();

        $key = $this->throttleKey($user, $email, $ip);

        // El intento se cuenta ANTES de verificar la contraseña y en una sola operación:
        // así una ráfaga de pedidos simultáneos no pasa toda junta la verificación del
        // límite. Si el login sale bien, el contador se limpia.
        $attempts = RateLimiter::hit($key, self::DECAY_SECONDS);

        if ($attempts > self::MAX_ATTEMPTS) {
            throw self::tooManyAttempts(RateLimiter::availableIn($key));
        }

        // Siempre se verifica una contraseña, aunque el usuario no exista.
        $passwordMatches = Hash::check($password, $user?->password ?? self::dummyHash());

        if ($user === null || ! $passwordMatches) {
            $this->audit->record(AuditEvent::LOGIN_FAILED, null, null, ['email' => $email], $user);

            throw new AuthException('El email o la contraseña no son correctos.', 422, 'INVALID_CREDENTIALS');
        }

        // Credenciales correctas: queda auditado, pero no cuenta como intento fallido.
        if (! $user->clinic->isActive()) {
            RateLimiter::decrement($key, self::DECAY_SECONDS);
            $this->audit->record(AuditEvent::LOGIN_FAILED, null, null, ['email' => $email, 'reason' => self::REASON_CLINIC_SUSPENDED], $user);

            throw new ClinicException('La clínica no está disponible.', 403, 'CLINIC_NOT_AVAILABLE');
        }

        // Usuario inactivo (2.2b): como la clínica suspendida, no cuenta como intento.
        if (! $user->is_active) {
            RateLimiter::decrement($key, self::DECAY_SECONDS);
            $this->audit->record(AuditEvent::LOGIN_FAILED, null, null, ['email' => $email, 'reason' => self::REASON_USER_INACTIVE], $user);

            throw self::userInactive();
        }

        RateLimiter::clear($key);

        $expiresAt = now()->addMinutes((int) config('sanctum.expiration'));
        $token = $user->createToken('api', ['*'], $expiresAt);

        return CurrentClinic::run($user->clinic, function () use ($user, $token, $expiresAt) {
            $this->audit->record(AuditEvent::LOGIN, null, null, null, $user);

            return [
                'token' => $token->plainTextToken,
                'expires_at' => $expiresAt,
                'user' => $this->profile($user),
            ];
        });
    }

    /**
     * Revoca solo el token con el que se hizo el pedido. Funciona también con la
     * clínica suspendida (la ruta no lleva SetCurrentClinic): el usuario siempre
     * puede cerrar su sesión.
     */
    public function logout(User $user): void
    {
        $user->currentAccessToken()->delete();

        $this->audit->record(AuditEvent::LOGOUT, null, null, null, $user);
    }

    /**
     * Sucursal actual del usuario autenticado (2.2b): una sucursal activa de la clínica
     * asignada al usuario, revisada otra vez con la clínica bloqueada (una inactivada
     * después de la validación → 422). Orden de bloqueo: clínica → sucursal → usuario,
     * como la inactivación de una sucursal.
     */
    public function setCurrentBranch(User $user, int $branchId): User
    {
        DB::transaction(function () use ($user, $branchId) {
            CatalogLock::forCurrentClinic();

            $available = Branch::query()
                ->selectableBy($user)
                ->whereKey($branchId)
                ->lockForUpdate()
                ->first() !== null;

            if (! $available) {
                throw ValidationException::withMessages(['branch_id' => UpdateCurrentBranchRequest::BRANCH_NOT_AVAILABLE]);
            }

            $locked = User::query()->inClinic()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $locked->current_branch_id = $branchId;
            $locked->save();
        });

        return $this->profile($user->refresh());
    }

    /**
     * Cambio de la contraseña propia (2.2b, pregunta 20). Límite: 5 intentos por minuto
     * por usuario, contados antes de verificar (como el login: una ráfaga no pasa junta);
     * el sexto responde 429 aunque la actual sea correcta, y un cambio correcto limpia el
     * contador. La actual incorrecta → 422 en current_password. Conserva el token del
     * pedido y revoca los demás del usuario.
     */
    public function changeOwnPassword(User $user, #[\SensitiveParameter] string $current, #[\SensitiveParameter] string $new): void
    {
        $key = "own-password:user:{$user->getKey()}";
        $attempts = RateLimiter::hit($key, self::DECAY_SECONDS);

        if ($attempts > self::MAX_ATTEMPTS) {
            throw self::tooManyAttempts(RateLimiter::availableIn($key));
        }

        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => self::WRONG_CURRENT_PASSWORD]);
        }

        RateLimiter::clear($key);

        $token = $user->currentAccessToken();

        DB::transaction(function () use ($user, $new, $token) {
            $user->password = $new;
            $user->save();

            $user->tokens()
                ->when($token instanceof PersonalAccessToken, fn ($query) => $query->whereKeyNot($token->getKey()))
                ->delete();
        });
    }

    /**
     * 403 de un usuario inactivo: en el login y, con un token vigente, en SetCurrentClinic.
     */
    public static function userInactive(): AuthException
    {
        return new AuthException(self::USER_INACTIVE_MESSAGE, 403, 'USER_INACTIVE');
    }

    private static function tooManyAttempts(int $seconds): AuthException
    {
        return new AuthException(
            "Demasiados intentos. Probá de nuevo en {$seconds} segundos.",
            429,
            'AUTH_TOO_MANY_ATTEMPTS',
            null,
            ['Retry-After' => (string) $seconds]
        );
    }

    /**
     * Usuario con lo que muestra MeResource ya cargado. La sucursal y la ficha de
     * veterinario son modelos de la clínica: se cargan con su clínica en contexto.
     */
    public function profile(User $user): User
    {
        // branches (entrega front-00, B1): las que acepta setCurrentBranch, con la misma regla
        // (Branch::selectableBy), en una consulta; dentro de CurrentClinic::run, el scope deja
        // afuera las de otra clínica.
        return CurrentClinic::run($user->clinic, function () use ($user) {
            $user->load(['clinic', 'currentBranch', 'veterinarian', 'roles']);

            return $user->setRelation('branches', Branch::query()->selectableBy($user)->orderBy('code')->get());
        });
    }

    /**
     * Por usuario cuando el email existe: así ninguna variante del email que la base
     * considere igual (mayúsculas, la collation de MySQL) abre un contador nuevo. Por
     * email normalizado cuando no existe. Los prefijos distintos evitan que un email
     * choque con un id.
     */
    private function throttleKey(?User $user, string $email, string $ip): string
    {
        return $user !== null
            ? "login:user:{$user->getKey()}|{$ip}"
            : "login:email:{$email}|{$ip}";
    }

    /**
     * El hash de relleno con el costo configurado (BCRYPT_ROUNDS), el mismo con el que
     * se guardan las contraseñas: así tarda lo mismo que verificar la de un usuario.
     * Con el costo de producción es DUMMY_HASH, sin calcular nada. Con otro (los tests
     * usan 4), se calcula una vez por proceso con un texto aleatorio descartado.
     */
    public static function dummyHash(): string
    {
        $cost = (int) config('hashing.bcrypt.rounds');

        if ($cost === password_get_info(self::DUMMY_HASH)['options']['cost']) {
            return self::DUMMY_HASH;
        }

        return self::$dummyHashes[$cost] ??= Hash::make(bin2hex(random_bytes(32)));
    }
}
