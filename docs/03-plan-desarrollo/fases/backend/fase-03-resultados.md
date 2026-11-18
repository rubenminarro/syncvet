# FASE 3 — Agenda y turnos: resultados

El resultado de cada entrega y el cierre de la fase. El diseño y las decisiones, en [`fase-03-agenda-y-turnos.md`](fase-03-agenda-y-turnos.md).

## Resultado

Lo completa el cierre de cada entrega ([`docs/03-plan-desarrollo/prompts/7-cierre.md`](../../prompts/7-cierre.md)): archivos, endpoints, tests y
aserciones, desvíos del diseño y por qué.

### Entrega 3.0

Implementación en `feature/fase-03-00-zona-mysql`, creada desde `docs/fase-03-diseno`, revisada en una sesión de Claude Code aparte
de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-049)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-049)). La implementación y las correcciones quedaron en dos commits WIP (`7c92097`
y `a0f9a4e`), y el cierre va en un commit aparte. Decisiones en R-047 y [R-049](../../../01-arquitectura/decisiones/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `tests/Feature/Database/MySqlSessionTimezoneTest` (solo MySQL), `tests/Feature/Database/DatabaseTimezoneConfigTest` y `tests/Unit/NoDatabaseNowTest` |
| Cambiados | `config/database.php` (`'timezone' => env('DB_TIMEZONE') ?: '-03:00'` en `mysql` y `mariadb`) y `.env.example` (`DB_TIMEZONE=-03:00`, con su comentario) |
| Documentación | `CLAUDE.md` §1, §3 (la zona de la sesión de MySQL, su excepción al "nunca un `-03:00` fijo", las fechas viejas en UTC-4 y el reloj de MySQL prohibido en código nuevo) y §7 (deuda 16 resuelta); `development/setup.md` (sección nueva, `DB_TIMEZONE`) y `development/deployment.md` (la variable en el servidor); deuda técnica, roadmap, módulos, handoff y el registro (R-049) |

**Endpoints.** Ninguno: la entrega no cambia la API. Rutas en 58, permisos en 127 y migraciones en 34, sin cambios.

**Tests.** Suite del 2026-11-10, sobre el código de `a0f9a4e` (el cierre solo cambia documentación), corrida en SQLite y después en
MySQL: 1007 casos con los *data providers*; en SQLite, 10851 aserciones con 8 omitidos (solo de MySQL: los 5 de
`MySqlSessionTimezoneTest` y 3 de antes); contra MySQL (`syncvet_test_mysql`), 10885 aserciones sin omitidos. `composer test:fast`,
1007 casos en verde. Frente a la corrida de R-046 (991 casos; 10833 y 10853 aserciones): 16 casos más, 18 aserciones más en SQLite y
32 en MySQL.

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `MySqlSessionTimezoneTest` (MySQL) | 5 | 14 | La sesión en `-03:00`; otra zona en la sesión y, al reconectar, otra vez `-03:00` (sobre una copia de la conexión); un `timestamp` de 2026 guardado por Eloquent con el mismo `UNIX_TIMESTAMP()` que PHP y leído igual; uno de 2023 (Asunción en UTC-4) guardado como `08:00:00` y leído igual, con el instante dentro de MySQL una hora corrido; `useCurrent()` de `inventory_movements` con la hora de Asunción |
| `DatabaseTimezoneConfigTest` | 3 | 6 | `DB_TIMEZONE` vacía o sin la variable → `-03:00`; otro desplazamiento se respeta; en `mysql` y en `mariadb` |
| `NoDatabaseNowTest` | 8 | 12 | Sin el reloj de MySQL ni `useCurrent()` en las migraciones, `app/`, los seeders, las factories y las rutas (un caso por carpeta); las dos columnas permitidas siguen existiendo; la excepción se identifica por archivo y columna (la misma línea con otra columna no pasa); el buscador encuentra cada forma, en cualquier combinación de mayúsculas, y no marca `now()` de Laravel, los comentarios, `UNIX_TIMESTAMP(columna)` ni palabras que solo empiezan igual |

**Mutaciones.** Las cuatro del diseño, en la implementación, cada una con tests que fallan: sin `timezone`, fallan los dos primeros
tests de `MySqlSessionTimezoneTest` (los del instante y `useCurrent()` pasan en Laragon, que ya está en UTC-3; en un servidor en otra
zona también fallan); con `+00:00`, fallan los cuatro de entonces; `useCurrent()` en una migración nueva y `DB::raw('NOW()')` en
`AuditLogger`, falla `NoDatabaseNowTest`. En la revisión, `DB_TIMEZONE=+00:00` (lo mismo que un servidor en UTC sin la configuración)
hizo fallar los cuatro. En las correcciones: `curdate()` en un seeder y una segunda columna con `useCurrent()` en la migración permitida
hacen fallar `NoDatabaseNowTest`; volver a `env('DB_TIMEZONE', '-03:00')` hace fallar `DatabaseTimezoneConfigTest` con la variable
vacía.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | El test de reconexión reconecta una copia de la conexión `mysql` (`mysql_timezone_probe`), no la principal | Reconectar la principal cortaría la transacción con la que `RefreshDatabase` deshace cada test; Laravel aplica `timezone` igual en cada conexión nueva | Implementación; aceptado por el usuario |
| 2 | `NoDatabaseNowTest` busca con el tokenizer de PHP, no con expresiones regulares sobre el archivo | Con regex marcaba el `now()` de Laravel; el tokenizer revisa solo los textos (el SQL) y las llamadas a `useCurrent`, sin los comentarios | Implementación; aceptado por el usuario |
| 3 | La documentación de `development/`, la deuda técnica, el registro y el roadmap, en el cierre y no en la implementación | El pedido de implementación incluía solo `CLAUDE.md` | Implementación; aceptado por el usuario |
| 4 | La regresión de Postman no se corrió en la implementación | Quedó para la validación del usuario, que la hizo ([validación](#validación-del-usuario-r-049)) | Implementación |

#### Correcciones de la revisión (R-049)

La revisión, en una sesión aparte de la implementación, confirmó: `timezone` se aplica en toda conexión nueva, también al reconectar,
en los workers de la cola (`database`, sobre la conexión por defecto) y en un paralelo sobre MySQL (`test:fast` corre en SQLite); los
tests fallan en un servidor con otra zona; `NoDatabaseNowTest` detecta `DB::raw("now()")`, `->default(DB::raw('CURRENT_TIMESTAMP'))` y
`selectRaw('NOW()')`; y `CLAUDE.md` §3 no contradice la regla de `AppDateTime` ni la de la base de zonas. Lo que encontró y el usuario
pidió corregir:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | `NoDatabaseNowTest` buscaba solo `NOW()` y `CURRENT_TIMESTAMP`: `CURDATE()`, `SYSDATE()`, `UTC_TIMESTAMP()` y las demás, que también dependen de la zona de la sesión, pasaban (la cola del día de la 3.4 invita a usar `CURDATE()`) | También `CURDATE()`, `CURRENT_DATE`, `CURTIME()`, `CURRENT_TIME`, `SYSDATE()`, `LOCALTIME`, `LOCALTIMESTAMP`, `UTC_TIMESTAMP()`, `UTC_DATE()`, `UTC_TIME()` y `UNIX_TIMESTAMP()` sin argumentos, en cualquier combinación de mayúsculas | `NoDatabaseNowTest::test_the_scanner_finds_each_form` |
| 2 | Baja | Con `DB_TIMEZONE=` vacía, `env()` devolvía `""` y Laravel mandaba `SET time_zone=''`, que MySQL rechaza: ninguna conexión abría | `env('DB_TIMEZONE') ?: '-03:00'` en `mysql` y `mariadb` | `DatabaseTimezoneConfigTest` (variable vacía) |
| 3 | Baja | `CLAUDE.md` §3 decía que la ida y la vuelta son exactas con una fecha vieja, sin test; y no avisaba que el instante dentro de MySQL queda corrido | Caso de `2023-07-01T12:00:00Z` (UTC-4): se guarda `08:00:00`, se lee igual y `UNIX_TIMESTAMP()` da una hora menos; §3 dice que esas fechas no se comparan con la hora de MySQL | `MySqlSessionTimezoneTest::test_an_old_daylight_saving_date_keeps_its_instant_in_php` |
| 4 | Baja | El escáner no revisaba los seeders, las factories ni las rutas, y la excepción se reconocía por el texto de la línea (una segunda línea igual en el mismo archivo pasaba) | Revisa también `database/seeders`, `database/factories` y `routes`; `ALLOWED` identifica la excepción por archivo y columna | `NoDatabaseNowTest` (un caso por carpeta y `test_the_allowed_column_is_identified_by_its_name_not_by_the_line`) |

#### Validación del usuario (R-049)

El 2026-11-10 el usuario, con `DB_TIMEZONE=-03:00` en su `.env`, corrió en Postman la colección `syncvet-fase-02-auditoria` contra la
base de desarrollo, como regresión (la entrega no tiene endpoints): 47 de 47 pruebas, 0 errores. Suite: la del cierre (1007 casos;
10851 aserciones en SQLite con 8 omitidos y 10885 en MySQL). Con este cierre la deuda 16 queda resuelta; la 17 (el tope de 2038)
sigue abierta.

### Entrega 3.1

Implementación en `feature/fase-03-01-agendas`, creada desde `feature/fase-03-00-zona-mysql`, revisada en una sesión de Claude Code
aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-050)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-050)). La implementación quedó en el commit WIP `aa96fed`; las correcciones y el
cierre van en el commit de cierre. Decisiones en [R-050](../../../01-arquitectura/decisiones/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `ScheduleController`, `ScheduleBlockController`; `IndexScheduleRequest`, `StoreScheduleRequest`, `UpdateScheduleRequest`, `ScheduleMessages`, `IndexScheduleBlockRequest`, `StoreScheduleBlockRequest`; `ScheduleResource`, `ShowScheduleResource`, `ScheduleBlockResource`, `StoredScheduleBlockResource`; `ScheduleService`, `ScheduleBlockService`; `ScheduleException` y `ScheduleChangedWhileLocking` (señal interna del reintento, revisión); las factories de `Schedule`, `ScheduleHour` y `ScheduleBlock`; `tests/Concerns/InteractsWithSchedules` y `tests/Feature/Schedules` (7 clases); la colección `syncvet-fase-03-agendas` |
| Cambiados | `Schedule`, `ScheduleHour` y `ScheduleBlock` (auditables, con sus casts y valores por defecto; deuda 2); `AppointmentStatus` (`occupiesSlot()` y `occupyingValues()`); `AuditLog::AUDITABLE_TYPES`; `ApiDateTime::DATE_TIME_FORMAT`; `RolesAndPermissionsSeeder` (`schedule-blocks.*`); `routes/api.php`; `lang/es/validation.php` (atributos); `RoleAccessTest`, `RouteBindingTest`, `ValidationTranslationsTest`, `VeterinarianDeleteTest`, `ServiceDeleteTest` y `tests/Fixtures/clinic-roles.json` |
| Documentación | `CLAUDE.md` §1, §3 (orden de bloqueo de la FASE 3 y la excepción de la baja física), §4 (`SCHEDULE_IN_USE`, `SCHEDULE_NOT_EDITABLE` y `CONFLICT`), §6.10 nueva (agendas; las pendientes de diseño pasan a §6.11); la guía de Postman (FASE 3); este documento, el registro (R-050), la deuda técnica (deuda 2), el roadmap, los módulos y el handoff |

**Endpoints.** 8 rutas nuevas (58 → 66): `GET`/`POST schedules`, `GET`/`PATCH`/`PUT`/`DELETE schedules/{schedule}` (el `PUT` es la
misma ruta que el `PATCH`), `GET`/`POST schedules/{schedule}/blocks` (nombres `schedules.schedule-blocks.index` y `.store`, permiso
`schedule-blocks.*`) y `DELETE schedule-blocks/{scheduleBlock}`. Permisos 127 → 130. Sin migraciones (34).

**Tests.** Suite del 2026-11-10 sobre el código del cierre (las correcciones de la revisión), corrida en serie en SQLite (`composer test`) y después en MySQL (`composer test:mysql`): 1108 casos con los *data providers*; en SQLite, 11845 aserciones con 8 omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 11879 aserciones sin omitidos. `composer test:fast`, 1108 casos en verde. Frente a la 3.0 (1007 casos; 10851 y 10885 aserciones): 101 casos más, 994 aserciones más en SQLite y 994 en MySQL. `tests/Feature/Schedules`: 92 casos y 462 aserciones (Store 23 y 107, Hours 19 y 71, Update 13 y 46, Delete 7 y 43, Block 20 y 99, Index 3 y 34, Lock 7 y 62).

| Clase | Verifica |
|---|---|
| `ScheduleStoreTest` | Alta con veterinario y horario (color en mayúsculas, auditoría de la agenda y de cada tramo), con solo el servicio y sus valores por defecto (`online_booking` se ignora); sin veterinario ni servicio; veterinario inactivo, de usuario inactivo, eliminado, de otra clínica o sin la sucursal; servicio inactivo o de otra clínica; sucursal inactiva o de otra clínica; nombre repetido en la sucursal (en otra pasa); campos mal formados; tramos superpuestos en el alta (revisión); el recepcionista (403) |
| `ScheduleHoursTest` | Tramos superpuestos (también uno dentro de otro) y que se tocan; fuera de la grilla, con segundos, hora 24, inicio igual o posterior al fin, día 0 u 8, campo de más; más de 6 por día; el reemplazo borra, crea y conserva con su auditoría; `hours: []` vacía y `null` → 422; sin `hours` no cambia; los turnos no se tocan; el horario anterior intacto si la edición falla (revisión) |
| `ScheduleUpdateTest` | Sucursal o veterinario con turnos (también cancelados) → 422 `SCHEDULE_NOT_EDITABLE`, y reenviar los mismos pasa; sin turnos cambian; mover a una sucursal donde el veterinario no trabaja; el servicio cambia con turnos; el veterinario y el servicio actuales inactivos se aceptan; veterinario nuevo inactivo o de usuario inactivo, sucursal de destino inactiva y nombre repetido en la sucursal de destino (revisión); sin veterinario ni servicio; inactivar con turnos futuros; nombre único; el recepcionista y el veterinario (403) |
| `ScheduleDeleteTest` | Baja sin turnos con sus tramos y bloqueos (auditoría) y el veterinario y el servicio libres; con un turno en cada uno de los 6 estados → 409 `SCHEDULE_IN_USE` |
| `ScheduleBlockTest` | Alta en UTC y sin zona (hora de Asunción); `affected_appointments` con los activos que se superponen (no los cancelados, ausentes, de otra agenda ni los que se tocan), también fichados y atendidos (revisión); en una agenda inactiva y superpuestos; fin no posterior, más de 366 días (exactamente 366 pasa), fuera de la grilla, fecha sola, texto, motivo largo; baja con auditoría; otra clínica (404); listado por defecto, `from`/`to` y `search`; detalle con los próximos; el recepcionista y el veterinario (403 al escribir) |
| `ScheduleIndexTest` | Activas por defecto e `include_inactive`; filtros; `search` literal; paginación; otra clínica; filtros mal formados o ajenos (422) |
| `ScheduleLockTest` | Orden del alta (clínica → veterinario → servicio → nombres) y de la edición (veterinarios y servicio antes que la agenda y ningún veterinario después, también al cambiar solo la sucursal y al cambiar de veterinario: revisión); reintento si el veterinario cambia entre la lectura y el bloqueo, y 409 `CONFLICT` después de 3 intentos (revisión); bloqueos, horario y baja con la agenda primero |
| Otros | `RoleAccessTest` (las 8 rutas por rol), `RouteBindingTest` (agenda o bloqueo de otra clínica → 404 en cada ruta), `ValidationTranslationsTest`, `VeterinarianDeleteTest` y `ServiceDeleteTest` (una agenda creada por la API es uso) |

**Mutaciones.** Las de la implementación no quedaron documentadas: su informe no está en el repositorio. En la revisión, quitar
`ensureVeterinarianWorksIn` al editar y el tope de 6 tramos por día hacen fallar sus tests, y estas cuatro no hacían fallar ninguno:
quitar el chequeo del veterinario nuevo inactivo al editar, el de la sucursal de destino inactiva, el del nombre al cambiar de
sucursal, y no contar los fichados y atendidos como ocupados. En las correcciones, cada una hace fallar su test nuevo; también volver
al orden anterior de la edición (el veterinario leído con la agenda bloqueada) hace fallar
`test_changing_only_the_branch_locks_the_veterinarian_before_the_schedule`, y quitar la verificación del veterinario, los dos tests del
reintento.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | Los turnos afectados por un bloqueo no muestran `contact_name` ni `contact_phone` | Las columnas llegan con la migración de la 3.2a | Implementación; aceptado por el usuario |
| 2 | Al mover una agenda de sucursal se revisa que el veterinario trabaje en la nueva; si está inactivo, se acepta igual | Es el veterinario que la agenda ya tiene, como al reenviarlo | Implementación; aceptado por el usuario |
| 3 | El listado de bloqueos muestra por defecto los que terminan desde hoy, con `from`/`to`, y `search` busca en el motivo | Precisa el listado del diseño | Implementación; aceptado por el usuario |
| 4 | El horario acepta hasta 6 tramos por día y 42 en total, y `hours: null` responde 422 | Un tope para la lista entera; `[]` es la forma de vaciarlo | Implementación; aceptado por el usuario |
| 5 | La documentación, en el cierre | Como en las entregas anteriores | Implementación; aceptado por el usuario |

El informe de la implementación no está en el repositorio; los desvíos salen del resumen del usuario.

**Precisión del diseño.** La tabla de tests pide "bloqueo de otra agenda en la URL → 404", pero `DELETE schedule-blocks/{scheduleBlock}`
no lleva la agenda en la URL: ese caso no existe. El 404 de una agenda o un bloqueo de otra clínica está cubierto (hallazgo 5, R-050).

#### Correcciones de la revisión (R-050)

La revisión, en una sesión aparte de la implementación, confirmó: la lógica en los Services y los errores de dominio (nunca 500); el
orden de bloqueo del alta y de la edición con el veterinario en el pedido; la superposición de tramos (tocarse pasa) y el reemplazo en
la transacción; los bloqueos con y sin zona y `affected_appointments` con la regla de superposición de los turnos; la baja solo sin
turnos en cualquier estado; las listas de uso de veterinarios y servicios con las agendas; y los permisos (el recepcionista y el
veterinario, 403 al escribir). Lo que encontró y el usuario pidió corregir:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | Un `PATCH` que cambiaba la sucursal sin `veterinarian_id` leía el veterinario con `sharedLock` después de bloquear la agenda: con el alta de un turno de la 3.2a (veterinario → agenda), un *deadlock* en MySQL y un 500 | La edición lee el veterinario de la agenda sin bloquearla, bloquea ese y el nuevo por id, después el servicio y la agenda, y verifica que el veterinario no cambió; si cambió, repite (3 intentos; después, 409 `CONFLICT`) | `ScheduleLockTest`: `test_changing_only_the_branch_locks_the_veterinarian_before_the_schedule`, `test_the_update_is_retried_if_the_veterinarian_changes_while_locking` y `test_after_the_attempts_the_update_answers_409` |
| 2 | Media-baja | Cuatro reglas sin test: el veterinario nuevo inactivo al editar, la sucursal de destino inactiva, el nombre único al mover de sucursal, y los fichados y atendidos como afectados | Un test por regla | `ScheduleUpdateTest` (`test_a_new_inactive_veterinarian_is_rejected`, `test_moving_to_an_inactive_branch_is_rejected`, `test_moving_to_a_branch_with_the_same_name_is_rejected`) y `ScheduleBlockTest::test_checked_in_and_attended_appointments_are_also_affected` |
| 3 | Baja | Sin test del horario anterior intacto cuando la edición falla, ni de la superposición en el alta | Tests | `ScheduleHoursTest::test_a_failed_update_keeps_the_previous_hours` y `ScheduleStoreTest::test_overlapping_hours_are_rejected_on_creation` |
| 4 | Baja | El comentario de las agendas entre el de la auditoría y su ruta (`routes/api.php`), y un docblock suelto en `ServiceDeleteTest` | Cada uno en su lugar | — |
| 5 | Baja | El caso "bloqueo de otra agenda en la URL" del diseño no existe | Precisión del diseño (arriba) | — |

Además, el usuario decidió que crear y borrar bloqueos sigue siendo solo del administrador en el MVP, y dejó dos pendientes para la
3.2a: la prueba de Postman de mover una agenda con turnos y de `SCHEDULE_IN_USE` con un turno real, y evaluar los reintentos ante un
*deadlock* en el alta y el mover de turnos ([pendientes que deja la 3.1](fase-03-agenda-y-turnos.md#32a-turnos-únicos)).

#### Validación del usuario (R-050)

El 2026-11-10 el usuario corrió en Postman, en otra PC, con el Collection Runner y una iteración, la colección
`syncvet-fase-03-agendas`: 35 requests, 68 de 68 pruebas, 0 errores. Las correcciones de la revisión no cambian la API: la edición
solo cambia el orden de los bloqueos y suma un 409 `CONFLICT` que aparece únicamente con ediciones simultáneas. Suite: la del cierre.

### Entrega 3.2a

Implementación en `feature/fase-03-02a-turnos`, creada desde `feature/fase-03-01-agendas`, revisada en una sesión de Claude Code
aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-051)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-051)). La implementación quedó en el commit WIP `fa03b6f` y las correcciones en
`b0dd969`; el cierre va en un commit aparte, solo de documentación. Decisiones en [R-051](../../../01-arquitectura/decisiones/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `AppointmentController`; `AppointmentMessages`, `IndexAppointmentRequest`, `StoreAppointmentRequest`, `UpdateAppointmentRequest`; `AppointmentResource`, `ShowAppointmentResource`; `AppointmentService`; la migración `2026_11_10_000000_add_contact_and_overbook_to_appointments`; `tests/Concerns/InteractsWithAppointments`, `tests/Feature/Appointments` (8 clases) y `tests/Support/concurrent-request.php` (un pedido a la API en un proceso de PHP aparte); la colección `syncvet-fase-03-turnos` |
| Cambiados | `Appointment` (contacto, sobreturno, valores por defecto; deuda 2) y `Client` (`appointments()`); `ClientService` y `PatientService` (las bajas revisan el uso con la fila bloqueada; el cambio de dueño en una transacción con el paciente y los clientes bloqueados, revisión); `AppointmentException` y `ScheduleChangedWhileLocking` (sus comentarios); `StoredScheduleBlockResource` (el contacto en `affected_appointments`); `RolesAndPermissionsSeeder` (`appointments.overbook`); `routes/api.php`; `lang/es/validation.php` (atributos); `RoleAccessTest`, `RouteBindingTest`, `ValidationTranslationsTest`, `ClientDeleteTest`, `ScheduleBlockTest` y `tests/Fixtures/clinic-roles.json` |
| Documentación | `CLAUDE.md` §1, §3 (orden de bloqueo de turnos con el cliente, el cambio de dueño y los reintentos), §4 (`APPOINTMENT_*` y `CONFLICT` de turnos), §6.1, §6.2, §6.10 (turnos) y §6.11; en la implementación, §2 (los pendientes se escriben en el repositorio, pedido del usuario); la guía de Postman (turnos); este documento, el registro (R-051), la deuda técnica (deuda 2), el roadmap, los módulos y el handoff |

**Endpoints.** 4 rutas nuevas (66 → 70): `GET`/`POST appointments` y `GET`/`PATCH`/`PUT appointments/{appointment}` (el `PUT` es la
misma ruta que el `PATCH`); sin `DELETE` (405): un turno se cancela (3.2b). Permisos 130 → 131: `appointments.overbook`, del
administrador y la recepción, un permiso de un campo (`overbook: true`) revisado en el `authorize()` del Form Request, no de una
ruta. Migraciones 34 → 35: `contact_name`, `contact_phone`, `is_overbooked` y el índice `(veterinarian_id, starts_at)`.

**Tests.** Suite del 2026-11-10 sobre el código de `b0dd969` (el cierre solo cambia documentación), corrida en serie en SQLite
(`composer test`) y después en MySQL (`composer test:mysql`): 1212 casos con los *data providers*; en SQLite, 12835 aserciones con 10
omitidos (los 8 de MySQL de antes y los 2 de `AppointmentConcurrencyTest`); contra MySQL (`syncvet_test_mysql`), 12879 aserciones sin
omitidos. `composer test:fast`, 1212 casos en verde. Frente a la 3.1 (1108 casos; 11845 y 11879 aserciones): 104 casos más, 990
aserciones más en SQLite y 1000 en MySQL. `tests/Feature/Appointments`: 99 casos; 555 aserciones en SQLite y 565 en MySQL.

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `AppointmentStoreTest` | 34 | 142 | Alta con paciente (el cliente es su dueño; sucursal y veterinario de la agenda), con cliente sin paciente y con contacto; sin ninguno; paciente de otro cliente, fallecido o de otra clínica; contacto con cliente o paciente; `ends_at` por defecto (servicio o `slot_minutes`); el mismo instante con `Z`, con desplazamiento y sin zona; campos que fija el sistema (`prohibited`); `source: online` con el mensaje en español (revisión); día pasado y hoy más temprano; cerca de la medianoche, hoy sigue siendo el día de Asunción (revisión); fuera de la grilla, fin no posterior, cruce de medianoche, más de 720 minutos y exactamente 720; agenda inactiva, veterinario inactivo, servicio inactivo; agenda sin veterinario; el veterinario (403) y la recepción con su rol |
| `AppointmentScheduleRulesTest` | 11 | 50 | Fuera del horario (antes, empieza dentro y termina fuera, entre tramos, abarca dos tramos con un hueco, después, día sin tramos) → 422 `APPOINTMENT_OUTSIDE_HOURS`; los bordes exactos; los tramos que se tocan como horario continuo y un hueco real fuera (revisión); el día de la semana de Asunción; un bloqueo que se superpone → 422 `APPOINTMENT_IN_BLOCK` y uno que se toca → 201; el bloqueo de otra agenda |
| `AppointmentOverlapTest` | 11 | 40 | El mismo horario y superposiciones parciales → 409 `APPOINTMENT_OVERLAP`; los que se tocan → 201; por estado (a confirmar, confirmado, fichado y atendido ocupan; ausente y cancelado no); el mismo veterinario en otra agenda → 409 y otro veterinario o una agenda sin veterinario → 201; un turno común contra un sobreturno → 409 |
| `AppointmentOverbookTest` | 5 | 20 | Sobreturno con permiso (`is_overbooked: true`), sin choque (`false`), sin permiso (403; `overbook: false` no lo pide), la recepción con su rol; con otro turno en el horario, igual respeta el horario y los bloqueos (revisión) |
| `AppointmentUpdateTest` | 18 | 118 | Mover dentro de la agenda sin chocar consigo mismo, con la duración y el estado; a otra agenda (copia sucursal y veterinario); a un horario ocupado → 409; horario, bloqueos y día al mover; agenda inactiva al mover y otros datos sin mover; solo a confirmar o confirmado (`APPOINTMENT_NOT_EDITABLE`); de contacto a cliente y de cliente a paciente; el paciente de ese cliente; el cliente es el dueño actual después de un cambio de dueño, con el formulario completo y con el paciente reenviado (revisión); el paciente fallecido ya asignado; solo el servicio no recalcula `ends_at`; sobreturno sin permiso; sin `DELETE` y el veterinario (403); `PUT` parcial |
| `AppointmentIndexTest` | 5 | 69 | Hoy por defecto; `from`/`to`, más de 31 días → 422; filtros; `search` literal; otra clínica |
| `AppointmentLockTest` | 13 | 116 | Orden del alta (veterinario → servicio → agenda → paciente → cliente, y el horario, los bloqueos y la superposición después de la agenda) y de mover (las dos agendas y después el turno); las bajas de cliente y paciente con su fila bloqueada; reintento si cambia el veterinario de la agenda y 409 después de 3; al mover, reintento si cambia el veterinario de la agenda de destino o la agenda del turno, y 409 después de 3 (revisión); *deadlock* repetido y 409, en el alta y al mover; espera vencida (1205) sin repetir, 409 (revisión); el cambio de dueño con el paciente y después los dos clientes por id, y el dueño nuevo eliminado en el medio → 422 (revisión) |
| `AppointmentConcurrencyTest` (MySQL) | 2 | 10 | Dos procesos de PHP aparte piden el mismo turno mientras una tercera conexión tiene tomada una fila (el veterinario; con una agenda solo de servicio, el servicio, revisión); al soltarla, un 201 y un 409 `APPOINTMENT_OVERLAP` |
| Otros | — | — | `RoleAccessTest` (las 4 rutas por rol), `RouteBindingTest` (un turno de otra clínica → 404; una agenda, un paciente, un cliente o un servicio de otra clínica → 422), `ValidationTranslationsTest`, `ClientDeleteTest` (un cliente con un turno → 409 `CLIENT_IN_USE`) y `ScheduleBlockTest` (el contacto en `affected_appointments`, revisión) |

**Mutaciones**, cada una con tests que fallan. De la implementación: `<` por `<=` en la superposición (`AppointmentOverlapTest`, los
que se tocan); contar los cancelados y ausentes como ocupados, y no contar los fichados y atendidos (`AppointmentOverlapTest`, por
estado); quitar el chequeo de bloqueos (3 tests de ScheduleRules, Overbook y Update); quitar el permiso del sobreturno
(`AppointmentOverbookTest`); quitar el veterinario de la superposición entre agendas (`AppointmentOverlapTest`); quitar el
`lockForUpdate` del veterinario (en MySQL, `AppointmentConcurrencyTest` y 4 de `AppointmentLockTest`); la baja del cliente sin bloquear
la fila (`AppointmentLockTest`) o sin contar los turnos (`ClientDeleteTest`). Quitar el `lockForUpdate` de la agenda, en la
implementación, solo hacía fallar los dos tests de orden en MySQL; en la revisión dio dos 201 en el mismo horario con una agenda solo
de servicio (3 de 3 corridas) y en las correcciones hace fallar el test de dos procesos nuevo (2 de 2). De las correcciones: "hoy" en
UTC (`->utc()->startOfDay()`), quitar los rechequeos del reintento al mover, reintentar la espera vencida, no aceptar el cliente
guardado, no unir los tramos que se tocan y bloquear un cliente antes que el paciente en el cambio de dueño: cada una hace fallar su
test nuevo. En SQLite los tests de orden no ven el `FOR UPDATE` (como en la 3.1): esa parte solo se verifica en MySQL.

**Desvíos del diseño.** Los 13 de la implementación, aceptados por el usuario en la revisión; el 5, el 7 y el 13 cambiaron con las
correcciones.

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | El alta y la edición también toman el **cliente** con `sharedLock`, después del paciente (agenda → paciente → cliente → turno) | La baja del cliente lo bloquea antes de revisar el uso (pregunta 21); sin el bloqueo, un turno con cliente y sin paciente podía quedar en un cliente eliminado. Ninguna operación bloquea un cliente y después un paciente | Implementación; aceptado por el usuario |
| 2 | Una edición que no mueve (notas, motivo, servicio, paciente) no bloquea el veterinario ni las agendas: solo el servicio, el paciente y el cliente que llegan y el turno | El horario no cambia; se respeta el orden con un subconjunto. Mover (llega `schedule_id`, `starts_at` o `ends_at`) bloquea todo | Implementación; aceptado por el usuario |
| 3 | Mover sin `ends_at` conserva la duración del turno | El diseño solo dice que cambiar el servicio no recalcula `ends_at` | Implementación; aceptado por el usuario |
| 4 | Al mover (también dentro de la misma agenda) se exigen la agenda activa y su veterinario activo; sin mover, un turno de una agenda inactiva se sigue editando | Un turno movido es como uno nuevo; pregunta 24 | Implementación; aceptado por el usuario |
| 5 | Reintentos: hasta 3 (`AppointmentService::ATTEMPTS`) si cambian el veterinario o el servicio de la agenda, la agenda, el paciente, el cliente o el estado del turno entre la lectura y el bloqueo, o si MySQL informa un *deadlock* (40001 o 1213); después, 409 `CONFLICT`. Una espera vencida (1205) no se repite: 409 enseguida (revisión, hallazgo 7) | Pendiente de la 3.1: nunca un 500 | Implementación; 1205, decisión del usuario |
| 6 | Al editar, el paciente que el turno ya tiene se acepta aunque haya fallecido; otro fallecido → 422 | El formulario lo reenvía, como las especies y razas inactivas | Implementación; aceptado por el usuario |
| 7 | Con paciente, otro `client_id` → 422 en `patient_id`, "El paciente no es de ese cliente." El cliente del turno es el **dueño actual** del paciente; al editar se acepta también el `client_id` guardado en el turno del mismo paciente, y queda el dueño actual (revisión, hallazgo 2) | Después de un cambio de dueño, el formulario completo daba 422 | Implementación; dueño actual, decisión del usuario |
| 8 | `contact_name`: al menos una letra, sin caracteres de control ni `<` `>` (regex propia) | El diseño dice "texto libre con al menos una letra" | Implementación; aceptado por el usuario |
| 9 | Listado: el rango filtra por `starts_at`; con solo `from` o solo `to`, el otro es el mismo día; `status` acepta uno o una lista | Precisa el diseño | Implementación; aceptado por el usuario |
| 10 | `is_overbooked` marca solo el turno dado como sobreturno, si al guardarlo se superponía; al mover se recalcula; `overbook` sin mover se ignora (pide solo el permiso). No cambia si después se cancela el otro turno | P2 | Implementación; aceptado por el usuario tal como está |
| 11 | `StoredScheduleBlockResource` suma `contact_name` y `contact_phone` a los turnos afectados | Cierra el desvío 1 de la 3.1 | Implementación; aceptado por el usuario |
| 12 | La baja del cliente sigue contando solo los pacientes no eliminados; suma los turnos y revisa todo con la fila bloqueada | Pregunta 21, sin cambiar lo demás | Implementación; aceptado por el usuario |
| 13 | Mensajes que el diseño no fija: "La agenda es obligatoria.", "El paciente no existe.", "El cliente no existe.", "El paciente falleció: no se le pueden dar turnos.", "El nombre y el teléfono de contacto van solo en un turno sin cliente ni paciente.", "Ingresá el nombre y el teléfono de contacto juntos.", "El fin del turno debe ser posterior a su inicio.", "El turno no puede durar más de 720 minutos.", "El turno tiene que empezar y terminar el mismo día.", "El origen debe ser recepción, teléfono o WhatsApp: la reserva online no está disponible." (con las etiquetas en español desde la revisión), "El rango no puede superar los 31 días." y los de los campos `prohibited` | — | Implementación; aceptado por el usuario |

**Deuda 2 (`Appointment`).** Relaciones y casts revisados: coinciden con las claves foráneas; los instantes con `AppDateTime`; suma
`contact_name`, `contact_phone` e `is_overbooked` (cast `boolean`) y los valores por defecto de la base en `$attributes` (a confirmar,
recepción, sin sobreturno). `Client` suma `appointments()`.

#### Correcciones de la revisión (R-051)

La revisión, en una sesión aparte de la implementación, confirmó: el orden de bloqueo del alta, del mover y de las bajas de cliente y
paciente, compatible con la 3.1 y la FASE 2; que dentro de la transacción no hay lecturas sin bloqueo antes de los bloqueos (en MySQL,
lo que se lee después ve lo último confirmado); que cada reintento vuelve a leer todo; la superposición con los bordes que se tocan; el
sobreturno; el horario en el borde exacto; los roles (el veterinario no da ni mueve turnos, 403). Lo que encontró y el usuario pidió
corregir:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Alta | El test de dos procesos usaba una agenda con veterinario: sin el `lockForUpdate` de la agenda seguía pasando, y con una agenda solo de servicio daba dos turnos en el mismo horario | Test de dos procesos con una agenda solo de servicio (la tercera conexión toma el servicio) | `AppointmentConcurrencyTest::test_in_a_schedule_without_a_veterinarian_the_schedule_lock_orders_the_bookings` |
| 2 | Media | Después de un cambio de dueño, reenviar el formulario (el paciente y el cliente anterior) daba 422, y cada camino comparaba contra otro dueño | El cliente del turno es el dueño actual del paciente; se acepta el guardado o el actual, y queda el actual; reenviar el paciente lo actualiza; `client_id` en la lectura y el rechequeo del reintento | `AppointmentUpdateTest::test_after_a_change_of_owner_the_client_is_the_current_owner` y `test_resending_the_patient_or_the_stored_client_takes_the_current_owner` |
| 3 | Media | Ningún test con el reloj cerca de la medianoche | A las 22:30 de Asunción (01:30 UTC del día siguiente), un turno de hoy a las 20:00 → 201 | `AppointmentStoreTest::test_near_midnight_today_is_still_the_day_of_asuncion` |
| 4 | Media | Los rechequeos del reintento al mover sin test | Tests del veterinario de la agenda de destino y de la agenda del turno que cambian, y 409 después de 3 | `AppointmentLockTest` (3 tests de mover) |
| 5 | Baja | El sobreturno fuera del horario o en un bloqueo, sin otro turno en el horario | Un turno en cada horario | `AppointmentOverbookTest::test_an_overbooked_appointment_still_respects_the_hours_and_the_blocks` |
| 6 | Baja | El contacto en `affected_appointments` (desvío 11) sin test | Test | `ScheduleBlockTest::test_affected_appointments_show_the_contact` |
| 7 | Baja | La espera vencida (1205) se reintentaba: hasta 3 × `innodb_lock_wait_timeout` | 409 `CONFLICT` enseguida; el *deadlock* sigue con hasta 3 intentos | `AppointmentLockTest::test_a_lock_wait_timeout_is_not_retried_and_answers_409` |
| 8 | Baja | `PatientService::changeOwner` sin transacción ni bloqueo (de la FASE 1): un cambio de dueño simultáneo con la baja del dueño nuevo podía dejar el paciente en un cliente eliminado | En una transacción, el paciente (`lockForUpdate`) y después los dos clientes por id; el dueño nuevo eliminado en el medio → 422 en `client_id`. El usuario había pedido clientes → paciente y eligió este orden al ver que el otro chocaba con el de los turnos | `AppointmentLockTest::test_changing_the_owner_locks_the_patient_then_both_clients_by_id` y `test_changing_to_an_owner_deleted_while_locking_is_rejected` |

Además, el usuario decidió que los tramos del mismo día que se tocan (08:00–12:00 y 12:00–16:00) cuentan como un horario continuo (un
hueco real sigue dando 422; `AppointmentScheduleRulesTest::test_touching_ranges_are_one_continuous_range_and_a_gap_is_not`), y que el
mensaje de `source` use las etiquetas en español. Los dos pendientes de la 3.1 quedan resueltos: mover una agenda con turnos (422
`SCHEDULE_NOT_EDITABLE`) y la baja de una agenda con un turno real (409 `SCHEDULE_IN_USE`) están en la colección, y el alta y el mover
reintentan ante un *deadlock*.

#### Validación del usuario (R-051)

El 2026-11-10, después de las correcciones de la revisión, el usuario corrió en Postman, con el Collection Runner y una iteración, la
colección `syncvet-fase-03-turnos` (37 requests): 67 de 67 pruebas, 0 errores. Suite: la del cierre.

### Entrega 3.2b

Implementación en `feature/fase-03-02b-estados`, creada desde `feature/fase-03-02a-turnos`, revisada en una sesión de Claude Code
aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-053)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-053)). La implementación quedó en el commit WIP `a1a1ab4` y las correcciones en
`d811fa1`; el cierre va en un commit aparte, solo de documentación. En la misma rama, `964db22` saca `disallowed-tools` de las skills
de lectura (R-052, sin relación con la entrega). Decisiones en [R-053](../../../01-arquitectura/decisiones/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `AppointmentTransitionController`; `CancelAppointmentRequest` y `UndoNoShowAppointmentRequest` (revisión); `AppointmentTransitionService`; `App\Enums\AppointmentTransition`; la migración `2026_11_10_000001_add_attended_and_no_show_to_appointments`; `tests/Concerns/RunsConcurrentRequests` (los helpers de dos procesos, desde `AppointmentConcurrencyTest`) y `tests/Feature/Appointments/Transitions` (7 clases) |
| Cambiados | `Appointment` (`attended_at` y `no_show_at` con `AppDateTime`, `startsToday()`; deuda 2); `AppointmentStatus` (sin cambios al final: `canTransitionTo()` e `isFinal()` se borraron en la revisión); `AppointmentException` (`invalidTransition()` y `notYours()`); `AppointmentService` (`withRetries` público, `overlapsAnother` y `undoNoShow`); `ShowAppointmentResource` (`attended_at` y `no_show_at`); `RolesAndPermissionsSeeder` (`appointments.attend` y `appointments.no-show`); `routes/api.php`; `lang/es/validation.php` (atributo); `InteractsWithApiRoutes` (`ROUTES_WITH_ANOTHER_PERMISSION`), `InteractsWithAppointments`, `AppointmentConcurrencyTest`, `RouteBindingTest` y `tests/Fixtures/clinic-roles.json`; la colección `syncvet-fase-03-turnos` |
| Documentación | `CLAUDE.md` §1, §3 (orden de las transiciones y de deshacer el ausente), §4 (los `APPOINTMENT_*` de las transiciones y `APPOINTMENT_NOT_YOURS`), §6.10 (estados) y §6.11 (los estados salen de las pendientes de diseño); la guía de Postman (turnos); este documento, el registro (R-053), la deuda técnica (deuda 2), el roadmap, los módulos, el `README.md` y el handoff |

**Endpoints.** 6 rutas nuevas (70 → 76): `POST appointments/{appointment}/confirm`, `/check-in`, `/attend`, `/no-show`,
`/undo-no-show` y `/cancel`, con nombres `appointments.confirm`, `.check-in`, `.attend`, `.no-show`, `.undo-no-show` y `.cancel`.
Permisos 131 → 133: `appointments.attend` (del administrador y el veterinario) y `appointments.no-show` (del administrador y la
recepción); `appointments.confirm`, `.check-in` y `.cancel` ya existían; `appointments.undo-no-show` usa `appointments.no-show`
(pregunta 19), en `ROUTES_WITH_ANOTHER_PERMISSION`. Migraciones 35 → 36: `attended_at` y `no_show_at`, `datetime` nulas (pregunta 20).

**Tests.** Suite del 2026-11-10 sobre el código de `d811fa1` (el cierre solo cambia documentación), corrida en serie en SQLite
(`composer test`) y después en MySQL (`composer test:mysql`): 1303 casos con los *data providers*; en SQLite, 13373 aserciones con 11
omitidos (los 10 de MySQL de antes y `AppointmentTransitionConcurrencyTest`); contra MySQL (`syncvet_test_mysql`), 13422 aserciones
sin omitidos. `composer test:fast`, 1303 casos en verde. Frente a la 3.2a (1212 casos; 12835 y 12879 aserciones): 91 casos más (85 de
las transiciones y 6 de `RouteBindingTest`), 538 aserciones más en SQLite y 543 en MySQL. `tests/Feature/Appointments/Transitions`:
85 casos; 391 aserciones en SQLite y 396 en MySQL. Antes de las correcciones eran 1290 casos (13316 y 13365 aserciones).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `AppointmentTransitionTest` | 37 | 190 | Cada transición válida (estado, hora y auditoría `updated` con el estado anterior y el nuevo): confirmar, fichar desde a confirmar y confirmado, atendido, ausente desde los dos, cancelar desde a confirmar, confirmado y fichado; la matriz de inválidas (escrita a mano, cada acción desde cada estado que no admite → 422 `APPOINTMENT_INVALID_TRANSITION`, con el estado en el mensaje y la fila sin cambios; también repetir la misma); el cancelado y el ausente liberan el horario y el fichado no; un cancelado no se reabre (fichado → cancelado → nada) |
| `AppointmentCheckInTest` | 9 | 39 | Sin paciente (con contacto o con cliente) → 422 `APPOINTMENT_PATIENT_REQUIRED`; paciente fallecido → 422; otro día (mañana y ayer) → 422 `APPOINTMENT_CHECK_IN_NOT_TODAY` y más tarde hoy, 200; a las 22:30, a las 23:50 (un turno de las 23:30) y a las 00:10 (uno de ayer a las 23:30), el día es el de Asunción (revisión); el veterinario ficha los suyos y los de agendas sin veterinario, otro → 403 `APPOINTMENT_NOT_YOURS`; sin ficha o con la ficha eliminada, solo los de agendas sin veterinario (revisión); la recepción, cualquiera |
| `AppointmentAttendTest` | 6 | 25 | El veterinario marca los suyos y los de agendas sin veterinario, otra ficha → 403 `APPOINTMENT_NOT_YOURS`; sin ficha o con la ficha eliminada, solo los de agendas sin veterinario (revisión); la recepción → 403; el administrador y quien tiene `appointments.update`, cualquiera; la ficha antes que el estado |
| `AppointmentNoShowAndCancelTest` | 9 | 41 | Ausente antes de la hora → 422 `APPOINTMENT_NO_SHOW_TOO_EARLY` y a la hora exacta, 200; un turno de días atrás; el veterinario no marca ausente, no cancela ni confirma (403); cancelar sin motivo, vacío, corto, largo o no texto → 422; sin límite de anticipación (dentro de un minuto y de un año), con `cancelled_by` y `cancelled_at` |
| `UndoNoShowTest` | 15 | 58 | El mismo día → confirmado, `no_show_at` nulo, `confirmed_at` la hora actual (o el que tenía) y la auditoría; otro día → 422 `APPOINTMENT_NO_SHOW_NOT_UNDOABLE`, también a las 00:10 con un turno de ayer a las 23:30 y con uno de hace 3 días marcado ausente hoy, y a las 23:50 un turno de las 20:00 se deshace (revisión); uno que no está ausente → 422; el horario tomado en la agenda o por el mismo veterinario en otra → 409 `APPOINTMENT_OVERLAP` (tocarse no choca); con `overbook: true` vuelve como sobreturno, con el horario libre no marca, y `overbook` no booleano → 422 (revisión); un sobreturno vuelve y recalcula `is_overbooked`; un rol sin `appointments.overbook`: `overbook: true` → 403, un sobreturno con el horario tomado o libre → 403, un turno común con el horario libre → 200 (revisión); el veterinario → 403 |
| `AppointmentTransitionLockTest` | 8 | 38 | Cada transición bloquea el turno como primera consulta y no bloquea la agenda ni el veterinario; deshacer el ausente: veterinario → agenda → turno y la superposición después; reintento si cambia el veterinario de la agenda; un *deadlock* repetido → 409 y una espera vencida → 409 al primer intento |
| `AppointmentTransitionConcurrencyTest` (MySQL) | 1 | 5 | Dos procesos de PHP aparte fichan y marcan ausente el mismo turno mientras una tercera conexión lo tiene tomado: uno 200 y el otro 422 `APPOINTMENT_INVALID_TRANSITION` |
| Otros | — | — | `RouteBindingTest` (las 6 rutas con un turno de otra clínica → 404, sin cambios), `RoleAccessTest` (las 6 rutas por rol, con la matriz automática), `ClinicRolesTest` (la foto de los roles) |

Las aserciones por clase son de SQLite; en MySQL suman las 5 de `AppointmentTransitionConcurrencyTest`, omitido en SQLite (396 en
total).

**Mutaciones**, cada una con tests que fallan. De la implementación: permitir cancelado → fichado y quitar los finales
(`AppointmentTransitionTest`: la matriz de inválidas y `test_a_cancelled_appointment_is_not_reopened`); quitar el chequeo del paciente
al fichar (`AppointmentCheckInTest`); quitar el chequeo de la hora del ausente (`AppointmentNoShowAndCancelTest`); que el cancelado
siga ocupando el horario (`AppointmentTransitionTest`, `AppointmentOverlapTest` y `AppointmentStoreTest`); deshacer el ausente sin el
chequeo del día (`UndoNoShowTest`) o sin revisar la superposición (`UndoNoShowTest`, 2 tests); quitar la regla de la ficha
(`AppointmentAttendTest` y `AppointmentCheckInTest`); el día de fichar en UTC (`AppointmentCheckInTest`, cerca de la medianoche);
quitar el `lockForUpdate` del turno (en MySQL, `AppointmentTransitionConcurrencyTest`, 2 de 2 corridas, y los 5 casos de orden de
`AppointmentTransitionLockTest`). De las correcciones: `startsToday()` en UTC (`UndoNoShowTest`, los dos de medianoche: en la
implementación ningún test de deshacer el ausente fallaba); el veterinario sin ficha sin restricción (`AppointmentAttendTest` y
`AppointmentCheckInTest`, los dos casos de cada uno); deshacer el ausente sin mirar `overbook` (`UndoNoShowTest`, con el horario
tomado); sin el permiso para un sobreturno que vuelve y `authorize()` sin el permiso de `overbook: true` (`UndoNoShowTest`, sin el
permiso). En SQLite los tests de orden no ven el `FOR UPDATE`: esa parte solo se verifica en MySQL.

**Desvíos del diseño.** Los 12 de la implementación, aceptados por el usuario en la revisión salvo el 5, que cambió con las
correcciones.

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | Las transiciones viven en `App\Enums\AppointmentTransition` (cada acción con sus estados de origen, `origins()`, y su destino, `to()`). El diseño ([estados del turno](fase-03-agenda-y-turnos.md#estados-del-turno)) dice que `AppointmentStatus` suma `canTransitionTo()`: se sumó con `isFinal()`, sin uso, y los dos se borraron en la revisión (hallazgo 5) | El destino solo no alcanza: confirmar y deshacer el ausente llevan los dos a confirmado, desde estados distintos | Implementación; aceptado por el usuario |
| 2 | `AppointmentTransitionController` y `AppointmentTransitionService` aparte; deshacer el ausente vive en `AppointmentService::undoNoShow`, que comparte los bloqueos, la superposición y los reintentos del alta | El Service de turnos ya era grande; el ausente vuelve a ocupar el horario como un alta | Implementación; aceptado por el usuario |
| 3 | Fichar un turno con el paciente fallecido → 422 `VALIDATION_ERROR` en `patient_id`, "El paciente falleció: no se puede fichar el turno." | El diseño dice "no fallecido" sin un código | Implementación; aceptado por el usuario |
| 4 | En fichar y atendido, la regla del veterinario se revisa antes que el estado (un turno ajeno sin fichar también da 403) | Un veterinario no averigua el estado de un turno ajeno por el error | Implementación; aceptado por el usuario |
| 5 | El 403 del turno ajeno usaba el mensaje genérico (`FORBIDDEN`); desde la revisión, 403 `APPOINTMENT_NOT_YOURS`, "Ese turno no es de tu agenda." (hallazgo 1) | El diseño dice solo "403" | Implementación; código y mensaje propios, decisión del usuario |
| 6 | Todas las transiciones van por `AppointmentService::withRetries`: un *deadlock* se repite hasta 3 veces y después 409 `CONFLICT`; una espera vencida (1205), 409 enseguida (R-051). Deshacer el ausente repite también si la agenda cambió de veterinario o el turno de agenda entre la lectura y el bloqueo | Nunca un 500, como el alta | Implementación; aceptado por el usuario |
| 7 | Deshacer el ausente no revisa que la agenda siga activa ni que su veterinario siga activo | El diseño solo excluye el horario y los bloqueos; el turno ya existía en esa agenda | Implementación; aceptado por el usuario |
| 8 | Fichar se permite el día del turno a cualquier hora (antes de su inicio: el cliente llegó temprano); el ausente, desde la hora de inicio, también días después | Precisa el diseño ("solo el día del turno"; "`starts_at <= now`") | Implementación; aceptado por el usuario |
| 9 | El test de dos procesos usa fichar y ausente, no cancelar y fichar | Cancelar un fichado es válido: si fichar entra primero, las dos responden 200. Fichar y ausente se excluyen en cualquier orden | Implementación; aceptado por el usuario |
| 10 | Los helpers de `AppointmentConcurrencyTest` pasan al trait `tests/Concerns/RunsConcurrentRequests` (los usan los dos tests de dos procesos) | Sin copiar código | Implementación; aceptado por el usuario |
| 11 | Mensajes que el diseño no fija: "Turno confirmado.", "Turno fichado.", "Turno marcado como atendido.", "Turno marcado como ausente.", "Se deshizo el ausente: el turno volvió a confirmado.", "Turno cancelado.", y los de `cancellation_reason` ("Ingresá el motivo de la cancelación.", al menos 3 y hasta 500 caracteres) | — | Implementación; aceptado por el usuario |
| 12 | Postman: una segunda agenda de la corrida, "del día" (todos los días de 00:00 a 23:55, con el mismo veterinario), para los turnos de hoy; el login del veterinario de la corrida y el de `vet-a@example.com` (otra ficha, para el 403); cada corrida deja también esa agenda inactiva y sus turnos de hoy | La colección corre a cualquier hora: el turno de las 00:00 ya empezó (ausente) y es de hoy (fichar) | Implementación; aceptado por el usuario |

**Decisión del usuario en la implementación.** Fichar también queda restringido como marcar atendido (cambia la respuesta a la
[pregunta 18](fase-03-agenda-y-turnos.md#preguntas-del-diseño), "Fichar, cualquiera"): un veterinario sin `appointments.update` ficha solo los turnos de su
ficha o de agendas sin veterinario. La revisión la extendió al rol (hallazgo 2).

**Deuda 2 (`Appointment`).** Suma `attended_at` y `no_show_at` con `AppDateTime` y `startsToday()` (el día del turno en la hora de
Asunción); las relaciones no cambian.

#### Correcciones de la revisión (R-053)

La revisión, en una sesión aparte de la implementación, confirmó: la tabla de estado × transición del diseño, con todas las inválidas
en la matriz (también repetir la misma); que un cancelado no se reabre por las transiciones ni por el `PATCH` o mover
(`AppointmentUpdateTest`); `attended_at` y `no_show_at` con `AppDateTime` y en UTC con `Z`, sin el reloj de MySQL; el orden de bloqueo
de deshacer el ausente (veterinario → agenda → turno), sin *deadlock* posible con el `PATCH`; los roles (la recepción no marca
atendido; el veterinario no cancela, no marca ausente ni confirma). Lo que encontró y el usuario pidió corregir:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | A corregir (decisión del usuario) | El 403 del turno ajeno usaba el mensaje genérico (`FORBIDDEN`) | 403 `APPOINTMENT_NOT_YOURS`, "Ese turno no es de tu agenda." (`AppointmentException::notYours()`), al fichar y al marcar atendido | `AppointmentAttendTest` y `AppointmentCheckInTest` (`assertNotYours`) |
| 2 | Media | Un usuario con el rol `veterinario` y sin ficha (o con la ficha eliminada) fichaba y marcaba atendido cualquier turno | La regla se aplica por el rol (o la ficha): sin ficha, o con la ficha eliminada, solo los turnos de agendas sin veterinario; los demás, 403 `APPOINTMENT_NOT_YOURS`. El administrador y quien tiene `appointments.update`, cualquiera | `AppointmentAttendTest::test_a_veterinarian_without_a_record_attends_only_unassigned_appointments` y `AppointmentCheckInTest::test_a_veterinarian_without_a_record_checks_in_only_unassigned_appointments` (sin ficha y con la ficha eliminada) |
| 3 | Media | Deshacer el ausente no admitía el sobreturno: con el horario tomado por otro turno, la recepción no podía forzarlo | `UndoNoShowAppointmentRequest`: `overbook: true` con `appointments.overbook` (sin el permiso, 403 `FORBIDDEN` en `authorize()`), como el alta: con el horario tomado entra con `is_overbooked: true`; sin él, 409 `APPOINTMENT_OVERLAP`. Un turno que ya era sobreturno vuelve sin mandar `overbook`, pero exige `appointments.overbook`, con el horario tomado o libre (si no, 403 `FORBIDDEN`, revisado con el turno bloqueado, después del estado y del día) | `UndoNoShowTest::test_with_overbook_a_taken_slot_comes_back_as_overbooked` y `test_without_the_overbook_permission` |
| 4 | Media | Sin los tests de medianoche pedidos; en deshacer el ausente, calcular el día en UTC no hacía fallar ningún test | 5 tests de medianoche (2 de fichar y 3 de deshacer el ausente) | `UndoNoShowTest::test_at_23_50_a_turn_of_20_00_is_undone` y `test_at_00_10_a_turn_of_yesterday_23_30_is_not_undone` |
| 5 | Baja | `AppointmentStatus::canTransitionTo()` e `isFinal()` sin uso ni tests (y `canTransitionTo(CONFIRMED)` desde un ausente daba `true` sin mirar el día) | Borrados | — |
| 6 | Baja | El documento reservaba R-052 para el cierre, que ya era el cambio de las skills de lectura | El cierre usa R-053 | — |
| 7 | Baja | Un sobreturno se restauraba sin el permiso del sobreturno | Cubierto por el 3 | `UndoNoShowTest::test_without_the_overbook_permission` |

Además, el usuario aceptó las dos decisiones de la corrección del hallazgo 3: un sobreturno vuelve sin mandar `overbook` pero pide el
permiso, y sin el permiso la respuesta es 403 `FORBIDDEN` (el genérico).

#### Validación del usuario (R-053)

El 2026-11-10, después de las correcciones de la revisión, el usuario corrió en Postman, con el Collection Runner y una iteración, la
colección `syncvet-fase-03-turnos` (58 requests): 107 de 107 pruebas, 0 errores. La colección suma a la de la 3.2a los pasos 24d a 24u,
24j1, 24j2 y 29a: la agenda del día de la corrida (de 00:00 a 23:55), dos turnos de hoy, confirmar y confirmar otra vez (422),
ausente antes de la hora (422), ausente, otro turno que toma el horario liberado, deshacer el ausente sin `overbook` (409) y con
`overbook: true` (200, sobreturno), fichar sin paciente (422), fichar, atendido por otra ficha (`vet-a`, 403 `APPOINTMENT_NOT_YOURS`)
y por la suya, fichar un atendido (422), cancelar sin motivo (422) y con motivo, mover un cancelado (422 `APPOINTMENT_NOT_EDITABLE`) e
inactivar la agenda del día. Suite: la del cierre.

### Entrega 3.3

Implementación en `feature/fase-03-03-recurrentes`, creada desde `feature/fase-03-02b-estados`, revisada en una sesión de Claude Code
aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-054)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-054)). La implementación quedó en el commit WIP `04789c9` y las correcciones en
`e4fe8c5`; el cierre va en un commit aparte, solo de documentación. Decisiones en [R-054](../../../01-arquitectura/decisiones/registro.md).

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `App\Enums\AppointmentCancelScope` (`single`, `following` y `series`); `App\Support\AppointmentRecurrence` (la cantidad y las fechas de la serie); la colección `syncvet-fase-03-series`; `tests/Feature/Appointments/Series` (7 clases) y `tests/Unit/AppointmentRecurrenceTest` |
| Cambiados | `AppointmentService` (`createSeries`; `lockForCreate`, `newAppointment` y `fit`, sin lanzar, salen del alta, que los usa igual; `ensureFits` usa `fit`); `AppointmentTransitionService::cancel` (alcance, una sola hora y la serie bloqueada por id); `AppointmentException::seriesConflict()`; `AppointmentController::store` (la serie: 201 con la lista y `meta.series_id`) y `AppointmentTransitionController::cancel` (`meta.cancelled_count`); `StoreAppointmentRequest` (`recurrence`), `CancelAppointmentRequest` (`scope`), `UpdateAppointmentRequest` (`recurrence` prohibido) y `AppointmentMessages` (reglas y mensajes de la serie); `lang/es/validation.php` (atributos); `InteractsWithAppointments` y `VoseoTest` ("reducir"); la colección `syncvet-fase-03-turnos` (`const data` → `body`, hallazgo 1) |
| Documentación | `CLAUDE.md` §1, §3 (bloqueos de la serie y de la cancelación con alcance), §4 (`APPOINTMENT_SERIES_CONFLICT`) y §6.10 (series y cancelación con alcance); la guía de Postman (series y Newman); este documento, el registro (R-054), el roadmap, los módulos y el handoff |

**Endpoints.** Sin rutas, permisos ni migraciones nuevas (76 rutas, 133 permisos, 36 migraciones): la serie va en `POST appointments`
(`recurrence`) y el alcance en `POST appointments/{appointment}/cancel` (`scope`).

**Tests.** Suite del 2026-11-11 sobre el código de `e4fe8c5` (el cierre solo cambia documentación), corrida en serie en SQLite
(`composer test`) y después en MySQL (`composer test:mysql`): 1363 casos con los *data providers*; en SQLite, 13681 aserciones con 14
omitidos (los 11 de MySQL de antes y los 3 de la serie); contra MySQL (`syncvet_test_mysql`), 13755 aserciones sin omitidos. Frente a
la 3.2b (1303 casos; 13373 y 13422 aserciones): 60 casos más (57 de `tests/Feature/Appointments/Series` y 3 de
`AppointmentRecurrenceTest`), 308 aserciones más en SQLite y 333 en MySQL. Antes de las correcciones eran 1347 casos (13610 y 13667
aserciones).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `AppointmentSeriesStoreTest` | 27 | 118 | Semanal por cantidad; cada 2 semanas hasta una fecha (la fecha de fin entra); la misma hora de reloj al cruzar el 15/10; el paciente, el servicio y los datos copiados a cada turno; hasta 52; `recurrence` inválida (16 casos → 422: `count` y `until` juntos o ninguno, más de 52, menos de 2, `until` antes del primero o a más de un año, una clave de más…); `until` con `every_weeks` (cada 2 semanas hasta el 15/11/2027, 27 turnos; hasta 7 días después, 422); el tope de un año con `count`; `every_weeks: null` es 1; `recurrence: null` da un turno único; el primer turno con las reglas del alta; el permiso del alta |
| `AppointmentSeriesConflictTest` | 7 | 26 | Una fecha ocupada y otra en un bloqueo → 409 `APPOINTMENT_SERIES_CONFLICT` con las dos en `errors.conflicts` y ningún turno creado; fuera del horario, todas las fechas en la lista; otra agenda del mismo veterinario cuenta; los turnos que liberan el horario no chocan; `overbook` vale para todas las fechas y marca solo las que se superponen, no saltea los bloqueos ni el horario y necesita el permiso |
| `AppointmentSeriesCancelTest` | 17 | 103 | `single` por defecto, `following` (no toca los anteriores; deja los fichados y los finales) y `series` (salvo lo que ya empezó); el turno pedido con las reglas de siempre y, si no admite cancelarse, nada cambia; otra serie no se toca; sin serie, cualquier alcance cancela uno; alcance inválido o sin motivo → 422; editar o mover uno no toca los demás (y `recurrence` en el `PATCH` → 422); el turno movido toma su lugar por fecha y uno movido antes no entra en `following`; el desempate por id en el mismo instante; una serie de otra clínica → 404 sin cambios; `scope: null` es `single`; una sola hora para toda la cancelación |
| `AppointmentSeriesLockTest` | 3 | 30 | La serie bloquea el veterinario y la agenda una sola vez antes de leer ninguna fecha; un error a mitad de la serie (el tercer `insert`) → 500, 0 turnos y 0 auditorías; cancelar la serie bloquea sus turnos antes de cambiarlos |
| `AppointmentSeriesConcurrencyTest` (MySQL) | 1 | — | Dos series simultáneas que comparten una fecha, en dos procesos: una entra entera y la otra recibe 409 con esa fecha |
| `AppointmentSeriesRetryTest` (MySQL) | 1 | — | Un *deadlock* en el tercer `insert` se reintenta y deja 4 turnos con un solo `series_id` y su auditoría |
| `AppointmentSeriesCancelConcurrencyTest` (MySQL) | 1 | — | `series` y `following` de la misma serie en dos procesos: 200 con `cancelled_count` 4 y 422 `APPOINTMENT_INVALID_TRANSITION`, nunca 500 |
| `AppointmentRecurrenceTest` (Unit) | 3 | 13 | El paso en semanas; la hora de reloj se mantiene con el cambio de desplazamiento (01/10/2023); `until` se incluye en la cantidad |

Las aserciones por clase son de SQLite; en MySQL suman 25 las tres clases solo de MySQL.

**Mutaciones** (las 5 críticas del diseño y 3 más; todas mueren con los tests de la serie): crear las fechas válidas aunque otra falle;
cortar la lista de conflictos en el primero; `following` que cancela también los anteriores; `following` que cancela los fichados; el
paso en días en lugar de semanas; `series` que cancela los que ya empezaron; `overbook` que no vale en la serie; `until` que excluye la
última fecha.

**Postman.** Colección aparte, `syncvet-fase-03-series` (más clara que sumar otra etapa a la de turnos, que ya tiene 58 pedidos).
`syncvet-fase-03-turnos` fallaba con Newman (también sin los cambios de la 3.3) porque 13 scripts declaraban `const data`, que choca
con la variable global `data` de Newman; en la app de Postman no pasaba. Corregido en la revisión (abajo).


**Desvíos del diseño** (precisiones del usuario al pedir la entrega, confirmadas en la revisión):

1. **`overbook` con `recurrence` se acepta** (el diseño lo prohibía con 422 `prohibited`): `overbook: true` vale para todas las
   fechas, con el permiso `appointments.overbook` (sin él, 403 como en el alta); saltea solo la superposición, no el horario ni los
   bloqueos. Cada turno queda con `is_overbooked` solo si al guardarlo se superponía.
2. **Un tercer alcance, `series`** (toda la serie), además de `single` y `following` (`App\Enums\AppointmentCancelScope`).
3. **Con `following` y `series`, de los demás turnos se cancelan solo los a confirmar o confirmados que todavía no empezaron**
   (`starts_at > now`); el diseño decía "a confirmar o confirmados", sin la condición de la hora. El turno pedido sigue las reglas de
   la cancelación de siempre (se cancela aunque ya haya empezado o esté fichado; si no admite cancelarse, 422
   `APPOINTMENT_INVALID_TRANSITION` y no cambia ningún turno).
4. **`meta.cancelled_count` en toda cancelación**, también con `single` (antes `meta` era `null`). El mensaje: "Turno cancelado." con
   uno y "Se cancelaron N turnos de la serie." con más.
5. **Mover la serie entera queda fuera del MVP** (lo decía el diseño): cada turno se mueve con el `PATCH` y conserva su `series_id`.
   `recurrence` en el `PATCH` → 422 (`prohibited`), "La serie se arma al dar los turnos: cada turno se modifica por separado.".

**Decisiones del usuario en la revisión (2026-11-10).** Se confirman los dos desvíos que contradicen el diseño: el 1 (`overbook`
con `recurrence`, para todas las fechas y con el permiso `appointments.overbook`) y el 2 (el alcance `series`). Se aceptan los desvíos
menores (3 a 5) y las decisiones de implementación de abajo. La falla de `syncvet-fase-03-turnos` con Newman (`const data`, ver
**Postman**) se corrige en esta entrega, en las correcciones de la revisión: renombrar la variable en todos los scripts de esa
colección y revisar las demás por el mismo problema.

**Decisiones de implementación** (no las definía el diseño):

* "Los siguientes" se ordenan por `starts_at` (y por id en el mismo instante), no por id: un turno movido a una fecha posterior entra
  en `following` desde un turno anterior, y uno movido antes, no.
* Con `until`, la serie tiene que dar de 2 a 52 turnos: `until` el mismo día del primero (un solo turno) → 422 en `recurrence.until`,
  "La serie tiene que tener al menos 2 turnos." (como `count`, de 2 a 52). `recurrence: null` da un turno único; una clave fuera de
  `every_weeks`, `count` y `until` → 422 en `recurrence`.
* **La última fecha, como mucho un año después del primer turno, también con `count`** (decisión del usuario en la revisión, hallazgo
  7): si no, 422 en `recurrence.count`, "La última fecha de la serie puede ser, como mucho, un año después del primer turno: reducí
  la cantidad de turnos o las semanas entre turnos." (antes, `count: 52` con `every_weeks: 12` llegaba a 2038).
* `recurrence.every_weeks: null` es 1 y `scope: null` es `single`, sus valores por defecto (hallazgo 8; el frontend manda el
  formulario completo).
* Toda la cancelación usa una sola hora (hallazgo 9): `cancelled_at` es la misma en todos los turnos y es la que decide cuáles ya
  empezaron.
* Las fechas se calculan en la hora de Asunción (`App\Support\AppointmentRecurrence`): `addDays(7 × every_weeks × i)` conserva la hora
  de reloj aunque cambie el desplazamiento (probado con el cambio de hora del 01/10/2023 en `AppointmentRecurrenceTest`).
* Las reglas que valen para toda la serie (la agenda activa con su veterinario activo, el servicio activo, el paciente, el día de hoy en
  adelante, la duración y la medianoche) responden con su error de siempre (422) sobre el primer turno; las de cada fecha (horario,
  bloqueos y superposición) van a `errors.conflicts`, una entrada por fecha con el primer motivo que falla (`outside_hours`,
  `in_block`, `overlap`, en ese orden), y la lista no se corta en la primera fecha.
* **El caso del diseño "una fecha ocupada, otra fuera del horario y otra en un bloqueo" no puede darse en una sola serie:** todas sus
  fechas caen el mismo día de la semana a la misma hora, así que el horario semanal las deja a todas adentro o a todas afuera. Los tests
  prueban una ocupada y otra bloqueada juntas, y fuera del horario con todas las fechas en la lista.
* La serie responde 201 con `AppointmentResource` (el del listado) y `meta.series_id`; el turno único sigue respondiendo
  `ShowAppointmentResource` sin `meta`.

#### Correcciones de la revisión (R-054)

Los 9 hallazgos de la revisión (2026-11-10), con las decisiones del usuario:

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | `syncvet-fase-03-turnos` falla con Newman (`const data`) | La variable se llama `body` en los 13 scripts (43 usos). Las otras 12 colecciones no declaran `data` ni otra variable global de Newman. La sección de Newman de `guia-postman.md` lo explica |
| 2 | `until` con `every_weeks` mayor que 1, sin test | `AppointmentSeriesStoreTest`: cada 2 semanas hasta el 15/11/2027 → 201 con 27 turnos; hasta 7 días después del primero → 422 "al menos 2 turnos" |
| 3 | Sin test de un error a mitad de la serie | `AppointmentSeriesLockTest`: una excepción en el tercer `insert` → 500, 0 turnos y 0 auditorías. `AppointmentSeriesRetryTest` (solo MySQL, sin la transacción de `RefreshDatabase`): un *deadlock* en el tercer `insert` se reintenta y deja 4 turnos con un solo `series_id` y su auditoría |
| 4 | Sin test de dos cancelaciones simultáneas | `AppointmentSeriesCancelConcurrencyTest` (solo MySQL): `series` desde el segundo turno y `following` desde el primero, en dos procesos, esperando la fila del primero: 200 con `cancelled_count` 4 y 422 `APPOINTMENT_INVALID_TRANSITION`, nunca 500; los cuatro cancelados una vez, con el motivo de la que entró |
| 5 | Desempate por id y turno movido antes, sin test | `AppointmentSeriesCancelTest`: dos turnos en el mismo instante (desde el de id menor, el otro entra; desde el mayor, no) y un turno movido a una fecha anterior (no entra en `following`) |
| 6 | Cancelar con alcance una serie de otra clínica, sin test | `AppointmentSeriesCancelTest`: `series` y `following` → 404 `RESOURCE_NOT_FOUND`, sin cambios en ninguna de las dos clínicas |
| 7 | Con `count`, la serie no tenía tope de fecha | La última fecha, como mucho un año después del primer turno (decisiones de implementación, arriba): `StoreAppointmentRequest`, `AppointmentMessages::RECURRENCE_COUNT_TOO_FAR` y "reducir" en `VoseoTest`. Tests: cada 4 semanas, 14 turnos (el 15/11/2027) entra y 15 no; cada 2 semanas 28 y cada 12 semanas 52 → 422 |
| 8 | `every_weeks: null` y `scope: null` → 422 | `nullable`, con su valor por defecto (1 y `single`). Tests en `AppointmentSeriesStoreTest` y `AppointmentSeriesCancelTest` |
| 9 | `cancelled_at` con una hora por turno | `now()` una vez por cancelación (`AppointmentTransitionService::cancel`). Test con un reloj que avanza un segundo en cada `now()`: los cinco turnos con el mismo `cancelled_at` |

**Mutaciones de la revisión** (todas mueren): la cuenta de `until` sin `every_weeks` (hallazgo 2); `count` sin el tope de un año (7);
`following` sin el desempate por id y con el desempate al revés (5); `now()` por turno al cancelar (9); la serie sin
`DB::transaction` (3); y, en MySQL, la cancelación de la serie sin `lockForUpdate` (4: los procesos no esperan la fila tomada).

**Newman** (2026-11-10, base de desarrollo, servidor en el puerto 8001, cada una dos veces seguidas sin recrear la base):
`syncvet-fase-03-turnos`, 58 pedidos y 107 aserciones, y `syncvet-fase-03-series`, 25 pedidos y 42 aserciones, sin fallas.

#### Validación del usuario (R-054)

El 2026-11-11, después de las correcciones de la revisión, el usuario corrió en Postman, en otra PC, con el Collection Runner y una
iteración: `syncvet-fase-03-series` (25 requests), 42 de 42 pruebas, 0 errores; `syncvet-fase-03-turnos` (58 requests), 107 de 107, 0
errores (también corre con Newman desde el hallazgo 1: `const data` → `body`); y, como regresión, `syncvet-fase-03-agendas`, 68 de 68, 0
errores. Suite: la del cierre (la última corrida en serie, en SQLite y en MySQL, sobre `e4fe8c5`).

### Entrega 3.4

Implementación en `feature/fase-03-04-disponibilidad`, creada desde `feature/fase-03-03-recurrentes`, revisada en una sesión de Claude
Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-055)) y validada por el
usuario ([validación del usuario](#validación-del-usuario-r-055)). La implementación quedó en el commit WIP `38ca91a` y las
correcciones en `4862658`; el cierre va en un commit aparte, solo de documentación. Decisiones en [R-055](../../../01-arquitectura/decisiones/registro.md).
Es la última entrega de la fase: el cierre de la FASE 3 (`composer audit`, el criterio de cierre, el módulo COMPLETADO y el merge a
`main`) va aparte.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `App\Services\ScheduleOccupancy` (las reglas del horario: el horario semanal, los bloqueos y la superposición, una sola vez para el alta, la serie, mover, deshacer el ausente y la disponibilidad); `ScheduleAvailabilityService` y `ScheduleAvailabilityRequest`; `AppointmentQueueService`, `AppointmentQueueRequest` y `QueueAppointmentResource`; la colección `syncvet-fase-03-disponibilidad`; `tests/Feature/Schedules/ScheduleAvailabilityTest` y `tests/Feature/Appointments/AppointmentQueueTest` |
| Cambiados | `AppointmentService` (las reglas del horario pasan a `ScheduleOccupancy`; `ensureScheduleIsAvailable`, con el campo del error, `ensureServiceIsAvailable`, `durationMinutes` y `timingProblem`, públicos para la disponibilidad; deshacer el ausente con `ScheduleOccupancy::loadAppointments`); `ScheduleController::availability` y `AppointmentController::queue`; `routes/api.php` (las dos rutas, la cola antes de `appointments/{appointment}`); `RolesAndPermissionsSeeder` y `tests/Fixtures/clinic-roles.json` (los dos permisos, a los tres roles); `lang/es/validation.php` (atributos `date` y `all`); `AppointmentLockTest`, `AppointmentSeriesLockTest` y `AppointmentTransitionLockTest` (reconocían el `exists` de la superposición; además, el alta lee solo los tramos del día y deshacer el ausente no lee tramos ni bloqueos) |
| Documentación | `CLAUDE.md` §1, §3 (`ScheduleOccupancy` y las lecturas después de los bloqueos), §4 (`APPOINTMENT_QUEUE_NO_BRANCH`) y §6.10 (disponibilidad y cola); la guía de Postman (la colección de disponibilidad); este documento, el registro (R-055), el roadmap, los módulos, el README y el handoff |

**Endpoints.** Dos rutas (76 → 78) y dos permisos (133 → 135), de los tres roles; sin migraciones (36).

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `schedules/{schedule}/availability` | `schedules.availability` | 200, `{schedule_id, duration_minutes, days}` | 404; 422 (`from`, `to`, `service_id`, `duration_minutes`; `schedule` con la agenda o su veterinario inactivos) |
| GET | `appointments/queue` | `appointments.queue` | 200, lista con `meta` (`date`, `branch_id`, `veterinarian_id`, `includes_unassigned`, `truncated`) | 422 `APPOINTMENT_QUEUE_NO_BRANCH`; 422 (`date`, `all`, `veterinarian_id`, `schedule_id`) |

**Tests.** Suite del 2026-11-11 sobre el código de `4862658` (el cierre solo cambia documentación), corrida en serie en SQLite
(`composer test`) y después en MySQL (`composer test:mysql`): 1395 casos con los *data providers*; en SQLite, 13960 aserciones con 14
omitidos (los de solo MySQL); contra MySQL (`syncvet_test_mysql`), 14034 aserciones sin omitidos. `composer test:fast`, 13958
aserciones, sin fallas. Frente a la 3.3 (1363 casos; 13681 y 13755 aserciones): 32 casos más (los de las dos clases nuevas) y 279
aserciones más en SQLite y en MySQL. Antes de las correcciones eran 1388 casos (13905 y 13979 aserciones).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `ScheduleAvailabilityTest` | 19 | 126 | Dos tramos en UTC; turnos que ocupan (también un sobreturno), un bloqueo, un cancelado y un ausente; el veterinario ocupado en otra agenda; tramos que se tocan y un hueco; la duración del servicio pedido o de la agenda; `duration_minutes`, que manda, y sus bordes (4, 5, 7, 720, 725, decimales y texto), sin saltear un servicio inactivo; hoy sin lo que ya empezó (09:30:00 sí, 09:30:01 no) y un horario de hoy que ya empezó, que el alta acepta; la medianoche de Asunción; varios días, un día sin tramos y uno pasado; el rango (más de 7 días, `to` antes de `from`, una fecha con hora); la agenda, su veterinario o el servicio inactivos (422); otra clínica (404); otra sucursal (200); sin permiso (403); la misma cantidad de consultas con un día y un turno que con 7 días de 00:00 a 23:55 cada 5 minutos, 41 turnos y dos bloqueos; y la garantía en tres grillas completas (con veterinario, con `duration_minutes` y el alta con `ends_at`, y sin veterinario): todo lo ofrecido se da (201) y cada candidato no ofrecido se rechaza con el `error_code` esperado |
| `AppointmentQueueTest` | 13 | 92 | La recepción ve toda la sucursal actual sin cancelados, con los filtros de veterinario, agenda y fecha; el veterinario ve su ficha y las agendas sin veterinario de su sucursal (no las de otra), con `all=1` toda la sucursal y con su `veterinarian_id` solo los suyos; sin ficha o con la ficha eliminada, toda la sucursal; la sucursal actual decide; sin sucursal actual, 422; los filtros validados; la forma de cada turno (especie, raza, `checked_in_at`, sobreturno, contacto); la medianoche; nada de otra clínica; sin permiso (403); sin N+1 (pacientes con especie y raza); 201 turnos, 200 con `truncated: true`, y 200 con `false`; dos turnos a la misma hora por id, también en el SQL |

Las aserciones por clase son de SQLite.

**Mutaciones** (todas mueren). De la implementación: no descontar los turnos (la disponibilidad con sobreturno), no descontar los
bloqueos, ofrecer los horarios que ya empezaron, la cola sin el filtro de sucursal, el veterinario sin el filtro de su ficha (las cinco
del diseño), la superposición sin el veterinario en sus otras agendas, las franjas pegadas no continuas, la cola con los cancelados y la
cola sin cargar la especie y la raza. De la revisión, abajo.

**Postman.** Colección aparte, `syncvet-fase-03-disponibilidad` (el diseño la llamaba `syncvet-fase-03-disponibilidad-y-cola`; el
pedido de implementación la nombró así): 50 requests y 81 pruebas (47 y 72 antes de las correcciones). Las dos rutas nuevas las tienen
los tres roles: no hay un 403 propio de la 3.4 con los roles de la demo; la colección prueba el 403 del veterinario que intenta dar un
horario ofrecido, y `RoleAccessTest` el de un rol sin el permiso. El usuario sin sucursal actual y más de 200 turnos los cubre
`AppointmentQueueTest`.

**Desvíos del diseño** (por el pedido de implementación o porque el diseño no lo decía; aceptados en la revisión; el 3 y el 10,
ajustados por las decisiones D1 y D3):

| # | Tema | Diseño | Implementado | Motivo |
|---|---|---|---|---|
| 1 | Reglas compartidas | "Las mismas reglas que el alta" | Clase nueva `App\Services\ScheduleOccupancy`: el horario semanal (franjas pegadas como una), los bloqueos y la superposición en la agenda y del veterinario en sus otras agendas. La usan el alta, la serie, mover, deshacer el ausente y la disponibilidad; `AppointmentService` expone `ensureScheduleIsAvailable` (con el campo del error), `ensureServiceIsAvailable`, `durationMinutes` y `timingProblem`, que la disponibilidad reutiliza | El pedido exige reutilizar el código del alta, no copiarlo. El alta lee las filas de bloqueos y turnos de la ventana del turno (`select starts_at, ends_at`) en lugar de un `exists`, con las mismas condiciones; `AppointmentTransitionLockTest` y `AppointmentSeriesLockTest` reconocían el `exists` y se ajustaron |
| 2 | Filtro de agenda en la cola | `date`, `all`, `veterinarian_id` | Suma `schedule_id` (una agenda de la clínica) | El pedido lo nombra entre los filtros |
| 3 | `meta` de la cola | No lo dice | `meta.date`, `meta.branch_id`, `meta.veterinarian_id` (el veterinario por el que se filtró: el de la ficha por defecto, `null` con `all=1`), `meta.includes_unassigned` (`true` solo en la vista por defecto de una ficha, que suma las agendas sin veterinario; D1) y `meta.truncated` (D3) | Para que el frontend muestre qué cola está viendo |
| 4 | `veterinarian_id` y `all` juntos | No lo dice | `veterinarian_id` manda sobre la ficha y sobre `all`: solo los de ese veterinario, sin las agendas sin veterinario | Es una vista (pregunta 25) |
| 5 | Veterinario inactivo o servicio inactivo | Solo "una agenda inactiva → 422 en `schedule`" | Agenda con el veterinario inactivo → 422 en `schedule` ("El veterinario de la agenda está inactivo."); servicio pedido, o el de la agenda, inactivo o eliminado → 422 en `service_id` (también con `duration_minutes`) | Lo rechazaría el alta: ofrecer horarios que el alta rechaza rompería la garantía |
| 6 | Un día anterior a hoy | No lo dice | 200 con `slots` vacío (no un 422) | El alta lo rechaza (pregunta 16); se resuelve con la misma regla (`timingProblem`) |
| 7 | "Ya empezó" | "Hoy, desde ahora" | Se ofrece si `starts_at >= ahora` (a las 09:30:00 en punto se ofrece el de las 09:30; a las 09:30:01, no). El alta sí acepta un horario de hoy que ya empezó: la garantía "todo candidato no ofrecido se rechaza" vale desde ahora | Precisión |
| 8 | `from`/`to` y `date` | "Una fecha" | Solo `Y-m-d`: una fecha con hora → 422 ("Ingresá una fecha (2026-11-09).") | Precisión |
| 9 | Nombre de la colección | `syncvet-fase-03-disponibilidad-y-cola` | `syncvet-fase-03-disponibilidad` | El pedido de implementación lo nombra así |
| 10 | Más de 200 turnos en la cola | "Hasta 200" | Corta en 200 y lo avisa con `meta.truncated` (D3) | Decisión del usuario |
| 11 | Agenda de otra sucursal | No lo dice | La disponibilidad de una agenda de otra sucursal que la actual del usuario se consulta (200) | Coherente con el alta, que da turnos en cualquier agenda de la clínica (hallazgo H11, aceptado) |

**Decisiones del usuario en la revisión (2026-11-11)**, las respuestas a las tres preguntas de la implementación:

| # | Decisión | Implementación |
|---|---|---|
| D1 | Con ficha de veterinario y sin `veterinarian_id`, la cola muestra sus turnos y los de las agendas sin veterinario (`veterinarian_id IS NULL`) de su sucursal actual. Con `veterinarian_id`, solo ese | `AppointmentQueueService::forUser` (`orWhereNull` solo en la vista por defecto de una ficha); `meta.includes_unassigned` |
| D2 | La disponibilidad acepta `duration_minutes` opcional (entero de 5 a 720 en múltiplos de 5), que manda sobre la duración del servicio y `slot_minutes` y sale en `data.duration_minutes`. Si llega `service_id`, se sigue revisando que esté activo | `ScheduleAvailabilityRequest` (`nullable`, `integer`, `between:5,720` y `multiple_of:5`, un solo mensaje: "Ingresá una duración de 5 a 720 minutos, en múltiplos de 5.") y `ScheduleAvailabilityService::days` |
| D3 | La cola sigue cortando en 200 pero avisa con `meta.truncated` (`true` si cortó, `false` si no) | Trae `LIMIT + 1` filas y devuelve las primeras 200 |

Los demás desvíos se aceptan, y también H11 (desvío 11).

#### Correcciones de la revisión (R-055)

La revisión (2026-11-11) confirmó que la extracción a `ScheduleOccupancy` no cambió ninguna regla del alta (comparada con la versión de
la 3.3), que las lecturas siguen después de los bloqueos y que los tests de orden de bloqueo ajustados prueban lo mismo. Hallazgos y
correcciones:

| # | Gravedad | Hallazgo | Corrección | Tests |
|---|---|---|---|---|
| D1–D3 | Alta | Las tres preguntas de la implementación sin resolver | Las decisiones de arriba | `AppointmentQueueTest` (una agenda solo de servicio en la sucursal y otra en otra sucursal; el propio `veterinarian_id`), `test_the_requested_duration_wins_and_is_validated` y `test_at_most_the_limit_and_it_warns_when_it_cuts` |
| H4 | Media | El tope de 200 sin test real: quitar el `limit` pasaba | Test con 201 turnos (corta, `truncated: true`, los primeros 200 por hora e id) y con 200 (`false`) | `test_at_most_the_limit_and_it_warns_when_it_cuts` |
| H5 | Media | El orden con turnos a la misma hora sin test | Test con dos turnos a las 09:00 (uno sobreturno) en el que el cargado primero tiene el id mayor. Con el índice `(branch_id, starts_at)`, SQLite y MySQL devuelven los empates por id aunque la consulta no lo pida: quitar `orderBy('id')` seguía pasando, así que el test exige además el `order by starts_at, id` en la consulta | `test_appointments_at_the_same_time_are_ordered_by_id` |
| H6 | Media | El rendimiento de la disponibilidad sin test (medido: 8 consultas fijas, unos 360 ms para 7 días de 00:00 a 23:55 cada 5 minutos con 60 turnos) | Test que cuenta las consultas con un día y un turno y con 7 días, 41 turnos y dos bloqueos (más de 1800 horarios): la misma cantidad. `ScheduleOccupancy::fit` calcula el texto local del horario una sola vez | `test_the_number_of_queries_does_not_grow_with_the_slots_or_the_appointments` |
| H7 | Media | El test de N+1 tenía pacientes sin raza (con la clave nula, Laravel no consulta la relación): quitar `patient.breed` pasaba | Los pacientes del test con especie y raza | `test_the_number_of_queries_does_not_grow_with_the_appointments` |
| H8 | Media | La grilla completa con un solo escenario y cualquier 409 o 422 como rechazo | Tres grillas completas que comparan el `error_code` de cada horario rechazado (`APPOINTMENT_OUTSIDE_HOURS`, `APPOINTMENT_IN_BLOCK`, `APPOINTMENT_OVERLAP`) y exigen que lo esperado cubra toda la grilla: con veterinario (con un sobreturno existente, que ocupa, y un ausente, que no), con `duration_minutes` (el alta con `ends_at`) y en una agenda sin veterinario (otra agenda sin veterinario a la misma hora no cuenta). El horario de hoy que ya empezó, en su propio test (desvío 7) | `test_every_offered_slot_is_booked_and_every_other_candidate_is_rejected_for_its_reason`, `test_with_its_own_duration_every_offered_slot_is_booked_with_ends_at`, `test_in_a_schedule_without_veterinarian_every_offered_slot_is_booked` y `test_today_a_slot_that_already_started_is_not_offered_but_the_booking_accepts_it` |
| H9 | Baja | Deshacer el ausente leía los tramos y los bloqueos sin usarlos, con el turno bloqueado | `ScheduleOccupancy::loadAppointments` (solo los turnos) | `AppointmentTransitionLockTest`: deshacer el ausente no lee `schedule_hours` ni `schedule_blocks` |
| H10 | Baja | El alta leía los tramos de toda la semana | `load()` lee los tramos de los días de la semana de la ventana (el alta, uno) | `AppointmentLockTest`: la lectura de los tramos lleva `weekday in (…)` |
| H11 | Baja | La disponibilidad de una agenda de otra sucursal, sin test | Se acepta (desvío 11) | `test_a_schedule_of_another_branch_than_the_current_one_is_available` |

**Mutaciones de la revisión** (todas mueren): la cola sin `patient.breed` (H7), sin `orderBy('id')` (H5), sin el `+ 1` (D3), sin
`orWhereNull` y con `includes_unassigned` siempre en `false` (D1); la disponibilidad que ignora `duration_minutes` (D2), con una
consulta por horario (H6), sin el filtro de estado de los turnos (cuenta el cancelado y el ausente), sin las otras agendas del
veterinario, sin los bloqueos y con los tramos de otro día (H10); deshacer el ausente con `load()` (H9).

**Postman de la revisión.** La colección suma la disponibilidad con `duration_minutes=90`, que manda sobre el servicio de 60 (solo
11:30), y con `duration_minutes=7` (422); la cola por defecto del veterinario con el turno de la agenda sin veterinario,
`includes_unassigned: true` y ningún turno de otro veterinario; la cola con su propio `veterinarian_id` (sin la agenda sin
veterinario); y `meta.truncated: false` en la del veterinario y la de la recepción. **Newman** (2026-11-11, base de desarrollo,
servidor en el puerto 8001, dos veces seguidas sin recrear la base): 50 pedidos y 81 aserciones, sin fallas.

#### Validación del usuario (R-055)

El 2026-11-11, después de las correcciones de la revisión, el usuario corrió en Postman, con el Collection Runner y una iteración:
`syncvet-fase-03-disponibilidad` (50 requests), 81 de 81 pruebas, 0 errores. Suite: la del cierre (la última corrida en serie, en
SQLite y en MySQL, sobre `4862658`).

## Cierre de la FASE 3

Completada el 2026-11-11 (R-056). La recepción da turnos sin superposiciones y el veterinario ve su cola del día: agendas con su
horario semanal y sus bloqueos (baja física solo sin turnos), turnos únicos con paciente, con cliente o con un contacto (dentro del
horario, fuera de los bloqueos, sin superponerse en la agenda ni con otro turno del mismo veterinario, con sobreturno explícito), sus
estados (confirmar, fichar, atendido, ausente y deshacerlo el mismo día, cancelar con motivo), las series semanales (todas las fechas
o ninguna; cancelar uno, los siguientes o toda la serie), la disponibilidad de cada agenda (los horarios que el alta aceptaría, con
las mismas reglas en `ScheduleOccupancy`) y la cola del día de la sucursal actual; antes, la zona fija de la sesión de MySQL (deuda
16). Las ramas se encadenaron (`docs/fase-03-diseno` → `feature/fase-03-00-zona-mysql` → `feature/fase-03-01-agendas` →
`feature/fase-03-02a-turnos` → `feature/fase-03-02b-estados` → `feature/fase-03-03-recurrentes` →
`feature/fase-03-04-disponibilidad`), todas en GitHub, y se integran a `main` con un merge `--no-ff` desde
`feature/fase-03-04-disponibilidad`, autorizado por el usuario (R-056). Las ramas de la fase se conservan.

| Entrega | Contenido | Commits | Suite al cerrar |
|---|---|---|---|
| Diseño | Análisis, preguntas 1 a 25 y entregas | `e510d73` (R-047), `c1778c0` (R-048) | — |
| [3.0](#entrega-30) | Zona de la sesión de MySQL (deuda 16), sin API | `7c92097`, `a0f9a4e` (cierre `5475048`) | 1007 casos; 10851 aserciones en SQLite y 10885 en MySQL |
| [3.1](#entrega-31) | Agendas: ABM, horario semanal y bloqueos; los pendientes de Postman de la FASE 2 | `aa96fed` (correcciones y cierre `2c0448c`) | 1108 casos; 11845 y 11879 |
| [3.2a](#entrega-32a) | Turnos únicos: alta, edición y mover, sin superposición; sobreturno | `fa03b6f`, `b0dd969` (cierre `7fb853d`) | 1212 casos; 12835 y 12879 |
| [3.2b](#entrega-32b) | Estados del turno | `a1a1ab4`, `d811fa1` (cierre `6fbe3c1`; en la rama, `964db22`, R-052) | 1303 casos; 13373 y 13422 |
| [3.3](#entrega-33) | Turnos recurrentes y cancelar con alcance | `04789c9`, `e4fe8c5` (cierre `615c015`) | 1363 casos; 13681 y 13755 |
| [3.4](#entrega-34) | Disponibilidad por agenda y cola del día | `38ca91a`, `4862658` (cierre `fe8553d`) | 1395 casos; 13960 y 14034 |

**Rutas, permisos y migraciones.** Las rutas de la API pasan de 58 a 78 (20 nuevas): agendas y bloqueos (8, 3.1), turnos (4, 3.2a),
estados del turno (6, 3.2b), y la disponibilidad y la cola del día (2, 3.4); la 3.3 va en rutas existentes. Los permisos, de 127 a 135
(`schedule-blocks.index`, `.store` y `.destroy`, `appointments.overbook`, `.attend`, `.no-show` y `.queue`, y `schedules.availability`).
Las migraciones, de 34 a 36 (el contacto y el sobreturno de los turnos, 3.2a; `attended_at` y `no_show_at`, 3.2b). Los `error_code`
nuevos (`SCHEDULE_*`, `APPOINTMENT_*` y el `CONFLICT` de agendas y turnos) están en `CLAUDE.md` §4.

**Suite al cerrar la fase** (2026-11-11, código de `4862658`; los commits posteriores solo cambian documentación): 1395 casos con los
*data providers*, en verde. SQLite: 13960 aserciones, 14 omitidos (solo de MySQL). MySQL (`syncvet_test_mysql`): 14034 aserciones, sin
omitidos. Frente al cierre de la FASE 2 (988 casos; 10816 y 10836 aserciones): 407 casos más. La corrida en `main` después del merge,
antes del push, la informa el cierre (R-056).

**Colecciones de Postman** (`docs/05-pruebas/postman`; se corren más de una vez sin recrear la base, [guía](../../../05-pruebas/guia-postman.md)).
Regresión del cierre (2026-11-11, Newman, base de desarrollo, servidor en el puerto 8001): las cuatro de la FASE 3 y las de
veterinarios y servicios de la FASE 2, sin fallas.

| Colección | Entrega | Requests | Pruebas | Validación del usuario | Newman del cierre |
|---|---|---|---|---|---|
| Agendas | 3.1 | 35 | 68 | 2026-11-10, en verde; regresión el 2026-11-11 | 35 y 68, sin fallas |
| Turnos | 3.2a y 3.2b | 58 | 107 | 2026-11-10 (3.2a: 37 requests y 67 pruebas; 3.2b), en verde | 58 y 107, sin fallas |
| Series | 3.3 | 25 | 42 | 2026-11-11, en verde | 25 y 42, sin fallas |
| Disponibilidad y cola | 3.4 | 50 | 81 | 2026-11-11, en verde | 50 y 81, sin fallas |
| Veterinarios (FASE 2) | 2.3 | 32 | 75 | 2026-11-09 | 32 y 75, sin fallas |
| Servicios (FASE 2) | 2.4 | 27 | 64 | 2026-11-09 | 27 y 64, sin fallas |

**Criterio de cierre**, punto por punto:

| Criterio | Evidencia |
|---|---|
| Dos turnos simultáneos en el mismo horario: uno entra y el otro recibe 409 | `AppointmentConcurrencyTest` (solo MySQL): dos altas en dos procesos de PHP aparte, con una agenda con veterinario y con una agenda solo de servicio; en los dos casos, un 201 y un 409 `APPOINTMENT_OVERLAP`. Además, dos transiciones simultáneas (`AppointmentTransitionConcurrencyTest`), dos series que comparten una fecha y dos cancelaciones de la misma serie. Corridos otra vez en el cierre, en MySQL, junto con las transiciones: 55 tests y 263 aserciones, sin omitidos |
| Tests de cada transición, válida e inválida | `AppointmentTransitionTest`: cada transición válida desde cada estado de origen y la matriz de las inválidas (también repetir la misma); deshacer el ausente en `UndoNoShowTest`; las reglas propias en `AppointmentCheckInTest`, `AppointmentAttendTest` y `AppointmentNoShowAndCancelTest` |
| Cada regla con su test con el rol de la operación | `tests/Feature/Schedules` y `tests/Feature/Appointments` (con `Transitions` y `Series`), con un administrador real y los roles propios en los 403; las protecciones críticas, quitadas de a una en la implementación y en la revisión de cada entrega (mutaciones de cada resultado) |
| Cada ruta nueva con su acceso por rol y su aislamiento por clínica | `RoleAccessTest` prueba el acceso por rol de las 78 rutas; el 404 de un registro de otra clínica, en los tests de cada módulo y en `RouteBindingTest`; la cola del día sin turnos de otra clínica |
| La deuda 16 resuelta | Entrega 3.0 (R-049) |
| Los pendientes de Postman de la FASE 2 probados | `syncvet-fase-03-agendas`, pedidos 21 a 23 (R-050); la guía de veterinarios y servicios los apunta ahí |
| Suite en verde en SQLite y en MySQL | La suite del cierre, en las dos bases, una después de la otra |
| `composer audit` sin avisos | 2026-11-11: "No security vulnerability advisories found."; nada que actualizar |

**Deudas** ([`deuda-tecnica.md`](../../deuda-tecnica.md)). Resuelta en la fase: 16 (la zona de la sesión de MySQL, R-049). Nueva:
17 (el tope de 2038 de las columnas `timestamp`, R-047; prioridad baja). La 2, cumplida en los modelos de la fase (`Schedule`,
`ScheduleHour` y `ScheduleBlock` en la 3.1; `Appointment` en la 3.2a y la 3.2b) y abierta para los demás. Abiertas al cerrar: 2, 3
(códigos SIFEN sin verificar), 5 (sin claves foráneas compuestas), 6 (límite del login en ráfagas con la caché `database`), 15 (foto
con un `photo_path` fuera de su carpeta) y 17.

**Decisiones de la fase.** R-047 (análisis, preguntas de negocio y solución de la deuda 16), R-048 (aprobación del diseño, preguntas 13
a 25), R-049 (3.0), R-050 (3.1), R-051 (3.2a), R-052 (las skills de lectura, sin relación con la API), R-053 (3.2b), R-054 (3.3), R-055
(3.4) y R-056 (cierre y merge), en el [registro](../../../01-arquitectura/decisiones/registro.md). Las que cambian reglas de fases anteriores: los turnos son
uso del cliente, con el mensaje nuevo de `CLIENT_IN_USE`, y las bajas del cliente y del paciente revisan el uso con su fila bloqueada
(pregunta 21, R-051); el cambio de dueño del paciente en una transacción con el paciente y los dos clientes bloqueados (R-051); la zona
fija de la sesión de MySQL y el reloj de MySQL prohibido en código nuevo (R-049); la baja física de las agendas, excepción a "datos
maestros con `SoftDeletes`" (pregunta 13, R-048).

**Pendiente para la FASE 4** ([historia clínica](fase-04-historia-clinica.md)):

* La relación entre "atendido" y la consulta, que decide el diseño de la FASE 4. La propuesta está en
  [Relación con la consulta](fase-03-agenda-y-turnos.md#relación-con-la-consulta-fase-4): la consulta se abre desde un turno fichado
  (`consultations.appointment_id`, única) y lo marca atendido en la misma transacción; `POST appointments/{appointment}/attend` sigue
  para los turnos sin historia clínica; un turno atendido no se cancela y cancelar la consulta no lo vuelve a fichado; la consulta sin
  turno (urgencia) sigue siendo posible. El orden de bloqueo de esa transacción se suma al de la FASE 3 (`CLAUDE.md` §3).
* El veterinario y el servicio que use la consulta, con un `sharedLock` (como los turnos, `CLAUDE.md` §3).
* Fuera del MVP o para más adelante, sin fase asignada: el ausente automático (P12), la reserva online (`online_booking` y
  `AppointmentSource::ONLINE`) y mover una serie entera (R-054).
