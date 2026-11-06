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
| 8 | `scopeBindings()` no tiene test: hoy ninguna ruta tiene dos parámetros anidados (`clients/{client}/patients` tiene uno), así que quitarlo no cambia nada y ningún test puede fallar. Cuando aparezca la primera (por ejemplo, `clients/{client}/patients/{patient}`), agregar un test que pida un hijo de otro padre de la misma clínica y espere 404, y comprobar que falla sin `scopeBindings()` | Revisión de la entrega 0.5 (FASE 0), R-013 | Con la primera ruta de dos parámetros anidados |

## Resueltas

| # | Deuda | Cerrada por |
|---|---|---|
| 4 | Sin límite de intentos en el login | R-004: se adelanta a la entrega del `AuthController` de la FASE 0 (5 intentos por minuto por email e IP). Implementado en la entrega 0.3 con la clave por usuario e IP (R-008) |
| 7 | Cédulas de ejemplo en los tests fuera del rango ficticio (`InteractsWithClinics`, `ClinicAssignmentTest` y `ValidationScopeTest`) | Entrega 0.5 de la FASE 0, adelantada desde la FASE 1: las cédulas pasan a `99xxxxxx` y `FictitiousDocumentsTest` revisa toda secuencia de 7 a 9 dígitos de los tests, las factories, los seeders, los fixtures y la guía de Postman (R-013, R-014) |
| 9 | `VoseoTest` usaba una lista cerrada de formas de tú y de usted (no detectaba `completa`, `usa`, `indique`, `usted`…) y ningún test detectaba un mensaje en inglés (revisión de la 1.1, R-017) | Entrega 1.2 de la FASE 1: los imperativos de tú y de usted salen de conjugar una lista de verbos en infinitivo (con los irregulares aparte), se suman las palabras que solo son de tú o de usted y se detectan los mensajes en inglés. Sigue siendo una lista: un verbo nuevo en un mensaje se agrega como infinitivo |
| 10 | El test de marcadores solo buscaba el del original como texto: `:values` por `:value` y un marcador inventado pasaban (revisión de la 1.1, R-017) | Entrega 1.2 de la FASE 1: cada línea tiene exactamente los marcadores del original, con un test propio de la comparación |
| 11 | Los nombres de los campos que solo se validaban en un Controller (`include_deceased`) no se probaban (revisión de la 1.1, R-017) | Entrega 1.2 de la FASE 1: los filtros de los listados pasan a `IndexClientRequest` e `IndexPatientRequest`, y un test impide validar en un Controller |
| 12 | `VoseoTest` rechazaba textos correctos en tercera persona (`registra`, `revisa`…) (revisión de la 1.1, R-017) | Entrega 1.2 de la FASE 1: los imperativos se buscan solo al comienzo de una oración o cláusula, o después de "por favor", "no" o "que" |
