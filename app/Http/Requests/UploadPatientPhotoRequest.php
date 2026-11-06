<?php

namespace App\Http\Requests;

use App\Exceptions\PatientException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Foto del paciente (FASE 1, entrega 1.5): JPG, PNG o WEBP de hasta 5 MB. El tipo
 * sale del contenido del archivo (image y mimetypes miran los bytes con finfo), no
 * de la extensión ni del Content-Type que manda el cliente. Un SVG no es una imagen
 * para `image`. Un archivo de más de 5 MB responde 413 PAYLOAD_TOO_LARGE, como el que
 * supera post_max_size de PHP (bootstrap/app.php).
 */
class UploadPatientPhotoRequest extends FormRequest
{
    public const MAX_KILOBYTES = 5120;

    public const TOO_LARGE = 'La foto no debe pesar más de 5 MB.';

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
            'photo.image' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'photo.mimetypes' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
            'photo.max' => self::TOO_LARGE,
        ];
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
