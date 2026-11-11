<?php

namespace App\Http\Requests;

use App\Exceptions\AppException;
use App\Exceptions\ClinicException;

/**
 * Logo de la clínica (FASE 2, entrega 2.5; P12 y pregunta 25): JPG, PNG o WEBP de hasta
 * 2 MB, por el contenido, vuelto a codificar con GD (sin EXIF) y hasta 25 megapíxeles;
 * más grande, 413 PAYLOAD_TOO_LARGE. Las reglas de la foto del paciente
 * (PrivateImageRequest).
 */
class UploadClinicLogoRequest extends PrivateImageRequest
{
    public const MAX_KILOBYTES = 2048;

    public const TOO_LARGE = 'El logo no debe pesar más de 2 MB.';

    public const NOT_AN_IMAGE = 'El logo debe ser una imagen JPG, PNG o WEBP.';

    public const TOO_MANY_PIXELS = 'El logo no debe superar los 25 megapíxeles.';

    protected function field(): string
    {
        return 'logo';
    }

    protected function maxKilobytes(): int
    {
        return self::MAX_KILOBYTES;
    }

    protected function texts(): array
    {
        return [
            'required' => 'El logo es obligatorio.',
            'file' => 'El logo debe ser un archivo.',
            'uploaded' => 'El logo no se pudo subir.',
            'not_image' => self::NOT_AN_IMAGE,
            'too_large' => self::TOO_LARGE,
            'too_many_pixels' => self::TOO_MANY_PIXELS,
        ];
    }

    protected function tooLarge(string $message, array $errors): AppException
    {
        return new ClinicException($message, 413, 'PAYLOAD_TOO_LARGE', $errors);
    }
}
