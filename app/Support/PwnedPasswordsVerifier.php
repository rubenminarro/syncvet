<?php

namespace App\Support;

use Exception;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;

/**
 * Verificador de uncompromised() (regla de contraseñas, R-009 y R-010): reemplaza al
 * NotPwnedVerifier de Laravel, que espera 30 segundos y, si Have I Been Pwned no
 * responde, reporta la excepción con su traza (que puede llevar la contraseña como
 * argumento).
 *
 * - Consulta por k-anonimato: solo viajan los 5 primeros caracteres del SHA-1.
 * - Espera como mucho TIMEOUT_SECONDS.
 * - Si el servicio no responde o responde con error, acepta la contraseña (como
 *   Laravel) y registra solo un aviso: sin traza, sin la contraseña y sin el hash.
 *   El aviso lleva el estado HTTP o el código de error de cURL (R-011), nunca el
 *   mensaje de la excepción, que termina con la URL y el prefijo del hash.
 * - lastCheckWasSkipped() dice si la última verificación no se pudo hacer, para
 *   avisarlo en la consola (UserPassword::leakCheckSkipped). Es un singleton: el
 *   ValidationServiceProvider lo registra así.
 */
final class PwnedPasswordsVerifier implements UncompromisedVerifier
{
    public const TIMEOUT_SECONDS = 3;

    public const URL = 'https://api.pwnedpasswords.com/range/';

    public const UNAVAILABLE_MESSAGE = 'Have I Been Pwned no respondió: la contraseña se aceptó sin verificar si aparece en filtraciones.';

    private bool $skipped = false;

    public function __construct(private readonly HttpFactory $http)
    {
    }

    /**
     * @param array{value: mixed, threshold: int} $data
     */
    public function verify(#[\SensitiveParameter] $data): bool
    {
        $this->skipped = false;
        $value = (string) $data['value'];

        // Como Laravel: una contraseña vacía no se da por segura.
        if ($value === '') {
            return false;
        }

        $hash = strtoupper(sha1($value));
        $prefix = substr($hash, 0, 5);
        $suffix = substr($hash, 5);

        try {
            $response = $this->http
                ->withHeaders(['Add-Padding' => 'true'])
                ->timeout(self::TIMEOUT_SECONDS)
                ->get(self::URL . $prefix);
        } catch (Exception $e) {
            return $this->unavailable(['reason' => $e::class, ...self::curlError($e)]);
        }

        if (! $response->successful()) {
            return $this->unavailable(['status' => $response->status()]);
        }

        foreach (preg_split('/\R/', trim($response->body())) as $line) {
            [$lineSuffix, $count] = array_pad(explode(':', trim($line), 2), 2, '0');

            if (strtoupper($lineSuffix) === $suffix && (int) $count > (int) $data['threshold']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Si la última verificación no se pudo hacer (el servicio no respondió) y la
     * contraseña se aceptó sin ella.
     */
    public function lastCheckWasSkipped(): bool
    {
        return $this->skipped;
    }

    /**
     * @param array<string, int|string> $context Solo datos que no identifican la contraseña.
     */
    private function unavailable(array $context): bool
    {
        $this->skipped = true;
        Log::warning(self::UNAVAILABLE_MESSAGE, $context);

        return true;
    }

    /**
     * Solo el número del error de cURL ("cURL error 77: … for https://…/range/ABCDE"):
     * el resto del mensaje lleva la URL con el prefijo del hash.
     *
     * @return array{curl_error?: int}
     */
    private static function curlError(Exception $e): array
    {
        return preg_match('/\bcURL error (\d+)/', $e->getMessage(), $match) === 1
            ? ['curl_error' => (int) $match[1]]
            : [];
    }
}
