<?php

namespace App\Support;

/**
 * Orígenes de CORS_ALLOWED_ORIGINS (entrega front-00, B2; revisión, H1 y H6).
 *
 * Se aceptan solo orígenes exactos, esquema://host[:puerto] con http o https, normalizados
 * como los manda el navegador: en minúsculas y sin la barra final. Todo lo demás se descarta
 * (config/cors.php lo deja en discarded_origins y AppServiceProvider lo avisa en el log), en
 * particular cualquier "*": php-cors toma "*" como "cualquier origen" y un origen con "*"
 * adentro ("https://*", "*.dominio", "https://a*.b") como un patrón.
 */
final class CorsOrigins
{
    private const ORIGIN = '#^https?://(?:\[[0-9a-f:.]+\]|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::\d{1,5})?$#';

    /**
     * @return array{allowed: list<string>, discarded: list<string>}
     */
    public static function parse(?string $value): array
    {
        $allowed = [];
        $discarded = [];

        foreach (explode(',', (string) $value) as $raw) {
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }

            $origin = strtolower(rtrim($raw, '/'));

            if (preg_match(self::ORIGIN, $origin) === 1) {
                $allowed[] = $origin;
            } else {
                $discarded[] = $raw;
            }
        }

        return ['allowed' => array_values(array_unique($allowed)), 'discarded' => $discarded];
    }
}
