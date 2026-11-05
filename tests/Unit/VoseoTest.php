<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;

/**
 * Mensajes al usuario con voseo (CLAUDE.md §3, R-004; FASE 1, entrega 1.1).
 * Revisa los textos de la aplicación (las cadenas con espacios, que son las que
 * llegan al usuario; no los comentarios ni los identificadores) y busca formas de
 * tú o de usted de una lista cerrada. Una forma nueva que aparezca se suma a la
 * lista.
 */
class VoseoTest extends TestCase
{
    /**
     * Formas de tú y de usted. Se buscan como palabra entera, en minúscula o con
     * la primera letra en mayúscula ("IN_USE" no es "use").
     */
    private const FORBIDDEN = [
        // Tú
        'tienes', 'puedes', 'debes', 'quieres', 'eres',
        'ingresa', 'registra', 'revisa', 'intenta', 'selecciona', 'elige', 'contacta', 'vuelve', 'verifica', 'escribe',
        // Usted
        'ingrese', 'registre', 'revise', 'intente', 'seleccione', 'elija', 'contacte', 'vuelva', 'verifique', 'escriba', 'use',
    ];

    public function test_the_forms_are_detected(): void
    {
        $this->assertSame(['tienes'], $this->forbiddenForms('No tienes permisos.'));
        $this->assertSame(['registre'], $this->forbiddenForms('Si falleció, registre la fecha.'));
        $this->assertSame(['use'], $this->forbiddenForms('Una empresa no lleva nombre: use la razón social.'));
        $this->assertSame(['ingresa'], $this->forbiddenForms('Ingresa tu email.'));
        $this->assertSame([], $this->forbiddenForms('No tenés permisos. Ingresá tu email. Registrá la fecha. Usá la razón social.'));
        $this->assertSame([], $this->forbiddenForms('CLIENT_IN_USE'));
    }

    public function test_messages_use_voseo(): void
    {
        $found = [];

        foreach ($this->files() as $path) {
            foreach ($this->texts($path) as [$line, $text]) {
                foreach ($this->forbiddenForms($text) as $form) {
                    $found[] = "{$path}:{$line} \"{$form}\" en {$text}";
                }
            }
        }

        $this->assertSame([], $found, 'Mensajes con tú o usted.');
    }

    /**
     * @return list<string>
     */
    private function forbiddenForms(string $text): array
    {
        $forms = [];

        foreach (self::FORBIDDEN as $form) {
            $pattern = '/(?<![\p{L}_])(?:' . $form . '|' . ucfirst($form) . ')(?![\p{L}_])/u';

            if (preg_match($pattern, $text)) {
                $forms[] = $form;
            }
        }

        return $forms;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $root = dirname(__DIR__, 2);
        $files = [$root . '/bootstrap/app.php'];

        foreach ((new Finder())->files()->in([$root . '/app', $root . '/lang/es'])->name('*.php') as $file) {
            $files[] = $file->getPathname();
        }

        $this->assertGreaterThan(1, count($files));

        return $files;
    }

    /**
     * Las cadenas con al menos un espacio, con su línea.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function texts(string $path): array
    {
        $texts = [];

        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                && str_contains($token[1], ' ')) {
                $texts[] = [$token[2], $token[1]];
            }
        }

        return $texts;
    }
}
