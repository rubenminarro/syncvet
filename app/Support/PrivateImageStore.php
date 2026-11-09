<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Imágenes privadas de la API (FASE 2, entrega 2.5): lo común de la foto del paciente
 * (FASE 1, entrega 1.5; R-027) y del logo de la clínica. Cada una tiene su disco
 * propio (privado, sin `serve`) y su carpeta; el archivo se llama {uuid}.{ext}, con la
 * extensión del tipo detectado en el contenido, y guarda la imagen ya codificada de
 * nuevo por el Form Request (ImageReencoder).
 *
 * - Guardar: el archivo nuevo se escribe antes de la transacción del dueño; si la
 *   transacción falla, se borra. El anterior se borra después del commit, si es un
 *   archivo de la carpeta del dueño (un valor ajeno no se toca).
 * - La ruta guardada en la base solo se usa si es un archivo de la carpeta del dueño;
 *   si no, queda un aviso en el log y el dueño responde 404.
 * - La descarga lleva el tipo guardado, nosniff, una CSP que no deja ejecutar nada y
 *   sin caché. Una ruta sin archivo en el disco es un 404, con un aviso.
 *
 * Los avisos del log llevan el contexto del dueño y la ruta con el nombre de su
 * columna o clave ($pathKey: photo_path, logo_path).
 */
final class PrivateImageStore
{
    /**
     * Tipo detectado → extensión del archivo guardado.
     */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * El nombre de un archivo guardado: {uuid}.{ext}.
     */
    public const FILE_NAME = '/^[0-9a-f-]{36}\.(jpg|png|webp)$/';

    /**
     * @param string $disk el disco propio de la imagen
     * @param string $subject cómo la nombra el log, con mayúscula: "Foto de paciente"
     * @param string $pathKey la columna o clave de la ruta, para el contexto del log
     */
    public function __construct(
        private readonly string $disk,
        private readonly string $subject,
        private readonly string $pathKey,
    ) {
    }

    /**
     * Guarda la imagen en la carpeta y llama a $swap dentro de una transacción, con la
     * ruta nueva: $swap bloquea al dueño, guarda la ruta y devuelve la anterior (o
     * null). La anterior se borra después del commit, si es de la carpeta; si la
     * transacción falla, se borra la nueva y la excepción sigue.
     *
     * @param string $folder la carpeta del dueño, con la barra final
     * @param array<string, mixed> $context el dueño, para el log
     * @param Closure(string): ?string $swap
     */
    public function replace(string $folder, string $contents, string $mimeType, array $context, Closure $swap): string
    {
        $path = $folder . Str::uuid() . '.' . self::EXTENSIONS[$mimeType];

        if (! $this->disk()->put($path, $contents)) {
            // Falla del disco, no de negocio: un 500 genérico, y se reporta.
            throw new RuntimeException("No se pudo guardar el archivo ({$this->subject}): " . json_encode($context));
        }

        try {
            DB::transaction(function () use ($folder, $path, $context, $swap) {
                $previous = $swap($path);

                if ($previous !== null && $this->owns($folder, $previous, $context)) {
                    $this->deleteAfterCommit($previous, $context);
                }
            });
        } catch (Throwable $e) {
            $this->deleteOrWarn($path, $context, 'la subida falló');

            throw $e;
        }

        return $path;
    }

    /**
     * true si la ruta es un archivo de la carpeta ({uuid}.{ext}, sin subcarpetas ni
     * "..");  si no, lo registra.
     *
     * @param array<string, mixed> $context
     */
    public function owns(string $folder, string $path, array $context): bool
    {
        $name = substr($path, strlen($folder));

        if (str_starts_with($path, $folder) && preg_match(self::FILE_NAME, $name) === 1) {
            return true;
        }

        Log::warning("{$this->subject} con una ruta fuera de su carpeta.", $this->context($context, $path));

        return false;
    }

    /**
     * Si la transacción se revierte, el archivo queda: sigue siendo la imagen del dueño.
     *
     * @param array<string, mixed> $context
     */
    public function deleteAfterCommit(string $path, array $context): void
    {
        DB::afterCommit(fn () => $this->deleteOrWarn($path, $context, 'después del commit'));
    }

    /**
     * El archivo, o null (con un aviso) si la ruta no tiene archivo en el disco. La
     * ruta ya está validada con owns().
     *
     * @param array<string, mixed> $context
     */
    public function response(string $path, string $downloadName, array $context): ?StreamedResponse
    {
        if (! $this->disk()->exists($path)) {
            Log::warning("{$this->subject} sin archivo en el disco.", $this->context($context, $path));

            return null;
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $type = array_search($extension, self::EXTENSIONS, true) ?: 'application/octet-stream';

        return $this->disk()->response($path, "{$downloadName}.{$extension}", [
            'Content-Type' => $type,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, no-store',
        ], 'inline');
    }

    /**
     * El disco no lanza excepciones (`throw` => false): un borrado que falla devuelve
     * false y deja el archivo huérfano. Queda en el log; lo levanta files:prune-orphans.
     *
     * @param array<string, mixed> $context
     */
    public function deleteOrWarn(string $path, array $context, string $moment): void
    {
        if (! $this->disk()->delete($path)) {
            Log::warning('No se pudo borrar un archivo de ' . Str::lcfirst($this->subject) . " ({$moment}).", $this->context($context, $path));
        }
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function context(array $context, string $path): array
    {
        return [...$context, $this->pathKey => $path];
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->disk);
    }
}
