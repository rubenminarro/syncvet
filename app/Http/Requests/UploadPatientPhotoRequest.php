<?php

namespace App\Http\Requests;

use App\Exceptions\AppException;
use App\Exceptions\PatientException;

/**
 * Foto del paciente (FASE 1, entrega 1.5): JPG, PNG o WEBP de hasta 5 MB, por el
 * contenido, vuelta a codificar con GD (R-027); más grande, 413 PAYLOAD_TOO_LARGE.
 * Las reglas comunes con el logo de la clínica están en PrivateImageRequest.
 */
class UploadPatientPhotoRequest extends PrivateImageRequest
{
    public const MAX_KILOBYTES = 5120;

    public const TOO_LARGE = 'La foto no debe pesar más de 5 MB.';

    public const NOT_AN_IMAGE = 'La foto debe ser una imagen JPG, PNG o WEBP.';

    public const TOO_MANY_PIXELS = 'La foto no debe superar los 25 megapíxeles.';

    protected function field(): string
    {
        return 'photo';
    }

    protected function maxKilobytes(): int
    {
        return self::MAX_KILOBYTES;
    }

    protected function texts(): array
    {
        return [
            'required' => 'La foto es obligatoria.',
            'file' => 'La foto debe ser un archivo.',
            'uploaded' => 'La foto no se pudo subir.',
            'not_image' => self::NOT_AN_IMAGE,
            'too_large' => self::TOO_LARGE,
            'too_many_pixels' => self::TOO_MANY_PIXELS,
        ];
    }

    protected function tooLarge(string $message, array $errors): AppException
    {
        return new PatientException($message, 413, 'PAYLOAD_TOO_LARGE', $errors);
    }

    /**
     * La imagen ya codificada de nuevo, sin metadatos ni contenido pegado.
     */
    public function encodedPhoto(): string
    {
        return $this->encodedImage();
    }
}
