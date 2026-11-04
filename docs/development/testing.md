# Tests

Configurados en la entrega 0.1 de la FASE 0 (`phpunit.xml`, `tests/bootstrap.php`, `tests/TestCase.php`).

| Regla | Detalle |
|---|---|
| Comando | `php artisan test` (suite completa antes de cerrar cualquier entrega) |
| Base | SQLite en `database/testing.sqlite` (lo crea `tests/bootstrap.php`; ignorado por Git). `RefreshDatabase` con `$seed = true` en `TestCase`: migra y siembra (`DatabaseSeeder`: roles, permisos y especies) una vez por ejecución; cada test en una transacción |
| Caché de permisos | `TestCase::setUp` limpia la caché de Spatie en cada test |
| Red | Los tests no usan red: `EMAIL_DNS_CHECK=false`, `Http::fake` para WhatsApp y SIFEN |
| Colas y mail | Cola `sync` y mailer `array` en los tests |
| Permisos | Probar con un rol propio, no con el administrador (`Gate::before` lo deja pasar todo) |
| MySQL | Cada migración nueva se prueba también en MySQL (límite de 64 caracteres en nombres de índices) |
| Concurrencia | Numeración, turnos y stock: el bloqueo real solo se reproduce en MySQL |

Organización: `tests/Feature/<Módulo>` por módulo, `tests/Feature/Access` para permisos por rol.
