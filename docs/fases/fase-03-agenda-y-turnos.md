# FASE 3 — Agenda y turnos

Etapa: Atención. Estado: **PENDIENTE**: diseño **aprobado** por el usuario el 2026-11-10, con las preguntas de negocio 1 a 12 (R-047)
y las [preguntas del diseño](#preguntas-del-diseño) 13 a 25 respondidas (R-048). Entregas 3.0 (R-049) y 3.1 (R-050) cerradas; sigue la 3.2a. El estado vigente de cada fase está en
[`docs/roadmap/README.md`](../roadmap/README.md).

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
| 3.2a | Turnos únicos: alta, edición y mover, sin superposición, con el horario y los bloqueos; sobreturno | 3.2 | PENDIENTE |
| 3.2b | Estados: confirmar, fichar, atendido, ausente (y deshacerlo el mismo día) y cancelar | 3.3 | PENDIENTE |
| 3.3 | Turnos recurrentes: la serie entera o nada; cancelar uno o los siguientes | 3.2 | PENDIENTE |
| 3.4 | Disponibilidad por agenda y cola del día | 3.4 y 3.5 | PENDIENTE |

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

Análisis con [`docs/project/principios.md`](../project/principios.md).

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

Implementación en `feature/fase-03-02a-turnos` (commit WIP "WIP 3.2a turnos, sin revisar"), sin revisar ni validar con Postman.
Lo que el cierre tiene que llevar al resultado, a `CLAUDE.md` y al registro.

**Cifras.** Migraciones 34 → 35 (`2026_11_10_000000_add_contact_and_overbook_to_appointments`); rutas 66 → 70 (`GET`/`POST
appointments`, `GET`/`PATCH`/`PUT appointments/{appointment}`; `DELETE` → 405); permisos 130 → 131 (`appointments.overbook`, del
administrador y la recepción). Suite del 2026-11-10: 1200 casos; en SQLite (`composer test`), 12752 aserciones con 9 omitidos (los 8
de MySQL de antes y `AppointmentConcurrencyTest`); en MySQL (`composer test:mysql`), 12791 aserciones sin omitidos.
`tests/Feature/Appointments`: 88 casos (Store 33, ScheduleRules 10, Overlap 11, Overbook 5, Update 16, Index 5, Lock 7, Concurrency 1).

**Desvíos y decisiones de la implementación** (a aceptar o corregir en la revisión):

| # | Desvío o decisión | Motivo |
|---|---|---|
| 1 | El alta y la edición también toman el **cliente** con `sharedLock`, después del paciente (agenda → paciente → cliente → turno) | La baja del cliente ahora lo bloquea antes de revisar el uso (pregunta 21); sin el bloqueo, un turno con cliente y sin paciente podía quedar en un cliente eliminado. Ninguna operación bloquea un cliente y después un paciente |
| 2 | Una edición que no mueve (notas, motivo, servicio, paciente) no bloquea el veterinario ni las agendas: solo el servicio, el paciente y el cliente que llegan y el turno | El horario no cambia; se respeta el orden con un subconjunto. Mover (llega `schedule_id`, `starts_at` o `ends_at`) bloquea todo como dice el diseño |
| 3 | Mover sin `ends_at` conserva la duración del turno | El diseño solo dice que cambiar el servicio no recalcula `ends_at` |
| 4 | Al mover (también dentro de la misma agenda) se exigen la agenda activa y su veterinario activo; sin mover, un turno de una agenda inactiva se sigue editando | Un turno movido es como uno nuevo; pregunta 24 (la recepción reprograma los turnos de una agenda inactiva) |
| 5 | Reintentos: hasta 3 (`AppointmentService::ATTEMPTS`) si cambian el veterinario o el servicio de la agenda, la agenda, el paciente o el estado del turno entre la lectura y el bloqueo, o si MySQL informa un *deadlock* o una espera vencida (`DetectsConcurrencyErrors`: 40001, 1213 y 1205). Después, 409 `CONFLICT` ("La agenda cambió mientras se guardaba el turno: volvé a intentarlo.") con `AppointmentException` | Pendiente de la 3.1 (hallazgo 1): nunca un 500. `CONFLICT` de turnos va a `CLAUDE.md` §4 |
| 6 | Al editar, el paciente que el turno ya tiene se acepta aunque haya fallecido (el formulario lo reenvía); otro fallecido → 422 | Como las especies y razas inactivas (§6.2) |
| 7 | Con paciente, mandar otro `client_id` (también sin `patient_id`) → 422 en `patient_id`, "El paciente no es de ese cliente." | El cliente del turno es el dueño de ese momento |
| 8 | `contact_name`: al menos una letra, sin caracteres de control ni `<` `>` (regex propia) | El diseño dice "texto libre con al menos una letra" |
| 9 | Listado: el rango filtra por `starts_at`; con solo `from` o solo `to`, el otro es el mismo día; `status` acepta uno (`status=confirmed`) o una lista (`status[]=…`) | Precisa el diseño |
| 10 | Al mover, `is_overbooked` se recalcula (un sobreturno movido a un horario libre deja de serlo); `overbook` sin mover se ignora (no pide nada más que el permiso) | P2 |
| 11 | `StoredScheduleBlockResource` suma `contact_name` y `contact_phone` a los turnos afectados | Cierra el desvío 1 de la 3.1 |
| 12 | La baja del cliente sigue contando solo los pacientes no eliminados (como hasta ahora); suma los turnos y revisa todo con la fila bloqueada | Pregunta 21, sin cambiar lo demás |
| 13 | Mensajes que el diseño no fija: "La agenda es obligatoria.", "El paciente no existe.", "El cliente no existe.", "El paciente falleció: no se le pueden dar turnos.", "El nombre y el teléfono de contacto van solo en un turno sin cliente ni paciente.", "Ingresá el nombre y el teléfono de contacto juntos.", "El fin del turno debe ser posterior a su inicio.", "El turno no puede durar más de 720 minutos.", "El turno tiene que empezar y terminar el mismo día.", "El origen debe ser reception, phone o whatsapp: la reserva online no está disponible.", "El rango no puede superar los 31 días." y los de los campos `prohibited` | — |

**Tests de concurrencia.** `AppointmentLockTest` (SQLite y MySQL): el orden veterinario → servicio → agenda → paciente → cliente y
los tramos, bloqueos y superposición leídos después de la agenda; mover con las dos agendas por id y el turno después; las bajas de
cliente y paciente con su fila bloqueada antes de revisar los turnos; el reintento si cambia el veterinario de la agenda y 409
después de 3; un *deadlock* simulado (un *listener* que hace fallar el bloqueo del veterinario con el mensaje de MySQL 1213) que se
repite y, si sigue, 409 en el alta y al mover. `AppointmentConcurrencyTest` (solo MySQL) es el criterio de cierre con **dos
conexiones reales**: dos procesos de PHP aparte (`tests/Support/concurrent-request.php`) piden el mismo turno por la API mientras una
tercera conexión del test tiene tomada la fila del veterinario; el test espera a verlos a los dos en el `SELECT … FOR UPDATE` del
veterinario (`information_schema.processlist`), suelta el bloqueo y verifica un 201 y un 409 `APPOINTMENT_OVERLAP`. Para que los
procesos vean los datos, el test no usa la transacción de `RefreshDatabase` (`$connectionsToTransact = []`) y borra al final los
datos de su clínica (comprobado: la base de test queda vacía).

**Mutaciones** (cada una con tests que fallan): `<` por `<=` en la superposición (`AppointmentOverlapTest`, los que se tocan);
contar los cancelados y ausentes como ocupados, y no contar los fichados y atendidos (`AppointmentOverlapTest`, por estado); quitar
el chequeo de bloqueos (3 tests de ScheduleRules, Overbook y Update); quitar el permiso del sobreturno (`AppointmentOverbookTest`);
quitar el veterinario de la superposición entre agendas (`AppointmentOverlapTest`, pregunta 15); quitar el `lockForUpdate` de la
agenda (en MySQL, los dos de orden de `AppointmentLockTest`; el de dos procesos sigue pasando porque el veterinario ya serializa); quitar
el `lockForUpdate` del veterinario (en MySQL, `AppointmentConcurrencyTest` y 4 de `AppointmentLockTest`); la baja del cliente sin
bloquear la fila (`AppointmentLockTest`) o sin contar los turnos (`ClientDeleteTest`). En SQLite los tests de orden no ven el
`FOR UPDATE` (como en la 3.1): esa parte solo se verifica en MySQL.

**Deuda 2 (`Appointment`).** Relaciones y casts revisados: coinciden con las claves foráneas; los instantes con `AppDateTime`;
suma `contact_name`, `contact_phone` e `is_overbooked` (cast `boolean`) y los valores por defecto de la base en `$attributes`
(estado a confirmar, origen recepción, sin sobreturno). `Client` suma la relación `appointments()`.

**Postman.** `syncvet-fase-03-turnos` (34 requests), **no corrida en esta sesión**. Antes de correrla: `php artisan migrate` y
`php artisan db:seed --class=RolesAndPermissionsSeeder`. No prueba "mover un turno cancelado → 422": la API todavía no cancela
(3.2b); lo cubre `AppointmentUpdateTest`. Los turnos, los dos clientes y el paciente de cada corrida quedan en la base (un turno no se
elimina y es uso del cliente y del paciente); la agenda, el servicio, el veterinario y el usuario se inactivan, y el bloqueo se
elimina.

**Documentación pendiente para el cierre**, como en las entregas anteriores: `CLAUDE.md` §1, §3 (orden con el cliente, reintentos),
§4 (`APPOINTMENT_*` y `CONFLICT` de turnos), §6.1 (`CLIENT_IN_USE` con turnos y la fila bloqueada), §6.2 (la baja del paciente con la
fila bloqueada), §6.10 (turnos) y §6.11 (sale la regla de turnos); la guía de Postman; el registro (R-051), el roadmap, los módulos y
el handoff. En esta entrega solo se agregó a `CLAUDE.md` §2 la regla de escribir los pendientes en el repositorio (pedido del usuario).

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
| 18 | ¿El veterinario marca atendido cualquier turno? | Solo los de su ficha o los de agendas sin veterinario; el administrador y quien tiene `appointments.update`, cualquiera. Fichar, cualquiera (con `appointments.check-in`) | Aprobada (usuario) | 2026-11-10 |
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

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado, una entrega por vez.

```text
Implementa la entrega 3.X de la FASE 3 según docs/fases/fase-03-agenda-y-turnos.md (diseño aprobado).

- Respeta las respuestas de R-047 y las de las preguntas del diseño.
- Reutiliza lo existente (CatalogLock, CatalogName, ListFilters, SearchTerm, ApiDateTime, AppDateTime, PersonName, AppointmentStatus).
- Superposición: dentro de DB::transaction, lockForUpdate sobre la agenda antes de leer el rango; conflicto → 409 APPOINTMENT_OVERLAP.
- Fechas: la base guarda la hora de Asunción y la API responde en UTC; los horarios semanales son hora de reloj de Asunción.
- Sigue CLAUDE.md. Corre la suite completa en SQLite (y en MySQL si la entrega toca migraciones, búsquedas, concurrencia o autenticación) e infórmame. No hagas commit.
```

## Resultado

Lo completa el cierre de cada entrega ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y
aserciones, desvíos del diseño y por qué.

### Entrega 3.0

Implementación en `feature/fase-03-00-zona-mysql`, creada desde `docs/fase-03-diseno`, revisada en una sesión de Claude Code aparte
de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-049)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-049)). La implementación y las correcciones quedaron en dos commits WIP (`7c92097`
y `a0f9a4e`), y el cierre va en un commit aparte. Decisiones en R-047 y [R-049](../decisions/registro.md).

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
cierre van en el commit de cierre. Decisiones en [R-050](../decisions/registro.md).

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
