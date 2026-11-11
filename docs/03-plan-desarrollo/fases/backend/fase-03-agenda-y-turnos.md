# FASE 3 — Agenda y turnos

Etapa: Atención. Estado: **COMPLETADO** el 2026-11-11 (R-056): entregas 3.0 (R-049), 3.1 (R-050), 3.2a (R-051), 3.2b (R-053), 3.3
(R-054) y 3.4 (R-055) cerradas; diseño aprobado por el usuario el 2026-11-10, con las preguntas de negocio 1 a 12 (R-047) y las
[preguntas del diseño](#preguntas-del-diseño) 13 a 25 respondidas (R-048). Ver el [cierre de la fase](#cierre-de-la-fase-3). El
estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

La recepción da turnos sin superposiciones, el veterinario ve su cola del día, y antes se resuelve la deuda 16 (la zona de las
columnas `timestamp` de MySQL), porque esta es la primera fase que guarda fechas con hora en volumen.

## Entregas

El alcance inicial tenía cinco entregas (3.1 a 3.5). El análisis suma la 3.0 (deuda 16), divide los turnos en dos (3.2a y 3.2b), con
los estados antes que la recurrencia para que la serie use las transiciones ya probadas, y junta la disponibilidad y la cola del día
(R-047). Cada entrega recorre los pasos 4 a 7 y deja la suite en verde.

| Entrega | Contenido | Antes era | Estado |
|---|---|---|---|
| 3.0 | Deuda 16: zona fija de la sesión de MySQL, sin migraciones | — | COMPLETADO ([resultado](#entrega-30), R-049) |
| 3.1 | Agendas: ABM, horario semanal y bloqueos; los pendientes de Postman de la FASE 2 | 3.1 | COMPLETADO ([resultado](#entrega-31), R-050) |
| 3.2a | Turnos únicos: alta, edición y mover, sin superposición, con el horario y los bloqueos; sobreturno | 3.2 | COMPLETADO ([resultado](#entrega-32a), R-051) |
| 3.2b | Estados: confirmar, fichar, atendido, ausente (y deshacerlo el mismo día) y cancelar | 3.3 | COMPLETADO ([resultado](#entrega-32b), R-053) |
| 3.3 | Turnos recurrentes: la serie entera o nada; cancelar uno o los siguientes | 3.2 | COMPLETADO ([resultado](#entrega-33), R-054) |
| 3.4 | Disponibilidad por agenda y cola del día | 3.4 y 3.5 | COMPLETADO ([resultado](#entrega-34), R-055) |

**Pendiente de la FASE 2 (R-037).** La colección de Postman de veterinarios (`syncvet-fase-02-veterinarios`) no prueba la baja de un
veterinario con uso (409 `VETERINARIAN_IN_USE`): la API todavía no crea agendas. La cubren los tests (`VeterinarianDeleteTest`). Va en
la colección de la 3.1 (una agenda es uso), sin tocar el `DemoSeeder`.

**Pendiente de la FASE 2 (entrega 2.4).** La colección de Postman de servicios (`syncvet-fase-02-servicios`) tampoco prueba un
servicio usado: la baja (409 `SERVICE_IN_USE`) ni el cambio de nombre (422 `SERVICE_NAME_NOT_EDITABLE`). Los cubren los tests
(`ServiceDeleteTest` y `ServiceUpdateTest`). Van en la colección de la 3.1, con un servicio usado por una agenda.

## Preguntas de negocio

Las cuatro del alcance inicial (P1 a P4) y las que sumó el análisis, respondidas por el usuario el 2026-11-10 (R-047).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| P1 | D3: ¿cómo se generan los turnos recurrentes? | Todos al crear la serie: semanal o cada N semanas, con fecha de fin o cantidad, hasta 52, agrupados por `series_id`. Si una fecha choca con otro turno, un bloqueo o el horario, se rechaza la serie entera con la lista de fechas en conflicto | 2026-11-10 |
| P2 | ¿Se permite sobreturno? | Solo explícito (`overbook: true`), con un permiso propio que tienen el administrador y la recepción; columna `is_overbooked`. Permite superponerse con otro turno, pero no salir del horario ni entrar en un bloqueo | 2026-11-10 |
| P3 | ¿Con cuánta anticipación se cancela? | Sin límite; el motivo es obligatorio | 2026-11-10 |
| P4 | ¿Se da turno sin paciente (primera vez)? | Sí: con paciente, con un cliente sin paciente, o con un nombre y un teléfono de contacto (columnas nuevas). Para fichar hace falta el paciente | 2026-11-10 |
| P5 | ¿Cuánto dura un turno? | `ends_at` por defecto: `starts_at` más la duración del servicio, o `slot_minutes` de la agenda si no hay servicio; editable | 2026-11-10 |
| P6 | ¿Turnos fuera del horario o en un bloqueo? | No: 422 siempre, sin excepción en el MVP (tampoco con sobreturno) | 2026-11-10 |
| P7 | ¿Quién mueve o cancela un turno? | El administrador y la recepción; el veterinario ficha y marca atendido. Un turno fichado, atendido, ausente o cancelado no se mueve | 2026-11-10 |
| P8 | ¿Qué ve el veterinario? | La cola del día de sus agendas en su sucursal actual; la recepción, la de toda la sucursal | 2026-11-10 |
| P9 | ¿Quién arma las agendas? | Solo el administrador | 2026-11-10 |
| P10 | ¿De quién es una agenda? | De un veterinario, de un servicio o de los dos (al menos uno). Un veterinario o un servicio inactivo no recibe agendas nuevas | 2026-11-10 |
| P11 | ¿Cómo se da de baja una agenda? | Se inactiva siempre y se elimina solo sin turnos. `Schedule` y `ScheduleHour` pasan a ser auditables | 2026-11-10 |
| P12 | ¿Cuándo un turno pasa a ausente? | Lo marca la recepción a mano; el automático queda para más adelante | 2026-11-10 |
| — | Deuda 16 | `'timezone' => env('DB_TIMEZONE', '-03:00')` en la conexión `mysql`, sin migraciones; las horas de negocio las pone PHP; columnas nuevas de hora de negocio en `datetime`; el tope de 2038 de `timestamp`, deuda de prioridad baja (17). Entrega 3.0 | 2026-11-10 |

Las preguntas que deja el diseño están al final, en [Preguntas del diseño](#preguntas-del-diseño).

## Regla de oro aplicada

Análisis con [`docs/00-vision/principios.md`](../../../00-vision/principios.md).

| Pregunta | Agenda (horario semanal) | Bloqueo | Turno |
|---|---|---|---|
| ¿Quién la crea? | Administrador (P9) | Administrador | Administrador y recepción; el sobreturno, con su permiso (P2) |
| ¿Quién la modifica? | Administrador | No se modifica: se elimina y se crea otro | Administrador y recepción (mover, datos); el veterinario ficha y marca atendido (P7) |
| ¿Quién la usa? | Turnos, disponibilidad, cola del día | Turnos y disponibilidad | Cola del día; la consulta de la FASE 4; recordatorios (FASE 7); reportes de ausentismo (FASE 10) |
| Estados | Activa / inactiva | — | A confirmar → confirmado → fichado → atendido; ausente; cancelado (`AppointmentStatus`) |
| ¿Qué depende de ella? | Sus horarios, bloqueos y turnos | — | La consulta (`consultations.appointment_id`, única) |
| Impacto económico | Ninguno directo | Ninguno | Ninguno directo: el turno no se factura; se factura la consulta (FASE 8). Guarda el servicio, no su precio |
| ¿Qué pasa si se cancela? | Inactiva: no recibe turnos nuevos; los que tiene siguen | — | Libera el horario; queda con motivo, quién y cuándo |
| ¿Qué pasa si se elimina? | Solo sin turnos (P11), con sus horarios y bloqueos | Se elimina (no es un documento); queda en la auditoría | No se elimina: se cancela |
| Permisos | `schedules.*` (existen) y `schedule-blocks.*` (nuevos) | `schedule-blocks.*` | `appointments.*` (existen) y cinco nuevos |
| Consulta, stock, facturación | — | — | Un turno fichado es el punto de partida de la consulta ([relación con la FASE 4](#relación-con-la-consulta-fase-4)); sin stock ni factura propios |

## Decisiones comunes

| Tema | Decisión |
|---|---|
| Permisos | Existen `schedules.*` y `appointments.index/store/show/update/confirm/check-in/cancel`. Nuevos: `schedule-blocks.index`, `schedule-blocks.store` y `schedule-blocks.destroy` (3.1), `appointments.overbook` (3.2a), `appointments.attend` y `appointments.no-show` (3.2b), y `schedules.availability` y `appointments.queue` (3.4): **127 → 135**. Asignación en la [tabla de permisos](#permisos-por-rol) |
| Rutas | Hoy 58. Suman 8 (3.1), 4 (3.2a), 6 (3.2b, con deshacer el ausente, pregunta 19), 0 (3.3: la serie va en el alta) y 2 (3.4): **78** al cerrar la fase |
| Migraciones | Dos nuevas: los datos de contacto, el sobreturno y un índice de turnos (3.2a), y `attended_at` y `no_show_at` (3.2b). **34 → 36**. La 3.0 y la 3.1 no tienen migraciones |
| Fechas con hora | Instantes (`starts_at`, `ends_at`, `confirmed_at`…) con `AppDateTime`: llegan con zona o en hora de Asunción y salen en UTC (`Z`). Los horarios semanales (`schedule_hours`) son **hora de reloj de Asunción** (`"09:00"`, sin zona): no son un instante. Los filtros por día usan `ApiDateTime::startOf`/`endOf` |
| Grilla | Horas en múltiplos de 5 minutos y sin segundos (horarios, turnos y duración), como la duración de los servicios (pregunta 22 de la FASE 2). Un turno no cruza la medianoche |
| Superposición | Dos turnos se superponen si `a.starts_at < b.ends_at` y `a.ends_at > b.starts_at`: uno que termina a las 09:30 y otro que empieza a las 09:30 no chocan. Ocupan el horario los turnos a confirmar, confirmados, fichados y atendidos; un cancelado o un ausente lo liberan |
| Veterinario y sucursal del turno | Salen de la agenda: `branch_id` y `veterinarian_id` se copian al crear o mover el turno y son `prohibited` en el cuerpo. Se guardan en el turno para la cola del día y los reportes sin unir la agenda, y porque una agenda con turnos no cambia de sucursal ni de veterinario ([pregunta 14](#preguntas-del-diseño)) |
| Reserva online | Fuera del MVP. `schedules.online_booking` no forma parte de la API (no se acepta y no se muestra; la columna queda en `false`), como `custom_fields` (R-015). `AppointmentSource::ONLINE` queda en el Enum para el futuro, pero la API la rechaza (422 en `source`); se aceptan `reception` (por defecto), `phone` y `whatsapp` |
| Auditoría | `Schedule`, `ScheduleHour` y `ScheduleBlock` suman `Auditable` (P11; el bloqueo, [pregunta 23](#preguntas-del-diseño)); `Appointment` ya lo tiene. Cada cambio de estado queda como `updated` con el estado anterior y el nuevo. Los tipos nuevos van en `AuditLog::AUDITABLE_TYPES` (`AuditableTypesTest`) |
| Deuda 2 | Cada entrega revisa las relaciones y los casts de los modelos que usa (`Schedule`, `ScheduleHour`, `ScheduleBlock` en la 3.1; `Appointment` en la 3.2a) y lo anota en su resultado |
| Deuda 5 | Sin claves foráneas compuestas: cada referencia (agenda, veterinario, servicio, sucursal, paciente, cliente) se valida por clínica y tiene su test de aislamiento (`tests/Feature/Tenancy`) |
| Excepciones | `ScheduleException` (nueva) para agendas y bloqueos; `AppointmentException` (existe, vacía) para turnos. Los verbos nuevos de los mensajes (elegir, cargar, marcar, inactivar, crear…) se suman a la lista de `VoseoTest` |
| Orden de bloqueo | Uno solo para toda la fase, en el [orden de bloqueo](#orden-de-bloqueo): clínica → veterinario → servicio → agenda → paciente → turno. Se suma a `CLAUDE.md` §3 |

### Permisos por rol

| Permiso | Administrador | Recepcionista | Veterinario |
|---|---|---|---|
| `schedules.index`, `schedules.show` (existen) | Sí | Sí | Sí |
| `schedules.store`, `schedules.update`, `schedules.destroy` (existen) | Sí | — | — |
| `schedule-blocks.index` (nuevo) | Sí | Sí | Sí |
| `schedule-blocks.store`, `schedule-blocks.destroy` (nuevos) | Sí | — | — |
| `schedules.availability` (nuevo) | Sí | Sí | Sí |
| `appointments.index`, `appointments.show` (existen) | Sí | Sí | Sí |
| `appointments.store`, `appointments.update`, `appointments.confirm`, `appointments.cancel` (existen) | Sí | Sí | — |
| `appointments.check-in` (existe) | Sí | Sí | Sí |
| `appointments.overbook` (nuevo) | Sí | Sí | — |
| `appointments.no-show` (nuevo) | Sí | Sí | — |
| `appointments.attend` (nuevo) | Sí | — | Sí |
| `appointments.queue` (nuevo) | Sí | Sí | Sí |

El administrador pasa todo por `Gate::before`; los tests usan un rol propio.

### Estados del turno

| Desde \ Hacia | Confirmado | Fichado | Atendido | Ausente | Cancelado |
|---|---|---|---|---|---|
| A confirmar | `confirm` | `check-in` | — | `no-show` | `cancel` |
| Confirmado | — | `check-in` | — | `no-show` | `cancel` |
| Fichado | — | — | `attend` | — | `cancel` |
| Ausente | `undo-no-show`, solo el día del turno ([pregunta 19](#preguntas-del-diseño)) | — | — | — | — |
| Atendido, cancelado | Finales: ninguna transición | | | | |

Las reglas de cada transición están en la [3.2b](#32b-estados-del-turno). `AppointmentStatus` suma `canTransitionTo()` y
`occupiesSlot()`, y la transición se compara con enums, nunca con textos.

### Orden de bloqueo

Pregunta 15 (R-048): la superposición por veterinario se controla con su fila bloqueada (`lockForUpdate` sobre `veterinarians`)
**antes** que la de la agenda. Para que ninguna operación tome dos filas en sentido contrario, toda la fase usa un solo orden:

**clínica → veterinario → servicio → agenda → paciente → turno**

Ninguna operación bloquea un veterinario o un servicio después de una agenda, ni una agenda después de un turno. Con varias filas del
mismo tipo, por id ascendente.

| Operación | Bloqueos, en orden |
|---|---|
| Alta de una agenda, o cambio de su veterinario o de su servicio (3.1) | Clínica (`CatalogLock`, si se crea o cambia el nombre) → veterinario (`sharedLock`) → servicio (`sharedLock`) → agenda (`lockForUpdate`, al editar) |
| Horario, bloqueos, inactivación, baja o cambio de nombre de una agenda (3.1) | Clínica (`CatalogLock`, solo si cambia el nombre) → agenda (`lockForUpdate`) |
| Alta de un turno o de una serie (3.2a, 3.3) | Se lee la agenda **sin** bloqueo para saber su veterinario y su servicio. Después: veterinario de la agenda (`lockForUpdate`; serializa sus turnos entre agendas) → servicio del turno (`sharedLock`) → agenda (`lockForUpdate`) → paciente (`sharedLock`). Con la agenda bloqueada se vuelve a leer: si cambió su veterinario o su servicio por defecto en el medio, se deshace la transacción y se repite (hasta 3 veces; después, 409 `CONFLICT`). Una agenda sin veterinario saltea el primer paso |
| Edición o mover un turno (3.2a) | Se lee el turno sin bloqueo. Después: veterinario de la agenda de destino (`lockForUpdate`) → servicio (`sharedLock`) → agendas de origen y destino (`lockForUpdate`, por id) → paciente (`sharedLock`, si cambia) → turno (`lockForUpdate`). Con el turno bloqueado se verifica que no cambiaron su agenda ni su estado; si cambiaron, se repite como en el alta |
| Transiciones (3.2b) | Solo el turno (`lockForUpdate`): liberan horario, nunca lo ocupan. Excepción: **deshacer el ausente** vuelve a ocupar el horario y bloquea como un alta: veterinario → agenda → turno |
| Cancelar los siguientes de una serie (3.3) | Los turnos de la serie, por id (`lockForUpdate`) |

**Compatibilidad con la FASE 2.** Las operaciones de la FASE 2 que tocan estas filas las toman en el mismo sentido y nunca bloquean una
agenda ni un turno después: la baja del veterinario (veterinario `lockForUpdate` → revisa el uso), el alta y la restauración del
veterinario (clínica → usuario → veterinario → serie), la baja y el cambio de nombre del servicio (servicio `lockForUpdate` → revisa el
uso) y, desde la 3.2a, la baja del paciente y la del cliente (su fila `lockForUpdate` → revisa el uso). El uso se revisa con lecturas
sin bloqueo dentro de la transacción. El alta de un turno no toma `CatalogLock`.

### Relación con la consulta (FASE 4)

Propuesta para el diseño de la FASE 4, que la decide; la FASE 3 no crea consultas.

* La consulta se abre desde un turno **fichado** (`consultations.appointment_id`, única: un turno, una consulta). Abrirla marca el
  turno como **atendido** en la misma transacción, con el orden agenda → turno → consulta: "atendido" significa que el veterinario lo
  atendió, aunque la consulta siga abierta.
* `POST appointments/{appointment}/attend` sigue para los turnos sin historia clínica (una agenda de peluquería, por ejemplo).
* Un turno atendido no se cancela; cancelar la consulta (FASE 4) no vuelve el turno a fichado.
* Una consulta sin turno (urgencia) sigue siendo posible: `appointment_id` es opcional.

## Diseño por entrega

### 3.0 Deuda 16: zona de la sesión de MySQL

**Alcance.** Fijar la zona de la sesión de MySQL para que las columnas `timestamp` no dependan de la zona del servidor (R-047). Sin
API, sin migraciones y sin cambios en los datos: hoy la sesión ya es UTC-3 ("Hora estándar de Argentina", `SYSTEM`).

**Cambios.**

| Archivo | Cambio |
|---|---|
| `config/database.php` | Conexión `mysql`: `'timezone' => env('DB_TIMEZONE', '-03:00')`. Laravel la aplica al conectar (`MySqlConnector`: `set time_zone='-03:00'`). La de `mariadb`, igual, por coherencia |
| `.env.example` | `DB_TIMEZONE=-03:00`, con un comentario: es la zona de la sesión de MySQL, no la de la aplicación |
| `CLAUDE.md` §3 (Fechas con hora) | La regla completa: la sesión de MySQL en `-03:00`; las horas de negocio las pone PHP (`now()` de Laravel, `AppDateTime`), nunca `NOW()`, `CURRENT_TIMESTAMP` ni `useCurrent()` en código nuevo; columnas nuevas de hora de negocio en `datetime`. La excepción al "nunca un `-03:00` fijo": en la sesión de MySQL el desplazamiento solo define cómo se guarda el valor por dentro (la ida y la vuelta son exactas aunque una fecha vieja haya tenido horario de verano); las conversiones en PHP siguen con `America/Asuncion` |
| `development/setup.md` y `development/deployment.md` | La variable y por qué no se usa `America/Asuncion` en MySQL (las zonas con nombre no están cargadas en Laragon: `mysql.time_zone_name` vacía, y tendrían el horario de verano viejo) |
| Deuda técnica | 16 resuelta; 17 nueva (el tope de 2038 de `timestamp`) |

**Por qué no pasar las columnas a `datetime`.** Son unas 40 columnas (`timestamps()` de casi todas las tablas, `audit_logs.created_at`,
`confirmed_at`, `closed_at`…): una migración grande para un riesgo que la zona fija ya cierra. Queda solo el tope de 2038 (deuda 17).

**Efecto.** `audit_logs`, los `created_at`/`updated_at` del framework y Sanctum (que compara en PHP) no cambian. `useCurrent()` de
`inventory_movements.created_at` (el modelo no llena `created_at`: `$timestamps = false`) y de `failed_jobs.failed_at` pasa a dar la
hora de Asunción aunque el servidor esté en UTC. Si Paraguay cambiara otra vez de desplazamiento, solo `NOW()` y `useCurrent()`
quedarían corridos: por eso la regla de no usarlos en código nuevo.

**Tests** (`tests/Feature/Database/MySqlSessionTimezoneTest`, solo MySQL; en SQLite, omitidos como los demás de MySQL):

| Test | Qué verifica |
|---|---|
| `test_the_session_uses_the_configured_offset` | `SELECT @@session.time_zone` es `-03:00` |
| `test_the_offset_survives_a_reconnection` | `SET time_zone = '+05:00'`, `DB::reconnect()` y otra vez `-03:00` |
| `test_a_timestamp_keeps_its_instant` | Un `timestamp` guardado por Eloquent (`patients.deceased_at`) con `2026-07-01T12:00:00Z` da `UNIX_TIMESTAMP()` igual al de PHP y se lee igual |
| `test_use_current_gives_asuncion_time` | Un `inventory_movements` insertado sin `created_at` queda con la hora de Asunción (reloj de MySQL, tolerancia de unos segundos) |

Y un test estático (`tests/Unit/NoDatabaseNowTest`): ninguna migración nueva usa `useCurrent()`, `CURRENT_TIMESTAMP` ni `NOW()`
(lista permitida: las dos columnas de hoy), y `app/` no usa `NOW()` ni `CURRENT_TIMESTAMP` en SQL crudo.

**Mutaciones críticas:** quitar `timezone` de la configuración; cambiarla a `+00:00` (falla `test_a_timestamp_keeps_its_instant` y el
de `useCurrent`); agregar `useCurrent()` en una migración nueva; `DB::raw('NOW()')` en un Service.

**Postman:** no cambia la API. Como regresión, la colección de auditoría (`syncvet-fase-02-auditoria`) contra la base de desarrollo.

**MySQL:** toca la conexión: `composer test:mysql` durante el trabajo.

### 3.1 Agendas, horario semanal y bloqueos

**Alcance.** ABM de agendas (P9 a P11) con su horario semanal y sus bloqueos.

**Migraciones:** ninguna (`schedules`, `schedule_hours` y `schedule_blocks` existen). La baja es física ([pregunta 13](#preguntas-del-diseño)),
así que `schedules` no suma `deleted_at`.

**Reglas.**

| Regla | Detalle |
|---|---|
| Sucursal | `branch_id` obligatorio: una sucursal activa de la clínica. No cambia si la agenda tiene turnos ([pregunta 14](#preguntas-del-diseño)) |
| Veterinario y servicio (P10) | Al menos uno de los dos (si no, 422 en `veterinarian_id`: "Elegí un veterinario, un servicio o los dos."). El veterinario: de la clínica, activo, con su usuario activo y asignado a la sucursal de la agenda (`branch_user`; si no, 422 "El veterinario no trabaja en esa sucursal."). El servicio: de la clínica y activo. Al editar se acepta el que la agenda ya tiene aunque se haya inactivado (el formulario lo reenvía), como las especies (§6.2). El veterinario no cambia si la agenda tiene turnos ([pregunta 14](#preguntas-del-diseño)); el servicio sí (es el que se propone a los turnos nuevos) |
| Nombre | Obligatorio, 2 a 100, con al menos una letra (`CatalogMessages::NAME_REGEX`), único dentro de la sucursal sin mayúsculas, acentos ni espacios repetidos (`CatalogName`), con la clínica bloqueada: "Ya hay una agenda con ese nombre en la sucursal." |
| Duración del turno | `slot_minutes`: de 5 a 240 en múltiplos de 5, 30 por defecto. Es el paso de la disponibilidad y la duración de un turno sin servicio (P5) |
| Color | `color` opcional, `#RRGGBB` (`^#[0-9A-Fa-f]{6}$`), se guarda en mayúsculas; solo para el frontend |
| Reserva online | `online_booking` no se acepta (se ignora) ni se muestra ([decisiones comunes](#decisiones-comunes)) |
| Horario semanal | `hours`: lista de `{weekday, starts_at, ends_at}`, con `weekday` de 1 (lunes) a 7 (domingo) y horas `HH:MM` de reloj de Asunción, de 00:00 a 23:55 en múltiplos de 5, `starts_at < ends_at`. En el mismo día, los tramos no se superponen (tocarse sí: 08:00–12:00 y 12:00–16:00); hasta 6 tramos por día. Opcional en el alta (sin horario, la agenda no recibe turnos). En la edición, si llega, **reemplaza** el horario entero (`hours: []` lo vacía): se borran los tramos que no están, se crean los nuevos y los que no cambian quedan, cada uno con su auditoría. Cambiar el horario no toca los turnos que ya existen |
| Inactivar | `is_active: false`, aunque tenga turnos futuros: los turnos siguen y la recepción los reprograma ([pregunta 24](#preguntas-del-diseño)). Una agenda inactiva no recibe turnos nuevos ni ofrece disponibilidad |
| Baja (P11) | Solo sin turnos, también cancelados: si no, 409 `SCHEDULE_IN_USE`. Se elimina con sus tramos y sus bloqueos, modelo por modelo (para la auditoría), en una transacción |
| Bloqueos | `schedules/{schedule}/blocks`: `starts_at` y `ends_at` obligatorios (instantes, `AppDateTime`; múltiplos de 5 minutos), `ends_at > starts_at`, hasta 366 días de largo; `reason` hasta 255, opcional; `created_by` es quien lo crea. Se pueden superponer entre sí. No se editan: se eliminan y se crea otro. Un bloqueo sobre turnos activos se permite, y la respuesta los lista en `affected_appointments` (id, `starts_at`, paciente o contacto) para que la recepción los reprograme ([pregunta 22](#preguntas-del-diseño)). En una agenda inactiva también se bloquea. Los feriados de toda la clínica quedan fuera del MVP (un bloqueo por agenda) |
| Uso | Una agenda es uso de su veterinario (`VeterinarianService::USAGE`) y de su servicio (`ServiceCatalogService::USAGE`): con una agenda, el veterinario no se elimina (409) y el servicio no se elimina ni cambia de nombre. Al eliminar la agenda (sin turnos), deja de serlo |
| Listados | Agendas paginadas con `ListFilters`: activas por defecto, `include_inactive=1`; filtros `branch_id`, `veterinarian_id` y `service_id`; `search` literal en el nombre; orden por nombre e id. Bloqueos: los que terminan desde hoy, salvo `from`/`to` (`ApiDateTime`), paginados, por `starts_at` |

**Endpoints** (todos con `auth:sanctum` y `clinic`):

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `schedules` | `schedules.index` | `IndexScheduleRequest` | 200, `ScheduleResource` paginado | 422 |
| POST | `schedules` | `schedules.store` | `StoreScheduleRequest`: `branch_id`, `name`, `veterinarian_id`, `service_id`, `slot_minutes`, `color`, `is_active`, `hours` | 201, `ShowScheduleResource` (con `hours` y los bloqueos próximos) | 422 |
| GET | `schedules/{schedule}` | `schedules.show` | — | 200, `ShowScheduleResource` | 404 |
| PATCH/PUT | `schedules/{schedule}` | `schedules.update` | `UpdateScheduleRequest` (`sometimes`) | 200 | 404; 422 `SCHEDULE_NOT_EDITABLE`; 422 |
| DELETE | `schedules/{schedule}` | `schedules.destroy` | — | 200 | 404; 409 `SCHEDULE_IN_USE` |
| GET | `schedules/{schedule}/blocks` | `schedules.schedule-blocks.index` → `schedule-blocks.index` | `IndexScheduleBlockRequest`: `ListFilters`, `from`, `to` | 200, `ScheduleBlockResource` paginado | 404; 422 |
| POST | `schedules/{schedule}/blocks` | `schedules.schedule-blocks.store` → `schedule-blocks.store` | `StoreScheduleBlockRequest`: `starts_at`, `ends_at`, `reason` | 201, `ScheduleBlockResource` con `affected_appointments` | 404; 422 |
| DELETE | `schedule-blocks/{scheduleBlock}` | `schedule-blocks.destroy` | — | 200 | 404 |

Las rutas de bloqueos llevan nombres propios (`->names()`) para que su permiso sea `schedule-blocks.*` por la regla de los dos últimos
tramos: no necesitan excepción en `ROUTES_WITH_ANOTHER_PERMISSION`. `ScheduleResource`: `id`, `name`, `branch` (`id`, `name`),
`veterinarian` (`id`, `code`, `name`, o `null`), `service` (`id`, `code`, `name`, o `null`), `slot_minutes`, `color` e `is_active`.
`ShowScheduleResource` suma `hours` (ordenados por día y hora) y `upcoming_blocks` (los que terminan desde hoy, hasta 20).

**Errores** (`ScheduleException`, nueva):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `SCHEDULE_IN_USE` | 409 | La agenda tiene turnos y no se puede eliminar: inactivala. |
| `SCHEDULE_NOT_EDITABLE` | 422 | La agenda ya tiene turnos y no puede cambiar de sucursal ni de veterinario: creá otra agenda e inactivá esta. |

Los demás, 422 `VALIDATION_ERROR` en su campo (nombre repetido, veterinario o servicio inactivo, sucursal, tramos superpuestos).

**Concurrencia** ([orden de bloqueo](#orden-de-bloqueo)). Alta y cambio de nombre: la clínica (`CatalogLock`) antes de leer los
nombres. El alta y el cambio de veterinario o de servicio toman el veterinario y el servicio con `sharedLock` **antes** que la agenda
(una baja simultánea del veterinario, que lo bloquea con `lockForUpdate` antes de revisar el uso, ve la agenda o hace fallar el alta
con 422). Edición, horario, inactivación, bloqueos y baja: la agenda con `lockForUpdate` antes de revisar los turnos, el uso o
reemplazar el horario; el alta de un turno toma la misma fila, así que un turno y un cambio de horario o un bloqueo no se cruzan.
Orden: clínica → veterinario → servicio → agenda.

**Tests** (`tests/Feature/Schedules`, con un rol de test con `schedules.*` y `schedule-blocks.*`; la recepción para el 403 de escritura):

| Test | Qué verifica |
|---|---|
| `ScheduleStoreTest` | Alta con horario; sin veterinario ni servicio → 422; veterinario inactivo, de usuario inactivo, de otra clínica o sin la sucursal → 422; servicio inactivo → 422; sucursal inactiva → 422; nombre repetido en la sucursal con otras mayúsculas o acentos → 422 y en otra sucursal → 201; `slot_minutes` y `color` fuera de formato → 422; `online_booking` se ignora |
| `ScheduleHoursTest` | Tramos superpuestos el mismo día → 422; que se tocan → 200; horas fuera de la grilla de 5 o `starts_at >= ends_at` → 422; el reemplazo borra, crea y conserva (auditoría de cada tramo); `hours: []` vacía |
| `ScheduleUpdateTest` | Cambiar sucursal o veterinario con turnos → 422 `SCHEDULE_NOT_EDITABLE` y sin turnos → 200; cambiar el servicio con turnos → 200; reenviar un veterinario ya inactivo → 200; inactivar con turnos futuros → 200 |
| `ScheduleDeleteTest` | Sin turnos → 200, con sus tramos y bloqueos, y el veterinario y el servicio quedan sin uso; con un turno (también cancelado) → 409 |
| `ScheduleBlockTest` | Alta, `affected_appointments` con los turnos activos (no los cancelados), `ends_at <= starts_at` → 422, más de 366 días → 422, baja, bloqueo de otra agenda en la URL → 404 |
| `ScheduleIndexTest` | Activas por defecto, `include_inactive`, filtros, `search` literal, paginación; no trae las de otra clínica |
| Uso de la FASE 2 | `VeterinarianDeleteTest` y `ServiceDeleteTest`/`ServiceUpdateTest` con una agenda creada por la API (además de las filas del helper) |
| Acceso y aislamiento | Las 8 rutas en `tests/Feature/Access` y una agenda o un bloqueo de otra clínica → 404 en cada ruta |

**Mutaciones críticas:** quitar la regla "al menos uno" (veterinario o servicio); quitar el chequeo de turnos en la baja; quitar el
chequeo de superposición de tramos; quitar el `SCHEDULE_NOT_EDITABLE`; quitar el filtro de la sucursal del veterinario.

**Postman:** `syncvet-fase-03-agendas` (repetible: crea en la corrida su usuario veterinario, su veterinario y su servicio con nombres
únicos): alta con horario, reemplazo del horario, bloqueo, inactivar, baja sin turnos. Suma los **pendientes de la FASE 2**: la baja del
veterinario con una agenda → 409 `VETERINARIAN_IN_USE`; la baja del servicio con una agenda → 409 `SERVICE_IN_USE` y su cambio de
nombre → 422 `SERVICE_NAME_NOT_EDITABLE`. Sin tocar el `DemoSeeder`.

### 3.2a Turnos únicos

**Alcance.** Alta, detalle, listado, edición y mover de un turno, sin superposición en la agenda, dentro del horario y fuera de los
bloqueos; sobreturno (P2, P4 a P7).

**Migración nueva** `2026_11_xx_000000_add_contact_and_overbook_to_appointments`:

| Columna o índice | Tipo |
|---|---|
| `contact_name` | `string(150)`, nulo |
| `contact_phone` | `string(20)`, nulo |
| `is_overbooked` | `boolean`, `false` por defecto |
| `appointments_veterinarian_id_starts_at_index` | Índice `(veterinarian_id, starts_at)`, para la cola del día |

**Reglas.**

| Regla | Detalle |
|---|---|
| Agenda | `schedule_id` obligatorio: una agenda activa de la clínica (si no, 422 "La agenda no existe o está inactiva."). Si tiene veterinario, tiene que estar activo y con su usuario activo (422 en `schedule_id`: "El veterinario de la agenda está inactivo."). `branch_id` y `veterinarian_id` se copian de la agenda; en el cuerpo, `prohibited` |
| Paciente, cliente o contacto (P4) | Uno de tres: `patient_id` (de la clínica, no eliminado, no fallecido); `client_id` sin paciente (primera vez); o `contact_name` (2 a 150, texto libre con al menos una letra; no usa `PersonName`, porque puede ser "Juan, el de la perra Luna") y `contact_phone` (la regla de teléfonos de clientes), los dos juntos. Sin ninguno → 422 en `patient_id`: "Ingresá el paciente, el cliente o un nombre y un teléfono de contacto." Con paciente, `client_id` es su dueño de ese momento (si llega otro → 422 "El paciente no es de ese cliente."). Los datos de contacto son `prohibited` con un cliente o un paciente (`null` se acepta: el frontend manda el formulario completo). Al editar se puede pasar de contacto a cliente y de cliente a paciente |
| Servicio | `service_id` opcional: si no llega, el de la agenda. De la clínica y activo; al editar, también el que el turno ya tiene aunque se haya inactivado |
| Horario (P5) | `starts_at` obligatorio; `ends_at` opcional: por defecto `starts_at` más `duration_minutes` del servicio o `slot_minutes` de la agenda. `ends_at > starts_at`, hasta 720 minutos, el mismo día de Asunción (no cruza la medianoche), los dos en múltiplos de 5 minutos y sin segundos. `starts_at` no antes del comienzo del día de hoy en Asunción (422 "No podés dar un turno en un día que ya pasó."; [pregunta 16](#preguntas-del-diseño)) |
| Dentro del horario (P6) | El rango entero dentro de **un** tramo de `schedule_hours` del día de la semana de `starts_at` (en hora de Asunción). Si no → 422 `APPOINTMENT_OUTSIDE_HOURS` |
| Bloqueos (P6) | Un bloqueo de la agenda que se superpone con el rango → 422 `APPOINTMENT_IN_BLOCK` |
| Superposición | Un turno de la misma agenda que ocupa el horario y se superpone → 409 `APPOINTMENT_OVERLAP`. También choca con un turno del **mismo veterinario en otra agenda** ([pregunta 15](#preguntas-del-diseño)) |
| Sobreturno (P2) | `overbook: true` necesita `appointments.overbook` (si no, 403 `FORBIDDEN`, en el `authorize()` del Form Request). Saltea solo la superposición (en la agenda y con los turnos del mismo veterinario en otra agenda, pregunta 15), no el horario ni los bloqueos. `is_overbooked` queda en `true` solo si al guardar se superpone con otro; sin choque queda en `false`. Un turno común que se superpone con un sobreturno también recibe 409 |
| Origen | `source`: `reception` (por defecto), `phone` o `whatsapp`; `online` → 422 ([decisiones comunes](#decisiones-comunes)) |
| Otros | `reason` hasta 255, `notes` hasta 1000. `status`, `series_id`, `is_overbooked`, `confirmed_at`, `checked_in_at`, `cancelled_*` y `created_by`: no se reciben (`prohibited` o los fija el Service) |
| Edición y mover (P7) | `PATCH appointments/{appointment}` solo a confirmar o confirmado (si no, 422 `APPOINTMENT_NOT_EDITABLE`). Mover es cambiar `starts_at`, `ends_at` o `schedule_id` (a otra agenda activa de la clínica: copia otra vez la sucursal y el veterinario): se revisan otra vez el horario, los bloqueos y la superposición (sin contarse a sí mismo). Un turno movido conserva su estado ([pregunta 17](#preguntas-del-diseño)). Si solo cambia el servicio, `ends_at` no se recalcula |
| Sin baja | No hay `DELETE`: un turno se cancela (3.2b). `DELETE appointments/{appointment}` → 405 |
| Uso de otros datos | Un turno es uso del paciente (ya en `PATIENT_IN_USE`), del veterinario, del servicio y del usuario que lo crea (ya en sus listas). **Del cliente**: `CLIENT_IN_USE` suma los turnos, con el mensaje "El cliente tiene pacientes, turnos, consultas o facturas y no puede eliminarse." ([pregunta 21](#preguntas-del-diseño)) |
| Listado | Paginado con `ListFilters`. `from` y `to` (`ApiDateTime`: una fecha sola es el día entero de Asunción), por defecto hoy, hasta 31 días; filtros `schedule_id`, `branch_id`, `veterinarian_id`, `service_id`, `patient_id`, `client_id`, `status` (uno o varios) y `series_id`; `search` literal en el nombre y el código del paciente, el nombre y el documento del cliente y el nombre y el teléfono de contacto; orden por `starts_at` e id |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `appointments` | `appointments.index` | `IndexAppointmentRequest` | 200, `AppointmentResource` paginado | 422 |
| POST | `appointments` | `appointments.store` (y `appointments.overbook` con `overbook: true`) | `StoreAppointmentRequest` | 201, `ShowAppointmentResource` | 403; 409 `APPOINTMENT_OVERLAP`; 422 `APPOINTMENT_OUTSIDE_HOURS`; 422 `APPOINTMENT_IN_BLOCK`; 422 |
| GET | `appointments/{appointment}` | `appointments.show` | — | 200, `ShowAppointmentResource` | 404 |
| PATCH/PUT | `appointments/{appointment}` | `appointments.update` (y `appointments.overbook`) | `UpdateAppointmentRequest` (`sometimes`) | 200 | 403; 404; 409 `APPOINTMENT_OVERLAP`; 422 `APPOINTMENT_NOT_EDITABLE`; 422 `APPOINTMENT_OUTSIDE_HOURS`; 422 `APPOINTMENT_IN_BLOCK`; 422 |

`appointments.overbook` no es el permiso de una ruta: es un permiso de un campo, revisado en el `authorize()` del Form Request; va en
`RolesAndPermissionsSeeder::PERMISSIONS` y su test de acceso lo prueba aparte.

`AppointmentResource`: `id`, `schedule` (`id`, `name`), `branch` (`id`, `name`), `veterinarian` (`id`, `code`, `name`, o `null`),
`service` (`id`, `code`, `name`, o `null`), `patient` (`id`, `code`, `name`, o `null`), `client` (`id`, `display_name`, o `null`),
`contact_name`, `contact_phone`, `starts_at`, `ends_at` (UTC, `Z`), `status` y `source` (`{value, label}`), `is_overbooked`,
`series_id` y `reason`. `ShowAppointmentResource` suma `notes`, `created_by` (`id`, `name`), `confirmed_at`, `checked_in_at`,
`cancelled_at`, `cancelled_by`, `cancellation_reason` y `created_at`.

**Errores** (`AppointmentException`):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `APPOINTMENT_OVERLAP` | 409 | El horario ya está ocupado: elegí otro o dalo como sobreturno. |
| `APPOINTMENT_OUTSIDE_HOURS` | 422 | El turno está fuera del horario de la agenda. |
| `APPOINTMENT_IN_BLOCK` | 422 | La agenda está bloqueada en ese horario. |
| `APPOINTMENT_NOT_EDITABLE` | 422 | Solo se modifican los turnos a confirmar o confirmados. |

**Concurrencia** ([orden de bloqueo](#orden-de-bloqueo)). Alta, edición y mover en `DB::transaction`: primero el veterinario de la
agenda con `lockForUpdate` (pregunta 15: serializa sus turnos entre todas sus agendas), después el servicio con `sharedLock`, después
la agenda con `lockForUpdate` (al mover, las dos, por id ascendente), y con todo eso bloqueado se leen los tramos, los bloqueos, los
turnos de la agenda y los del veterinario en sus otras agendas que se superponen. Después el paciente con `sharedLock` (su baja lo
bloquea antes de revisar el uso) y, al editar, el turno con `lockForUpdate`. Orden: veterinario → servicio → agenda → paciente →
turno. **La baja del paciente** pasa a bloquear la fila
del paciente (`lockForUpdate`) y a revisar el uso dentro de su transacción: hoy (`PatientService::delete`) lo revisa antes, sin
bloqueo, y un turno simultáneo podría quedar en un paciente eliminado. El cliente, igual en `ClientService::delete`.

**Tests** (`tests/Feature/Appointments`):

| Test | Qué verifica |
|---|---|
| `AppointmentStoreTest` | Alta con paciente (el cliente es su dueño), con cliente sin paciente y con contacto; sin ninguno → 422; paciente de otro cliente, fallecido o de otra clínica → 422; contacto con cliente → 422; `ends_at` por defecto con servicio y sin servicio; `branch_id`, `veterinarian_id`, `status` o `series_id` en el cuerpo → 422; `source: online` → 422; fecha pasada → 422; fuera de la grilla de 5 o cruzando la medianoche → 422; agenda inactiva o con veterinario inactivo → 422 |
| `AppointmentScheduleRulesTest` | Fuera del horario, empezando dentro y terminando fuera, en un día sin tramos → 422 `APPOINTMENT_OUTSIDE_HOURS`; en el borde exacto del tramo → 201; con un bloqueo que se superpone → 422 `APPOINTMENT_IN_BLOCK` y uno que se toca → 201 |
| `AppointmentOverlapTest` | El mismo horario → 409; que se tocan → 201; contra un cancelado o un ausente → 201; contra un fichado o un atendido → 409; el mismo veterinario en otra agenda → 409, y con `overbook: true` → 201 (pregunta 15); el log de consultas muestra el `FOR UPDATE` de `veterinarians` antes que el de `schedules` (MySQL) |
| `AppointmentOverbookTest` | `overbook: true` con permiso → 201 con `is_overbooked`; sin choque → `is_overbooked: false`; sin permiso → 403; con sobreturno, fuera del horario o en un bloqueo → 422 |
| `AppointmentUpdateTest` | Mover dentro de la misma agenda sin chocar consigo mismo; a otra agenda (copia sucursal y veterinario); mover a un horario ocupado → 409; editar un fichado, atendido, ausente o cancelado → 422 `APPOINTMENT_NOT_EDITABLE`; de contacto a cliente y de cliente a paciente |
| `AppointmentConcurrencyTest` (solo MySQL) | Una segunda conexión toma la agenda con `FOR UPDATE`; el alta, con `innodb_lock_wait_timeout = 1`, espera y falla por el tiempo de espera (prueba que pide el bloqueo antes de leer); el log de consultas muestra el `FOR UPDATE` de `schedules` antes del `SELECT` de la superposición. Más el criterio de cierre: dos altas seguidas en el mismo horario, una 201 y la otra 409 |
| `AppointmentIndexTest` | Por defecto hoy; `from`/`to`, más de 31 días → 422; filtros; `search` literal; no trae los de otra clínica |
| Usos | `ClientDeleteTest`: un cliente con un turno → 409; `PatientDeleteTest`: la baja revisa el uso con el paciente bloqueado (log de consultas en MySQL) |
| Acceso y aislamiento | Las 4 rutas en `tests/Feature/Access`; una agenda, un paciente, un cliente o un servicio de otra clínica → 422 y un turno de otra clínica → 404 |
| Fechas | `DateTimeCastCoverageTest` sin cambios (las columnas nuevas no tienen hora); `starts_at` con `Z`, con desplazamiento y sin zona dan la misma hora de Asunción |

**Mutaciones críticas:** `<` por `<=` en la superposición (dos turnos que se tocan dan 409); quitar el `lockForUpdate` de la agenda;
contar los cancelados como ocupados (o no contar los fichados); quitar el chequeo de bloqueos; quitar el permiso del sobreturno.

**Postman:** `syncvet-fase-03-turnos` (repetible: crea su agenda con un horario amplio y calcula las fechas en el *pre-request*, el
próximo lunes y siguientes): alta con paciente, con cliente y con contacto; superposición → 409; sobreturno → 201; fuera del horario y
en un bloqueo → 422; mover; listado del día.

**Pendientes que deja la 3.1 (R-050).** Para el diseño y la colección de la 3.2a:

* **Postman:** mover una agenda con turnos (cambio de sucursal o de veterinario, 422 `SCHEDULE_NOT_EDITABLE`) y la baja de una
  agenda con un turno real (409 `SCHEDULE_IN_USE`). En la 3.1 los cubren los tests (`ScheduleUpdateTest` y `ScheduleDeleteTest`),
  porque la API todavía no creaba turnos.
* **Deadlock:** evaluar `DB::transaction` con reintentos ante un *deadlock* (MySQL, código 1213) en el alta y el mover de turnos,
  para que nunca respondan 500 (hoy `DB::transaction` corre un solo intento). Lo sugirió la revisión de la 3.1 (hallazgo 1).

**MySQL:** toca concurrencia: `composer test:mysql` durante el trabajo.

#### Pendiente para el cierre (3.2a)

Vacía: lo que tenía (cifras, desvíos, revisión, mutaciones y Postman) pasó al [resultado de la 3.2a](#entrega-32a) (R-051).

### 3.2b Estados del turno

**Alcance.** Las transiciones de `AppointmentStatus` ([tabla](#estados-del-turno)), cada una con su ruta, su permiso y su test (P3,
P7, P12).

**Migración nueva** `2026_11_xx_000001_add_attended_and_no_show_to_appointments`: `attended_at` y `no_show_at`, `datetime` nulos
(columnas nuevas de hora de negocio, R-047), con `AppDateTime`. Se guardan como columnas, igual que `confirmed_at` y `checked_in_at`,
porque la cola del día, la demora y el ausentismo (FASE 10) los consultan; la auditoría guarda además quién hizo cada cambio, pero no
sirve para consultas de negocio ([pregunta 20](#preguntas-del-diseño)).

**Reglas.**

| Transición | Ruta | Desde | Reglas | Fija |
|---|---|---|---|---|
| Confirmar | `POST appointments/{appointment}/confirm` | A confirmar | — | `status`, `confirmed_at` |
| Fichar | `POST appointments/{appointment}/check-in` | A confirmar, confirmado | El turno tiene paciente (si no, 422 `APPOINTMENT_PATIENT_REQUIRED`), no fallecido; solo el día del turno en Asunción (si no, 422 `APPOINTMENT_CHECK_IN_NOT_TODAY`) | `status`, `checked_in_at` |
| Atendido | `POST appointments/{appointment}/attend` | Fichado | Un usuario con ficha de veterinario y sin `appointments.update` marca solo los turnos de su ficha o de agendas sin veterinario (si no, 403; [pregunta 18](#preguntas-del-diseño)) | `status`, `attended_at` |
| Ausente (P12) | `POST appointments/{appointment}/no-show` | A confirmar, confirmado | Solo cuando llegó la hora del turno (`starts_at <= now`; si no, 422 `APPOINTMENT_NO_SHOW_TOO_EARLY`) | `status`, `no_show_at` |
| Deshacer el ausente (pregunta 19) | `POST appointments/{appointment}/undo-no-show` | Ausente | Solo el día del turno en Asunción (si no, 422 `APPOINTMENT_NO_SHOW_NOT_UNDOABLE`). Vuelve a ocupar el horario: se revisa la superposición como en un alta, sin contarse a sí mismo (ocupado → 409 `APPOINTMENT_OVERLAP`, salvo un sobreturno, `is_overbooked`, que se recalcula); el horario y los bloqueos no se vuelven a revisar. Queda en la auditoría (`updated`, de ausente a confirmado) | `status` (confirmado) y `no_show_at` en `null`; `confirmed_at`, la hora actual si no tenía |
| Cancelar (P3) | `POST appointments/{appointment}/cancel` | A confirmar, confirmado, fichado | `cancellation_reason` obligatorio, de 3 a 500; sin límite de anticipación | `status`, `cancelled_at`, `cancelled_by`, `cancellation_reason` |

Otra transición (también repetir la misma: confirmar un confirmado) → 422 `APPOINTMENT_INVALID_TRANSITION`. Responden 200 con
`ShowAppointmentResource`. Atendido y cancelado son finales; el ausente también, pasado el día del turno.

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Errores |
|---|---|---|---|---|
| POST | `appointments/{appointment}/confirm` | `appointments.confirm` (existe) | — | 404; 422 `APPOINTMENT_INVALID_TRANSITION` |
| POST | `appointments/{appointment}/check-in` | `appointments.check-in` (existe) | — | 404; 422 `APPOINTMENT_INVALID_TRANSITION`, `APPOINTMENT_PATIENT_REQUIRED`, `APPOINTMENT_CHECK_IN_NOT_TODAY` |
| POST | `appointments/{appointment}/attend` | `appointments.attend` (nuevo) | — | 403; 404; 422 `APPOINTMENT_INVALID_TRANSITION` |
| POST | `appointments/{appointment}/no-show` | `appointments.no-show` (nuevo) | — | 404; 422 `APPOINTMENT_INVALID_TRANSITION`, `APPOINTMENT_NO_SHOW_TOO_EARLY` |
| POST | `appointments/{appointment}/undo-no-show` | `appointments.undo-no-show` → `appointments.no-show` | — | 404; 409 `APPOINTMENT_OVERLAP`; 422 `APPOINTMENT_INVALID_TRANSITION`, `APPOINTMENT_NO_SHOW_NOT_UNDOABLE` |
| POST | `appointments/{appointment}/cancel` | `appointments.cancel` (existe) | `CancelAppointmentRequest`: `cancellation_reason` (y `scope` en la 3.3) | 404; 422 `APPOINTMENT_INVALID_TRANSITION`; 422 |

**Errores** (`AppointmentException`):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `APPOINTMENT_INVALID_TRANSITION` | 422 | El turno está «:status» y no admite ese cambio. (`:status`, la etiqueta del estado) |
| `APPOINTMENT_PATIENT_REQUIRED` | 422 | Para fichar el turno, cargá el paciente. |
| `APPOINTMENT_CHECK_IN_NOT_TODAY` | 422 | Solo se fichan los turnos del día. |
| `APPOINTMENT_NO_SHOW_TOO_EARLY` | 422 | El turno todavía no empezó: no se puede marcar como ausente. |
| `APPOINTMENT_NO_SHOW_NOT_UNDOABLE` | 422 | El ausente solo se deshace el mismo día del turno. |

`appointments.undo-no-show` usa el permiso `appointments.no-show` (pregunta 19: lo deshacen el administrador y la recepción): va en
`ROUTES_WITH_ANOTHER_PERMISSION` con su motivo y no suma un permiso.

**Concurrencia.** Cada transición, en `DB::transaction` con el turno `lockForUpdate` antes de leer su estado: dos clics simultáneos
(confirmar y cancelar) no dejan un estado imposible. No bloquea la agenda: una transición solo libera horario (cancelado, ausente),
nunca lo ocupa. Deshacer el ausente sí lo ocupa: bloquea veterinario → agenda → turno, como un alta. El fichado no bloquea al
paciente: el turno ya existe, así que la baja del paciente lo ve como uso.

**Tests** (`tests/Feature/Appointments/Transitions`): uno por transición válida (estado, hora y auditoría `updated` con el estado
anterior y el nuevo) y, con un *data provider*, cada transición inválida de la tabla → 422; fichar sin paciente → 422, otro día → 422
(con `freezeTime()`); ausente antes de la hora → 422; cancelar sin motivo → 422; un cancelado o un ausente liberan el horario (un alta
en el mismo horario → 201); el veterinario marca atendido un turno de su ficha (200) y de otra ficha (403); la recepción no marca
atendido (403) y el veterinario no confirma ni cancela (403); el log de consultas muestra el `FOR UPDATE` del turno (MySQL).
`UndoNoShowTest`: el mismo día → 200, confirmado, `no_show_at` nulo y la auditoría; al día siguiente → 422
`APPOINTMENT_NO_SHOW_NOT_UNDOABLE` (con `freezeTime()`); un turno que no está ausente → 422 `APPOINTMENT_INVALID_TRANSITION`; con el
horario tomado por otro turno → 409 y, si era un sobreturno, 200; el veterinario → 403; la ruta, con su excepción en
`ROUTES_WITH_ANOTHER_PERMISSION` (`tests/Feature/Access`).

**Mutaciones críticas:** permitir fichado → cancelado → fichado (quitar los finales); quitar el chequeo del paciente al fichar; quitar
el chequeo de la hora del ausente; quitar el `lockForUpdate` del turno; que el cancelado siga ocupando el horario; deshacer el ausente
sin el chequeo del día o sin revisar la superposición.

**Postman:** `syncvet-fase-03-turnos` suma las transiciones: confirmar, fichar (un turno de hoy, con la agenda abierta todo el día),
atender con el token del veterinario, ausente, cancelar con motivo, y una transición inválida → 422.

#### Pendiente para el cierre (3.2b)

Vacía: lo que tenía (cifras, desvíos, revisión, mutaciones y Postman) pasó al [resultado de la 3.2b](#entrega-32b) (R-053).

### 3.3 Turnos recurrentes

**Alcance.** Una serie de turnos al darlos (P1); cancelar uno o los siguientes.

**Migraciones:** ninguna (`series_id` existe, con índice).

**Reglas.**

| Regla | Detalle |
|---|---|
| Serie | `POST appointments` con `recurrence`: `every_weeks` (1 a 12, 1 por defecto) y uno de `count` (2 a 52) o `until` (una fecha, desde la del primer turno y hasta un año después). Las dos o ninguna → 422. Si `until` da más de 52 turnos → 422 |
| Fechas | El turno `i` es el primero más `7 × every_weeks × i` días, a la misma hora de reloj de Asunción y con la misma duración |
| Todo o nada (P1) | Cada fecha pasa por las reglas de la 3.2a (horario, bloqueos, superposición). Si alguna falla, no se crea ninguna: 409 `APPOINTMENT_SERIES_CONFLICT` con `errors.conflicts`, una lista de `{starts_at, reason, message}` (`reason`: `overlap`, `outside_hours` o `in_block`) |
| Sobreturno | `overbook` no se combina con `recurrence` (422, `prohibited`) |
| Después | Los turnos comparten `series_id` (UUID) y son independientes: cada uno se edita, se mueve y cambia de estado solo. Mover una serie entera = cancelar los siguientes y dar otra serie |
| Cancelar | `POST appointments/{appointment}/cancel` suma `scope`: `single` (por defecto) o `following` (este y los siguientes de la serie que estén a confirmar o confirmados; los fichados y los finales no cambian). En un turno sin serie, `following` es lo mismo que `single`. El motivo vale para todos, cada uno con su auditoría |
| Respuesta | El alta de una serie responde 201 con la lista de los turnos creados (`AppointmentResource`) y `meta.series_id`; la cancelación, el turno pedido y `meta.cancelled_count` |

**Errores** (`AppointmentException`):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `APPOINTMENT_SERIES_CONFLICT` | 409 | Hay fechas de la serie que no se pueden dar: revisá la lista. |

**Concurrencia.** La serie entera en una transacción, con el veterinario y la agenda bloqueados una vez (el mismo orden de la 3.2a). Cancelar los
siguientes bloquea los turnos de la serie con `lockForUpdate` por id ascendente.

**Tests** (`tests/Feature/Appointments/Series`): serie por cantidad y por fecha de fin (las fechas y la hora de reloj, también en una
fecha que cruza el 15/10, con `freezeTime()`); `count` y `until` juntos → 422; más de 52 → 422; una fecha ocupada, otra fuera del
horario y otra en un bloqueo → 409 con las tres en `errors.conflicts` y ningún turno creado; `overbook` con `recurrence` → 422; cancelar
`single` y `following` (los fichados de la serie no cambian); editar uno no toca los demás.

**Mutaciones críticas:** crear las fechas válidas aunque otra falle (no todo o nada); cortar la lista de conflictos en el primero;
`following` que cancela también los anteriores; `following` que cancela los fichados; el paso en días en lugar de semanas.

**Postman:** `syncvet-fase-03-turnos` suma una serie de 4, una con un conflicto (409 con la lista) y la cancelación de los siguientes.

#### Pendiente para el cierre (3.3)

Vacía: lo que tenía (desvíos, decisiones, revisión, mutaciones y Postman) pasó al [resultado de la 3.3](#entrega-33) (R-054).

### 3.4 Disponibilidad y cola del día

**Alcance.** Los horarios libres de una agenda (3.4 del alcance inicial) y la cola del día (3.5; P8). Solo lectura.

**Migraciones:** ninguna.

**Reglas.**

| Regla | Detalle |
|---|---|
| Disponibilidad | `GET schedules/{schedule}/availability` con `from` (una fecha, hoy por defecto) y `to` (una fecha, `from` por defecto), hasta 7 días; `service_id` opcional (la duración de ese servicio; si no, la del servicio de la agenda o `slot_minutes`). Para cada día, los horarios que empiezan cada `slot_minutes` desde el comienzo de cada tramo, en los que la duración entra en el tramo, no toca un bloqueo ni se superpone con un turno que ocupa el horario y que no empezaron (hoy, desde ahora). Una agenda inactiva → 422 en `schedule`. No bloquea nada: el alta vuelve a revisar |
| Respuesta de disponibilidad | `{schedule_id, duration_minutes, days: [{date, slots: [{starts_at, ends_at}]}]}`, con las horas en UTC (`Z`) y `date` en Asunción |
| Cola del día (P8) | `GET appointments/queue`, con `date` (hoy por defecto). Los turnos del día en la **sucursal actual** del usuario (sin sucursal actual → 422 `APPOINTMENT_QUEUE_NO_BRANCH`), sin los cancelados. Un usuario con ficha de veterinario ve por defecto los de su ficha; con `all=1`, los de toda la sucursal; la recepción y el administrador, toda la sucursal, con `veterinarian_id` opcional ([pregunta 25](#preguntas-del-diseño)). Orden por `starts_at` e id; sin paginar (hasta 200) |
| Respuesta de la cola | `AppointmentResource` con el paciente ampliado (especie y raza) y `checked_in_at` (para la demora) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `schedules/{schedule}/availability` | `schedules.availability` (nuevo) | `ScheduleAvailabilityRequest` | 200 | 404; 422 |
| GET | `appointments/queue` | `appointments.queue` (nuevo) | `AppointmentQueueRequest`: `date`, `all`, `veterinarian_id` | 200, lista | 422 `APPOINTMENT_QUEUE_NO_BRANCH`; 422 |

`appointments/queue` se registra antes de `appointments/{appointment}` para que no la tome el *route binding*.

**Errores** (`AppointmentException`):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `APPOINTMENT_QUEUE_NO_BRANCH` | 422 | Elegí tu sucursal actual para ver la cola del día. |

**Tests:** `ScheduleAvailabilityTest` (los horarios de un día con dos tramos; un turno, un cancelado, un bloqueo y un sobreturno; la
duración de un servicio que no entra al final del tramo; hoy, sin los horarios que ya pasaron, con `freezeTime()`; más de 7 días →
422; agenda inactiva → 422; un día sin tramos → `slots` vacío) y `AppointmentQueueTest` (sucursal actual; el veterinario ve los suyos y
con `all=1` los de la sucursal; la recepción ve todos; sin cancelados; sin sucursal actual → 422; nada de otra sucursal ni de otra
clínica).

**Mutaciones críticas:** no descontar los turnos que ocupan el horario; no descontar los bloqueos; mostrar los horarios que ya pasaron;
la cola sin el filtro de sucursal; el veterinario sin el filtro de su ficha.

**Postman:** `syncvet-fase-03-disponibilidad-y-cola` (la disponibilidad antes y después de un turno; la cola con el token de la
recepción y con el del veterinario).

#### Pendiente para el cierre (3.4)

Vacía: lo que tenía (desvíos, decisiones, revisión, mutaciones y Postman) pasó al [resultado de la 3.4](#entrega-34) (R-055).

## Documentación por entrega

| Entrega | Además de lo de `docs/README.md` (Mantenimiento) |
|---|---|
| 3.0 | `CLAUDE.md` §3 (la zona de la sesión y su excepción al `-03:00` fijo), `development/setup.md` y `development/deployment.md`; deuda 16 resuelta |
| 3.1 | `CLAUDE.md` §3 (orden clínica → agenda → veterinario → servicio) y una sección nueva §6.10 de agendas (las pendientes de diseño pasan a §6.11); §4 con `SCHEDULE_IN_USE` y `SCHEDULE_NOT_EDITABLE` si se usan fuera del módulo |
| 3.2a | `CLAUDE.md` §3 (orden agenda → veterinario → servicio → paciente → turno), §6.1 (`CLIENT_IN_USE` con turnos) y §6.10 (turnos); la regla de turnos sale de las pendientes de diseño |
| 3.2b | `CLAUDE.md` §6.10 (estados); la relación con la consulta, en el documento de la FASE 4 |
| 3.3 | `CLAUDE.md` §6.10 (series) |
| 3.4 | `CLAUDE.md` §6.10 (disponibilidad y cola); módulo de agenda y turnos COMPLETADO al cerrar la fase |

## Preguntas del diseño

Lo que el diseño no podía decidir solo, con su propuesta y la respuesta del usuario (R-048).

| # | Pregunta | Propuesta | Respuesta | Fecha |
|---|---|---|---|---|
| 13 | ¿La baja de una agenda es física o con `SoftDeletes`? | Física: sin turnos no hay historia que conservar, queda en la auditoría y libera al veterinario y al servicio (con `SoftDeletes`, la fila eliminada seguiría contando como uso en `VeterinarianService::USAGE` y `ServiceCatalogService::USAGE`). Es una excepción a "datos maestros con `SoftDeletes`" (`CLAUDE.md` §3) | Aprobada (usuario) | 2026-11-10 |
| 14 | Una agenda con turnos, ¿puede cambiar de sucursal o de veterinario? | No: 422 `SCHEDULE_NOT_EDITABLE`; se crea otra agenda y se inactiva esta (los turnos guardan la sucursal y el veterinario). El servicio sí cambia | Aprobada (usuario) | 2026-11-10 |
| 15 | ¿Un veterinario puede tener dos turnos a la misma hora en dos agendas distintas? | No: 409 `APPOINTMENT_OVERLAP` también entre sus agendas, con la fila del veterinario bloqueada (`lockForUpdate`) para serializar sus turnos. Un sobreturno lo permite | Aprobada (usuario), con dos precisiones: el sobreturno (`overbook: true`) también se superpone con otro turno del mismo veterinario en otra agenda; y la fila del veterinario se bloquea (`lockForUpdate`) **antes** que la de la agenda ([orden de bloqueo](#orden-de-bloqueo)) | 2026-11-10 |
| 16 | ¿Se dan turnos en el pasado? | Desde el comienzo del día de hoy (para cargar a alguien que llegó sin turno o un turno de esta mañana); un día anterior → 422 | Aprobada (usuario) | 2026-11-10 |
| 17 | Un turno confirmado que se mueve, ¿vuelve a "a confirmar"? | No: conserva el estado (la recepción lo mueve hablando con el cliente) | Aprobada (usuario) | 2026-11-10 |
| 18 | ¿El veterinario marca atendido cualquier turno? | Solo los de su ficha o los de agendas sin veterinario; el administrador y quien tiene `appointments.update`, cualquiera. Fichar, cualquiera (con `appointments.check-in`) | Aprobada (usuario). Cambiada en la 3.2b (R-053): fichar queda restringido igual que marcar atendido; la regla se aplica por el rol (sin ficha, o con la ficha eliminada, solo las agendas sin veterinario), con 403 `APPOINTMENT_NOT_YOURS` ([resultado de la 3.2b](#entrega-32b)) | 2026-11-10 |
| 19 | Un ausente marcado por error, ¿se puede deshacer? | No en el MVP: si el cliente llega tarde, se le da otro turno (o un sobreturno) | Cambiada (usuario): el ausente se deshace el mismo día del turno, por el administrador y la recepción (`appointments.no-show`), con `POST appointments/{appointment}/undo-no-show`: vuelve a confirmado, `no_show_at` en `null`, queda en la auditoría; pasado el día, 422 `APPOINTMENT_NO_SHOW_NOT_UNDOABLE` ([3.2b](#32b-estados-del-turno)) | 2026-11-10 |
| 20 | ¿`attended_at` y `no_show_at` como columnas o alcanza la auditoría? | Columnas `datetime` (migración de la 3.2b): la cola, la demora y el ausentismo los consultan; la auditoría guarda quién | Aprobada (usuario) | 2026-11-10 |
| 21 | ¿Los turnos cuentan como uso del cliente? Y la baja del paciente, ¿con bloqueo? | Sí: `CLIENT_IN_USE` suma los turnos, y su mensaje cambia a "El cliente tiene pacientes, turnos, consultas o facturas y no puede eliminarse." (§6.1). La baja del paciente y la del cliente revisan el uso con la fila bloqueada, dentro de su transacción (hoy lo revisan antes, sin bloqueo) | Aprobada (usuario) | 2026-11-10 |
| 22 | Un bloqueo sobre turnos que ya existen, ¿se rechaza? | No: se crea, y la respuesta lista los turnos afectados (`affected_appointments`) para que la recepción los reprograme | Aprobada (usuario) | 2026-11-10 |
| 23 | ¿`ScheduleBlock` también es auditable? | Sí, como `Schedule` y `ScheduleHour` (P11): un bloqueo cierra la agenda y conviene saber quién lo puso o lo sacó | Aprobada (usuario) | 2026-11-10 |
| 24 | ¿Se inactiva una agenda con turnos futuros? | Sí: los turnos siguen y la recepción los reprograma o cancela; la agenda no recibe turnos nuevos | Aprobada (usuario) | 2026-11-10 |
| 25 | ¿La cola del veterinario es una restricción o una vista por defecto? | Vista por defecto (sus turnos, y `all=1` para la sucursal): el veterinario ya lista todos los turnos con `appointments.index` (P8 no pide ocultárselos). Si se quiere restringir, se le quita `appointments.index` | Aprobada (usuario) | 2026-11-10 |

## Criterio de cierre

Dos turnos simultáneos en el mismo horario: uno entra y el otro recibe 409 (test de concurrencia en MySQL). Tests de cada transición,
válida e inválida. Cada regla con su test con el rol de la operación, y cada ruta nueva con su acceso por rol y su aislamiento por
clínica. La deuda 16 resuelta. Los pendientes de Postman de la FASE 2 probados. Suite en verde en SQLite y en MySQL, y `composer audit`
sin avisos.

**Avance (R-051).** Cumplido en la 3.2a el primer punto: `AppointmentConcurrencyTest` (MySQL) lanza dos altas simultáneas del mismo
horario en dos procesos de PHP aparte, con una agenda con veterinario y con una agenda solo de servicio, y en los dos casos una entra
(201) y la otra recibe 409 `APPOINTMENT_OVERLAP`. También la deuda 16 (R-049) y los pendientes de Postman de la FASE 2 (R-050). Faltan
los tests de cada transición (3.2b), el resto de las rutas nuevas (3.2b a 3.4) y `composer audit` al cierre de la fase.

**Avance (R-053).** Cumplidos en la 3.2b los tests de cada transición, válida e inválida (`AppointmentTransitionTest`, con la matriz
completa), y las 6 rutas de los estados con su acceso por rol y su aislamiento por clínica. Faltan las rutas de la 3.3 y la 3.4 y
`composer audit` al cierre de la fase.

**Avance (R-054).** La 3.3 no suma rutas (la serie y el alcance van en rutas existentes); suma dos tests de dos procesos en MySQL
(dos series que comparten una fecha y dos cancelaciones de la misma serie) y el aislamiento por clínica de la cancelación con alcance.
Faltan las rutas de la 3.4 y `composer audit` al cierre de la fase.

**Avance (R-055).** La 3.4 suma 2 rutas (la disponibilidad y la cola del día), con su acceso por rol (`RoleAccessTest`) y su
aislamiento por clínica (una agenda o un servicio de otra clínica, 404 o 422; ningún turno de otra clínica en la cola), y cada regla
con su test con el rol de la operación. Cumplido todo menos `composer audit` sin avisos, que va en el cierre de la fase.

**Cumplido (R-056).** En el cierre de la fase, `composer audit` sin avisos. El criterio, punto por punto, en el
[cierre de la fase](#cierre-de-la-fase-3).

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado, una entrega por vez.

```text
Implementa la entrega 3.X de la FASE 3 según docs/03-plan-desarrollo/fases/backend/fase-03-agenda-y-turnos.md (diseño aprobado).

- Respeta las respuestas de R-047 y las de las preguntas del diseño.
- Reutiliza lo existente (CatalogLock, CatalogName, ListFilters, SearchTerm, ApiDateTime, AppDateTime, PersonName, AppointmentStatus).
- Superposición: dentro de DB::transaction, lockForUpdate sobre la agenda antes de leer el rango; conflicto → 409 APPOINTMENT_OVERLAP.
- Fechas: la base guarda la hora de Asunción y la API responde en UTC; los horarios semanales son hora de reloj de Asunción.
- Sigue CLAUDE.md. Corre la suite completa en SQLite (y en MySQL si la entrega toca migraciones, búsquedas, concurrencia o autenticación) e infórmame. No hagas commit.
```

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
*deadlock* en el alta y el mover de turnos ([pendientes que deja la 3.1](#32a-turnos-únicos)).

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
| 1 | Las transiciones viven en `App\Enums\AppointmentTransition` (cada acción con sus estados de origen, `origins()`, y su destino, `to()`). El diseño ([estados del turno](#estados-del-turno)) dice que `AppointmentStatus` suma `canTransitionTo()`: se sumó con `isFinal()`, sin uso, y los dos se borraron en la revisión (hallazgo 5) | El destino solo no alcanza: confirmar y deshacer el ausente llevan los dos a confirmado, desde estados distintos | Implementación; aceptado por el usuario |
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
[pregunta 18](#preguntas-del-diseño), "Fichar, cualquiera"): un veterinario sin `appointments.update` ficha solo los turnos de su
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
  [Relación con la consulta](#relación-con-la-consulta-fase-4): la consulta se abre desde un turno fichado
  (`consultations.appointment_id`, única) y lo marca atendido en la misma transacción; `POST appointments/{appointment}/attend` sigue
  para los turnos sin historia clínica; un turno atendido no se cancela y cancelar la consulta no lo vuelve a fichado; la consulta sin
  turno (urgencia) sigue siendo posible. El orden de bloqueo de esa transacción se suma al de la FASE 3 (`CLAUDE.md` §3).
* El veterinario y el servicio que use la consulta, con un `sharedLock` (como los turnos, `CLAUDE.md` §3).
* Fuera del MVP o para más adelante, sin fase asignada: el ausente automático (P12), la reserva online (`online_booking` y
  `AppointmentSource::ONLINE`) y mover una serie entera (R-054).
