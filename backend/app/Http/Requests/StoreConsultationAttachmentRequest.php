<?php

namespace App\Http\Requests;

use App\Exceptions\ConsultationException;
use App\Services\ConsultationAttachmentService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Subir un adjunto a la consulta (FASE 4, entrega 4.5; pregunta 17), en multipart, en el campo file:
 * una imagen JPEG, PNG o WebP o un PDF, de hasta 10 MB.
 *
 * El tipo sale del contenido del archivo (mimetypes mira los bytes con finfo, en
 * UploadedFile::getMimeType()), nunca de la extensión del nombre ni del Content-Type que manda el
 * cliente: un PDF con nombre .png es un PDF, y un HTML con nombre .pdf se rechaza (422). Un archivo
 * más grande que el límite responde 413 PAYLOAD_TOO_LARGE, también el que corta PHP antes
 * (upload_max_filesize; el que supera post_max_size, en bootstrap/app.php).
 *
 * A diferencia de la foto, el archivo no se vuelve a codificar: es un estudio clínico y se guarda
 * tal cual. La descarga lo sirve con el tipo detectado, nosniff y una CSP que no deja ejecutar nada.
 */
class StoreConsultationAttachmentRequest extends FormRequest
{
    public const MAX_KILOBYTES = 10240;

    public const TOO_LARGE = 'El archivo no debe pesar más de 10 MB.';

    public const NOT_ALLOWED = 'El archivo debe ser una imagen JPEG, PNG o WebP, o un PDF.';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required', 'file',
                'mimetypes:' . implode(',', array_keys(ConsultationAttachmentService::EXTENSIONS)),
                'max:' . self::MAX_KILOBYTES,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Elegí el archivo que querés adjuntar.',
            'file.file' => 'El adjunto debe ser un archivo.',
            'file.uploaded' => 'El archivo no se pudo subir.',
            'file.mimetypes' => self::NOT_ALLOWED,
            'file.max' => self::TOO_LARGE,
        ];
    }

    /**
     * El tipo detectado en el contenido (finfo), el único que se usa para guardar y servir el archivo.
     */
    public function detectedMimeType(): string
    {
        return $this->file('file')->getMimeType();
    }

    /**
     * Un archivo más grande que el límite responde 413, no 422: por la regla max o porque PHP lo cortó.
     */
    protected function failedValidation(Validator $validator): void
    {
        $file = $this->file('file');
        $tooLargeForPhp = $file instanceof UploadedFile
            && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

        if ($tooLargeForPhp || isset($validator->failed()['file']['Max'])) {
            throw new ConsultationException(self::TOO_LARGE, 413, 'PAYLOAD_TOO_LARGE', ['file' => [self::TOO_LARGE]]);
        }

        parent::failedValidation($validator);
    }
}
