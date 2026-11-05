<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Exceptions\AuthException;
use App\Exceptions\ClinicException;
use App\Models\User;
use App\Support\CurrentClinic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

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
            $seconds = RateLimiter::availableIn($key);

            throw new AuthException(
                "Demasiados intentos. Probá de nuevo en {$seconds} segundos.",
                429,
                'AUTH_TOO_MANY_ATTEMPTS',
                null,
                ['Retry-After' => (string) $seconds]
            );
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
     * Usuario con lo que muestra MeResource ya cargado. La sucursal y la ficha de
     * veterinario son modelos de la clínica: se cargan con su clínica en contexto.
     */
    public function profile(User $user): User
    {
        return CurrentClinic::run($user->clinic, fn () => $user->load(['clinic', 'currentBranch', 'veterinarian', 'roles']));
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
