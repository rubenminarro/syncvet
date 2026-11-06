<?php

namespace App\Support;

use GdImage;

/**
 * Vuelve a codificar una imagen JPG, PNG o WEBP con GD, en su mismo formato (FASE 1,
 * entrega 1.5; R-027, que cambia el "tal cual" de R-015). Solo quedan los píxeles:
 * se pierden los metadatos (EXIF, también el GPS) y todo lo que venga pegado
 * después de la imagen (un archivo políglota). No redimensiona.
 *
 * La orientación del EXIF se aplica a los píxeles antes de descartarla: si no, una
 * foto vertical de un teléfono quedaría acostada.
 *
 * Devuelve null si GD no puede decodificarla, si el formato que lee getimagesize no
 * es el que detectó finfo, o si pasa de MAX_PIXELS: una imagen chica en bytes puede
 * ocupar gigas al decodificarse (cada píxel ocupa unos 4 bytes en GD).
 */
class ImageReencoder
{
    /**
     * 25 megapíxeles (6000 × 4000 entra), unos 100 MB en memoria al decodificar.
     */
    public const MAX_PIXELS = 25_000_000;

    public const JPEG_QUALITY = 90;

    public const WEBP_QUALITY = 90;

    private const IMAGE_TYPES = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    /**
     * Ancho × alto según el encabezado, sin decodificar; null si no se puede leer.
     */
    public static function pixels(string $path): ?int
    {
        $info = @getimagesize($path);

        return $info !== false && $info[0] > 0 && $info[1] > 0 ? $info[0] * $info[1] : null;
    }

    public static function reencode(string $path, string $mimeType): ?string
    {
        $info = @getimagesize($path);
        $pixels = self::pixels($path);

        if ($info === false || ($info[2] ?? null) !== (self::IMAGE_TYPES[$mimeType] ?? false)
            || $pixels === null || $pixels > self::MAX_PIXELS) {
            return null;
        }

        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };

        if (! $image instanceof GdImage) {
            return null;
        }

        try {
            if ($mimeType === 'image/jpeg') {
                $image = self::applyExifOrientation($image, $path);
            } else {
                // PNG y WEBP conservan la transparencia.
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }

            ob_start();
            $written = match ($mimeType) {
                'image/jpeg' => imagejpeg($image, null, self::JPEG_QUALITY),
                'image/png' => imagepng($image),
                'image/webp' => imagewebp($image, null, self::WEBP_QUALITY),
            };
            $contents = ob_get_clean();

            return $written && is_string($contents) && $contents !== '' ? $contents : null;
        } finally {
            imagedestroy($image);
        }
    }

    /**
     * Gira o espeja los píxeles según la etiqueta Orientation (1 a 8) del EXIF.
     */
    private static function applyExifOrientation(GdImage $image, string $path): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($path) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // imagerotate gira en sentido antihorario: 270 es un cuarto de vuelta horario.
        $angle = match ($orientation) {
            3, 4 => 180,
            6, 7 => 270,
            5, 8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        if (! $rotated instanceof GdImage) {
            return $image;
        }

        imagedestroy($image);

        return $rotated;
    }
}
