# Tests

Se configuran en la FASE 0.

| Regla | Detalle |
|---|---|
| Comando | `php artisan test` (suite completa antes de cerrar cualquier entrega) |
| Base | SQLite en un archivo dentro de `database/` (lo crea la FASE 0): migra y siembra roles y permisos una vez por ejecución; cada test en una transacción |
| Red | Los tests no usan red: `EMAIL_DNS_CHECK=false`, `Http::fake` para WhatsApp y SIFEN |
| Colas y mail | Cola `sync` y mailer `array` en los tests |
| Permisos | Probar con un rol propio, no con el administrador (`Gate::before` lo deja pasar todo) |
| MySQL | Cada migración nueva se prueba también en MySQL (límite de 64 caracteres en nombres de índices) |
| Concurrencia | Numeración, turnos y stock: el bloqueo real solo se reproduce en MySQL |

Organización: `tests/Feature/<Módulo>` por módulo, `tests/Feature/Access` para permisos por rol.
