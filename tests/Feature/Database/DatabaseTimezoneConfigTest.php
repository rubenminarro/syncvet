<?php

namespace Tests\Feature\Database;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Deuda 16 (FASE 3, entrega 3.0; R-049): la zona de la sesión de MySQL sale de DB_TIMEZONE
 * y, sin valor o vacía, es -03:00. Con env('DB_TIMEZONE', '-03:00') una variable vacía daba ""
 * y Laravel mandaba SET time_zone='', que MySQL rechaza: ninguna conexión abría.
 * Se lee el archivo de configuración, sin MySQL, así que corre también en SQLite.
 */
class DatabaseTimezoneConfigTest extends TestCase
{
    private const VARIABLE = 'DB_TIMEZONE';

    /**
     * @var array{server: mixed, env: mixed, putenv: string|false}
     */
    private array $original;

    protected function setUp(): void
    {
        parent::setUp();

        $this->original = [
            'server' => $_SERVER[self::VARIABLE] ?? null,
            'env' => $_ENV[self::VARIABLE] ?? null,
            'putenv' => getenv(self::VARIABLE),
        ];
    }

    protected function tearDown(): void
    {
        $this->setVariable($this->original['server']);

        if ($this->original['env'] === null) {
            unset($_ENV[self::VARIABLE]);
        } else {
            $_ENV[self::VARIABLE] = $this->original['env'];
        }

        putenv($this->original['putenv'] === false
            ? self::VARIABLE
            : self::VARIABLE . '=' . $this->original['putenv']);

        parent::tearDown();
    }

    /**
     * @return array<string, array{?string, string}>
     */
    public static function values(): array
    {
        return [
            'vacía' => ['', '-03:00'],
            'sin la variable' => [null, '-03:00'],
            'otro desplazamiento' => ['+05:00', '+05:00'],
        ];
    }

    #[DataProvider('values')]
    public function test_the_session_timezone_of_mysql_and_mariadb(?string $value, string $expected): void
    {
        $this->setVariable($value);

        $connections = (require config_path('database.php'))['connections'];

        $this->assertSame($expected, $connections['mysql']['timezone']);
        $this->assertSame($expected, $connections['mariadb']['timezone']);
    }

    private function setVariable(mixed $value): void
    {
        if ($value === null) {
            unset($_SERVER[self::VARIABLE], $_ENV[self::VARIABLE]);
            putenv(self::VARIABLE);

            return;
        }

        $_SERVER[self::VARIABLE] = $_ENV[self::VARIABLE] = $value;
        putenv(self::VARIABLE . '=' . $value);
    }
}
