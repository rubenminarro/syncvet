<?php

namespace Tests\Unit;

use App\Support\UserPassword;
use PHPUnit\Framework\TestCase;

/**
 * Contraseña generada de clinic:create (R-009): siempre trae una mayúscula, una
 * minúscula, un número y un símbolo, y solo caracteres no ambiguos.
 *
 * Con 300 repeticiones, un generador que no garantice cada juego (que dependa del
 * azar) pasaría con una probabilidad menor a 1 en 10^13: sin un número, por ejemplo,
 * falla en el 8 % de las contraseñas.
 */
class UserPasswordGenerateTest extends TestCase
{
    private const REPETITIONS = 300;

    public function test_every_generated_password_has_one_character_of_each_set(): void
    {
        for ($i = 0; $i < self::REPETITIONS; $i++) {
            $password = UserPassword::generate();

            $this->assertSame(UserPassword::GENERATED_LENGTH, strlen($password));
            $this->assertMatchesRegularExpression('/[A-Z]/', $password, "Sin mayúscula: {$password}");
            $this->assertMatchesRegularExpression('/[a-z]/', $password, "Sin minúscula: {$password}");
            $this->assertMatchesRegularExpression('/[0-9]/', $password, "Sin número: {$password}");
            $this->assertMatchesRegularExpression('/[!#$%*+\-=?@_]/', $password, "Sin símbolo: {$password}");
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $password, "Con un carácter ambiguo: {$password}");
            $this->assertMatchesRegularExpression('/^[A-Za-z2-9!#$%*+\-=?@_]+$/', $password);
        }
    }

    public function test_two_generated_passwords_differ(): void
    {
        $this->assertNotSame(UserPassword::generate(), UserPassword::generate());
    }
}
