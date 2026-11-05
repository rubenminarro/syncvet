# Deuda técnica

Deudas y discrepancias abiertas, numeradas. La misma lista resumida está en `CLAUDE.md`, sección 7. Una deuda resuelta pasa a
"Resueltas" con la sección del registro que la cerró.

## Abiertas

| # | Deuda | Origen | Resolver en |
|---|---|---|---|
| 1 | El módulo de clientes y pacientes no tiene tests de PHPUnit; los Form Requests y las rutas no se probaron | Registro R-001 | FASE 1 |
| 2 | Los modelos fuera de clientes y pacientes se generaron desde el esquema: revisar relaciones y casts al usarlos | Registro R-001 | Fase de cada módulo |
| 3 | Códigos SIFEN de `PaymentMethod::sifenCode()` e `invoice_items.unit_of_measure` sin verificar contra el Manual Técnico | Registro R-001 | FASE 9 |
| 5 | Sin claves foráneas compuestas (`clinic_id` + `id`): la base no impide que un registro apunte a otro de una clínica distinta (por ejemplo, `patients.client_id` a un cliente ajeno). Hoy lo cubren las reglas `exists` por clínica, los scopes y los tests de `tests/Feature/Tenancy` | Revisión de la entrega 0.2 (FASE 0) | Antes del primer cliente real |
| 6 | Límite del login en ráfagas: con la caché `database` el contador no es atómico. Causa probable: `RateLimiter::increment` de Laravel, cuando el contador ya existía y su incremento da 1, hace `put($key, 1)` y puede pisar el incremento de otro pedido. Con 20 logins fallidos simultáneos en MySQL pasaron a verificar la contraseña de 5 a 9, en vez de 5: de 0 a 4 de más, con un usuario existente y con emails inexistentes. Riesgo bajo: unas pocas claves más por minuto e IP, sin saltear el límite. En SQLite, además, la contención da 500 (`database is locked`), pero SQLite solo se usa en los tests. Solución prevista: caché en Redis en producción (`CACHE_STORE=redis`), que incrementa de forma atómica. Como el `put` está en el `RateLimiter`, Redis achica mucho la ventana pero no la cierra por sí solo: verificarlo con 20 logins simultáneos y, si sigue pasando de 5, envolver el `hit` en `Cache::lock` | Segunda revisión de la entrega 0.3 (FASE 0), R-008 | FASE 10 (entrega 10.5, despliegue) |
| 7 | Cédulas de ejemplo en los tests fuera del rango ficticio: `tests/Concerns/InteractsWithClinics.php` (`4567890`), `ClinicAssignmentTest` (`1234567`) y `ValidationScopeTest` (`4567890` y `7777777`) pueden ser de personas reales. R-011 pasó a rangos ficticios (RUC `999999xx`, cédulas `99xxxxxx`) el `DemoSeeder`, la guía de Postman y el registro, no los tests | Cierre de la entrega 0.4 (FASE 0), R-012 | FASE 1, con los tests de clientes (deuda 1) |
| 8 | `scopeBindings()` no tiene test: hoy ninguna ruta tiene dos parámetros anidados (`clients/{client}/patients` tiene uno), así que quitarlo no cambia nada y ningún test puede fallar. Cuando aparezca la primera (por ejemplo, `clients/{client}/patients/{patient}`), agregar un test que pida un hijo de otro padre de la misma clínica y espere 404, y comprobar que falla sin `scopeBindings()` | Revisión de la entrega 0.5 (FASE 0), R-013 | Con la primera ruta de dos parámetros anidados |

## Resueltas

| # | Deuda | Cerrada por |
|---|---|---|
| 4 | Sin límite de intentos en el login | R-004: se adelanta a la entrega del `AuthController` de la FASE 0 (5 intentos por minuto por email e IP). Implementado en la entrega 0.3 con la clave por usuario e IP (R-008) |
