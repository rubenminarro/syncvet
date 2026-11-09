<?php

namespace App\Http\Requests;

use App\Exceptions\AppException;
use App\Support\ImageReencoder;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use LogicException;

/**
 * Subida de una imagen privada (la foto del paciente, FASE 1, entrega 1.5; el logo de
 * la clínica, FASE 2, entrega 2.5): JPG, PNG o WEBP hasta un tamaño. El tipo sale del
 * contenido del archivo (image y mimetypes miran los bytes con finfo), no de la
 * extensión ni del Content-Type que manda el cliente. Un SVG no es una imagen para
 * `image`. Un archivo más grande que el límite responde 413 PAYLOAD_TOO_LARGE, como el
 * que supera post_max_size de PHP (bootstrap/app.php).
 *
 * finfo solo mira los primeros bytes: si las reglas pasan, la imagen se vuelve a
 * codificar con GD (ImageReencoder, R-027). Si GD no la puede decodificar, o pasa de
 * 25 megapíxeles, 422 en el campo. El Service guarda la imagen ya codificada.
 */
abstract class PrivateImageRequest extends FormRequest
{
    private ?string $encoded = null;

    /**
     * El nombre del campo del formulario (photo, logo).
     */
    abstract protected function field(): string;

    abstract protected function maxKilobytes(): int;

    /**
     * Los mensajes del campo.
     *
     * @return array{required: string, file: string, uploaded: string, not_image: string, too_large: string, too_many_pixels: string}
     */
    abstract protected function texts(): array;

    /**
     * La excepción del 413 (la del módulo), con el mensaje en el campo.
     *
     * @param array<string, list<string>> $errors
     */
    abstract protected function tooLarge(string $message, array $errors): AppException;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            $this->field() => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:' . $this->maxKilobytes()],
        ];
    }

    public function messages(): array
    {
        $field = $this->field();
        $texts = $this->texts();

        return [
            "{$field}.required" => $texts['required'],
            "{$field}.file" => $texts['file'],
            "{$field}.uploaded" => $texts['uploaded'],
            "{$field}.image" => $texts['not_image'],
            "{$field}.mimetypes" => $texts['not_image'],
            "{$field}.max" => $texts['too_large'],
        ];
    }

    /**
     * Con las reglas cumplidas, la imagen se decodifica y se vuelve a codificar.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $field = $this->field();

                if ($validator->errors()->has($field)) {
                    return;
                }

                $file = $this->file($field);
                $pixels = ImageReencoder::pixels($file->getRealPath());

                if ($pixels !== null && $pixels > ImageReencoder::MAX_PIXELS) {
                    $validator->errors()->add($field, $this->texts()['too_many_pixels']);

                    return;
                }

                $this->encoded = ImageReencoder::reencode($file->getRealPath(), $file->getMimeType());

                if ($this->encoded === null) {
                    $validator->errors()->add($field, $this->texts()['not_image']);
                }
            },
        ];
    }

    /**
     * La imagen ya codificada de nuevo, sin metadatos ni contenido pegado.
     */
    public function encodedImage(): string
    {
        return $this->encoded ?? throw new LogicException('La imagen no se validó.');
    }

    /**
     * El tipo detectado en el contenido.
     */
    public function detectedMimeType(): string
    {
        return $this->file($this->field())->getMimeType();
    }

    /**
     * Un archivo más grande que el límite responde 413, no 422: por la regla max o
     * porque PHP lo cortó (upload_max_filesize; el formulario no lo trae completo).
     */
    protected function failedValidation(Validator $validator): void
    {
        $field = $this->field();
        $file = $this->file($field);
        $tooLargeForPhp = $file instanceof UploadedFile
            && in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);

        if ($tooLargeForPhp || isset($validator->failed()[$field]['Max'])) {
            $message = $this->texts()['too_large'];

            throw $this->tooLarge($message, [$field => [$message]]);
        }

        parent::failedValidation($validator);
    }
}
