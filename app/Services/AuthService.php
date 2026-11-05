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
use Illuminate\Support\Str;

/**
 * Login y logout con tokens de Sanctum (FASE 0, entrega 0.3, registro R-004).
 *
 * - El mismo error para un email desconocido, un usuario eliminado y una
 *   contraseña incorrecta, y el mismo trabajo: siempre se verifica un hash.
 * - El email se busca en minúsculas (User lo guarda así).
 * - Límite: 5 intentos FALLIDOS por minuto por email (en minúsculas) e IP. Un
 *   login correcto limpia el contador.
 * - Cada login crea un token que vence a las 12 horas (sanctum.expiration); un
 *   usuario puede tener varios vigentes. El logout revoca solo el actual.
 * - Clínica suspendida: 403 CLINIC_NOT_AVAILABLE, no se crea el token y queda
 *   LOGIN_FAILED con el motivo; no cuenta como intento fallido.
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

    private static ?string $dummyHash = null;

    public function __construct(
        protected AuditLogger $audit
    ) {
    }

    /**
     * @return array{token: string, expires_at: Carbon, user: User}
     */
    public function login(string $email, string $password, string $ip): array
    {
        $email = User::normalizeEmail($email);
        $key = $this->throttleKey($email, $ip);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            throw new AuthException(
                "Demasiados intentos. Probá de nuevo en {$seconds} segundos.",
                429,
                'AUTH_TOO_MANY_ATTEMPTS',
                null,
                ['Retry-After' => (string) $seconds]
            );
        }

        // SoftDeletes: un usuario eliminado no aparece y se comporta como un email desconocido.
        $user = User::where('email', $email)->first();

        // Siempre se verifica una contraseña, aunque el usuario no exista: así el tiempo
        // de respuesta no revela qué emails tienen cuenta.
        $passwordMatches = Hash::check($password, $user?->password ?? $this->dummyHash());

        if ($user === null || ! $passwordMatches) {
            RateLimiter::hit($key, self::DECAY_SECONDS);
            $this->audit->record(AuditEvent::LOGIN_FAILED, null, null, ['email' => $email], $user);

            throw new AuthException('El email o la contraseña no son correctos.', 422, 'INVALID_CREDENTIALS');
        }

        // Credenciales correctas: queda auditado, pero no cuenta como intento fallido.
        if (! $user->clinic->isActive()) {
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
     * Revoca solo el token con el que se hizo el pedido.
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
     * Hash de relleno para verificar cuando no hay usuario, con el costo configurado
     * (el mismo que el de las contraseñas reales). Se calcula una vez por proceso.
     */
    private function dummyHash(): string
    {
        return self::$dummyHash ??= Hash::make(Str::random(40));
    }

    private function throttleKey(string $email, string $ip): string
    {
        return 'login:' . mb_strtolower($email) . '|' . $ip;
    }
}
