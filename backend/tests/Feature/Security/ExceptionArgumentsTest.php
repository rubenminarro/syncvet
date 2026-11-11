<?php

namespace Tests\Feature\Security;

use RuntimeException;
use Tests\TestCase;

/**
 * La aplicación oculta los argumentos de las trazas (R-010), sin depender del
 * php.ini: phpunit.xml arranca con zend.exception_ignore_args=Off, como el php.ini de
 * desarrollo, y AppServiceProvider lo pasa a On.
 */
class ExceptionArgumentsTest extends TestCase
{
    public function test_the_application_turns_on_exception_ignore_args(): void
    {
        $this->assertSame('1', ini_get('zend.exception_ignore_args'));
    }

    public function test_a_trace_does_not_carry_the_arguments(): void
    {
        $secret = 'Secreta#' . random_int(100000, 999999);
        $throw = function (string $value): never {
            throw new RuntimeException('falla');
        };

        try {
            $throw($secret);
            $this->fail('Debía lanzar la excepción.');
        } catch (RuntimeException $e) {
            // include y require guardan siempre la ruta del archivo: no son argumentos.
            $frames = array_filter($e->getTrace(), fn (array $frame) => ! in_array($frame['function'], ['include', 'include_once', 'require', 'require_once'], true));

            $this->assertNotSame([], $frames);
            foreach ($frames as $frame) {
                $this->assertArrayNotHasKey('args', $frame, $frame['function']);
            }

            $this->assertStringNotContainsString($secret, (string) $e);
        }
    }
}
