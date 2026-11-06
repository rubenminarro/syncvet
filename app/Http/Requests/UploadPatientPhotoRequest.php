<?php

namespace App\Http\Requests;

use App\Exceptions\PatientException;
use App\Support\ImageReencoder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Foto del paciente (FASE 1, entrega 1.5): JPG, PNG o WEBP de hasta 5 MB. El tipo
 * sale del contenido del archivo (image y mimetypes miran los bytes con finfo), no
 * de la extensión ni del Content-Type que manda el cliente. Un SVG no es una imagen
 * para `image`. Un archivo de más de 5 MB responde 413 PAYLOAD_TOO_LARGE, como el que
 * supera post_max_size de PHP (bootstrap/app.php).
 *
 * finfo solo mira los primeros bytes: si las reglas pasan, la imagen se vuelve a
 * codificar con GD (ImageReencoder, R-027). Si GD no la puede decodificar, o pasa de
 * 25 megapíxeles, 422 en `photo`. El Service guarda la imagen ya codificada.
 */
class UploadPatientPhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    public const TOO_LARGE = 'La foto no debe pesar más de 5 MB.';

    public const NOT_AN_IMAGE = 'La foto debe ser una imagen JPG, PNG o WEBP.';

    public const TOO_MANY_PIXELS = 'La foto no debe superar los 25 megapíxeles.';

    private ?string $encodedPhoto = null;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:' . self::MAX_KILOBYTES],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'La foto es obligatoria.',
            'photo.file' => 'La foto debe ser un archivo.',
            'photo.uploaded' => 'La foto no se pudo subir.',
            'photo.image' => self::NOT_AN_IMAGE,
            'photo.mimetypes' => self::NOT_AN_IMAGE,
            'photo.max' => self::TOO_LARGE,
        ];
    }

    /**
     * Con las reglas cumplidas, la imagen se decodifica y se vuelve a codificar.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->has('photo')) {
                    return;
                }

                $file = $this->file('photo');
                $pixels = ImageReencoder::pixels($file->getRealPath());

                if ($pixels !== null && $pixels > ImageReencoder::MAX_PIXELS) {
                    $validator->errors()->add('photo', self::TOO_MANY_PIXELS);

                    return;
                }

                $this->encodedPhoto = ImageReencoder::reencode($file->getRealPath(), $file->getMimeType());

                if ($this->encodedPhoto === null) {
                    $validator->errors()->add('photo', self::NOT_AN_IMAGE);
                }
            },
        ];
    }

    /**
     * La imagen ya codificada de nuevo, sin metadatos ni contenido pegado.
     */
    public function encodedPhoto(): string
    {
        return $this->encodedPhoto ?? throw new LogicException('La foto no se validó.');
    }

    /**
     * Un archivo más grande que el límite responde 413, no 422: por la regla max o
     * porque PHP lo cortó (upload_max_filesize; el formulario no lo trae completo).
     */
    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('photo');
        $tooLargeForPhp = $file instanceof UploadedFile
            && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

        if ($tooLargeForPhp || isset($validator->failed()['photo']['Max'])) {
            throw new PatientException(self::TOO_LARGE, 413, 'PAYLOAD_TOO_LARGE', ['photo' => [self::TOO_LARGE]]);
        }

        parent::failedValidation($validator);
    }
}
