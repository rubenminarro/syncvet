# FASE 4 — Historia clínica: resultados

El resultado de cada entrega y el cierre de la fase. El diseño y las decisiones, en
[`fase-04-historia-clinica.md`](fase-04-historia-clinica.md).

## Resultado

Lo completa el cierre de cada entrega ([`docs/03-plan-desarrollo/prompts/7-cierre.md`](../../prompts/7-cierre.md)): archivos,
endpoints, tests y aserciones, desvíos del diseño y por qué.

### Entrega 4.1

La consulta: abrirla desde el turno o sin turno, editarla abierta, el listado y el detalle. Implementación en
`feature/back-4-1-consulta`, creada desde `844a7c0` (el ajuste de R-099), en el commit WIP `f9f95d4`; revisión con correcciones en
`36aef4c`; el cierre va en un commit aparte. Decisiones en [R-100](../../../01-arquitectura/decisiones/registro.md). Las reglas, en
[`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Archivos.**

* Nuevos: la migración `2026_11_21_000000_adjust_consultations_for_phase_4`; `ConsultationService`, `AppointmentOwnership`
  (las reglas de `attend`, compartidas), `ConsultationController`, `PermissionsSyncCommand`; `StoreConsultationRequest`,
  `UpdateConsultationRequest`, `IndexConsultationRequest` y `ConsultationMessages`; `ConsultationResource` y
  `ShowConsultationResource`.
* Cambiados: `Consultation` (`treatment`, `instructions`, casts; deuda 2), `Appointment` (la relación `consultation`),
  `ConsultationException`, `AppointmentResource` y `ShowAppointmentResource`, `AppointmentController` y `AppointmentQueueService`
  (`withExists`), `AppointmentService` (`withRetries` con mensaje de conflicto y `loadDetail` con la consulta),
  `AppointmentTransitionService` (usa `AppointmentOwnership`), `RolesAndPermissionsSeeder` y `tests/Fixtures/clinic-roles.json`,
  `routes/api.php`, `lang/es/validation.php` y `PostmanCleanupCommand` (las consultas de la corrida, primero).
* Tests nuevos: `tests/Feature/Consultations/` (`ConsultationStoreTest`, `ConsultationUpdateTest`, `ConsultationIndexTest`,
  `ConsultationShowTest`, `ConsultationLockTest` y `ConsultationConcurrencyTest`), `PermissionsSyncCommandTest`,
  `ConsultationsPhase4MigrationTest` y `tests/Concerns/InteractsWithConsultations`. Cambiados: `RoleAccessTest`, `RouteBindingTest`,
  `PostmanCleanupCommandTest`, `AppointmentQueueTest` y `DemoAgendaCommandTest`.
* Documentación: `consultas.md` (§6.12), `turnos.md`, `pendientes-de-diseno.md`, `02-requisitos/README.md`, la guía de Postman y la
  colección `syncvet-fase-04-consultas`, `setup.md` (`permissions:sync`), `backend/README.md`, los `CLAUDE.md` (el presupuesto por
  entrega de R-100) y `testing.md`.

**Endpoints.** `GET`/`POST consultations` y `GET`/`PUT|PATCH consultations/{consultation}`, sin `DELETE` (405). Rutas 78 → 82,
permisos 135 → 136 (`consultations.show-clinical`), migraciones 36 → 37. En la FASE 3, campos aditivos: `consultation` en el detalle
del turno y `has_consultation` en el listado y en la cola.

**Tests.** Corrida de `composer test` del cierre (2026-11-20, sobre `36aef4c`): 1590 casos, 1572 en verde y 18 omitidos (de MySQL),
15776 aserciones, en una sola corrida. Frente al cierre de la F3 del frontend (R-098: 1511 casos y 15085 aserciones): 79 casos más.

* `ConsultationStoreTest` (22): desde el turno fichado (atendido y su auditoría), el dueño actual, el atendido el mismo día y otro
  día, los otros estados, el 409 (también por el índice único), otro veterinario, la agenda sin veterinario, el administrador, el
  veterinario del pedido, el fallecido, sin turno, sin sucursal, el veterinario obligatorio y activo, uno de turno o paciente, otra
  clínica, `consulted_at`, la recepción, los códigos y los campos del turno.
* `ConsultationUpdateTest` (10, con *data provider*: 26 casos): la edición parcial, el administrador, otra ficha, cerrada o anulada,
  los límites y sus bordes, `consulted_at`, los campos fijos, la recepción y el `DELETE`.
* `ConsultationIndexTest` (9): los últimos 30 días, los filtros, el turno, los filtros mal formados, la vista de la recepción y la
  clínica, otra clínica y el conteo de consultas (con el administrador y la recepción; y el de los turnos con `has_consultation`).
* `ConsultationShowTest` (6): la vista del administrador (también sin el permiso en su rol, por la Gate), del veterinario, de la
  recepción y de un rol propio; otra clínica.
* `ConsultationLockTest` (5): el orden de bloqueo al abrir, con y sin turno, y al editar; el deadlock y la espera vencida; el turno
  que cambia mientras se bloquea.
* `ConsultationConcurrencyTest` (1, solo MySQL): dos aperturas simultáneas del mismo turno, una 201 y otra 409.
* `PermissionsSyncCommandTest` (4): una base de la FASE 3 queda igual a la foto; dos corridas; los usuarios conservan sus roles;
  `--dry-run`.
* `ConsultationsPhase4MigrationTest` (3): las columnas, el índice con nombre propio y, en MySQL, la bajada y la subida.

**MySQL, Newman y la base local** (en la implementación, sobre `f9f95d4`; la revisión no tocó la migración, la concurrencia ni la
colección). MySQL (`syncvet_test_mysql`), los tests afectados: 125 de 125, 1146 aserciones. Newman, `syncvet-fase-04-consultas`: 31
pedidos y 54 aserciones, sin fallas; la limpieza borró 2 consultas, 2 turnos, la agenda, el paciente, el cliente, la ficha y el
servicio, y dio de baja el usuario. `permissions:sync` en la base local: el permiso en el administrador y el veterinario, no en la
recepción; la segunda corrida, sin cambios.

**Mutaciones** (4, en la implementación; antes del presupuesto de R-100), todas detectadas y revertidas con el editor:

* Sacar el paso del turno a atendido: `ConsultationStoreTest::test_opening_from_a_checked_in_appointment_marks_it_attended_in_the_same_transaction`.
* Aceptar el turno atendido otro día: `test_an_appointment_attended_another_day_is_rejected`.
* Dar `consultations.show-clinical` a la recepción: `ClinicRolesTest` (la foto), `PermissionsSyncCommandTest`, `ConsultationIndexTest` y
  `ConsultationShowTest`.
* Saltear el fallecido: `test_a_deceased_patient_gets_no_new_consultation`.

**Desvíos del diseño y precisiones.**

1. Un `veterinarian_id` distinto del que corresponde (el del turno o la ficha de quien abre) responde 422, en lugar de ignorarse.
2. La vista administrativa no lleva las claves clínicas (no van en `null`); el motivo es administrativo; en el listado, lo clínico es
   el diagnóstico.
3. `RoleAccessTest` acepta un 403 de negocio (`APPOINTMENT_NOT_YOURS`, `CONSULTATION_NOT_YOURS`) como permitido: el permiso ya pasó.
4. Código compartido con la FASE 3: `withRetries` con un mensaje de conflicto propio; las reglas de `attend`, en
   `AppointmentOwnership` (revisión), sin cambios de comportamiento; `AppointmentQueueTest` reconoce la consulta de la cola con el
   `exists`.
5. Deuda 2 en `Consultation`: la temperatura con `decimal:1` y los signos vitales enteros.
6. `has_consultation`, solo en el listado y la cola; el detalle del turno lleva `consultation`.
7. Editar: el administrador por su rol; un rol propio con `consultations.update` y sin ficha, 403 `CONSULTATION_NOT_YOURS`.
8. `permissions:sync --dry-run`, que no estaba en el diseño.

**Revisión.** Sin hallazgos en el índice único, el orden de bloqueo, `consulted_at` ni la salida de textos clínicos. Correcciones: el
permiso clínico se pregunta una vez por pedido (el test de conteo, también con la recepción) y las reglas de `attend` pasaron a
`AppointmentOwnership`. Las dos corridas completas anteriores tuvieron una falla de detalle cada una (`AppointmentQueueTest`, la
consulta de la cola con el `exists`; `DemoAgendaCommandTest`, que arma `AppointmentTransitionService` a mano), corregidas; la corrida
del cierre, en verde en una sola corrida (R-100).

### Entrega 4.2

Cierre, enmiendas y anulación de la consulta. Implementación en `feature/back-4-2-cierre`, creada desde `9e7c478` (el cierre de la
4.1), en el commit WIP `54a161e`; revisión con correcciones en `6c00790`; el cierre va en un commit aparte. Decisiones en
[R-101](../../../01-arquitectura/decisiones/registro.md). Las reglas, en [`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Archivos.**

* Nuevos: la migración `2026_11_21_000001_create_consultation_amendments_table`; el modelo `ConsultationAmendment` (inmutable);
  `AmendConsultationRequest` y `CancelConsultationRequest`.
* Cambiados: `ConsultationService` (`lockEditable()`, el lugar único de la inmutabilidad; `close()`, `amend()` y `cancel()`),
  `ConsultationController`, `ConsultationException`, `ConsultationMessages`, `ShowConsultationResource` (las enmiendas, con el permiso
  clínico), `Consultation` (la relación `amendments`), `AuditLog` (el tipo `consultation_amendment`), `UserService` (el uso de
  `created_by`), `RolesAndPermissionsSeeder` y `tests/Fixtures/clinic-roles.json` (`amend` entra, `reopen` sale), `routes/api.php`,
  `lang/es/validation.php` y `PostmanCleanupCommand` (las enmiendas, antes que sus consultas).
* Tests nuevos: `ConsultationCloseTest`, `ConsultationAmendmentTest`, `ConsultationCancelTest` y `ConsultationImmutabilityTest`.
  Cambiados: `PermissionsSyncCommandTest`, `PostmanCleanupCommandTest`, `ConsultationLockTest` y `RouteBindingTest`.
* Documentación: `consultas.md`, la guía de Postman y la colección `syncvet-fase-04-consultas` ampliada, y `backend/CLAUDE.md` (no
  migrar "para los tests").

**Endpoints.** `POST consultations/{consultation}/close`, `/amendments` y `/cancel`. Rutas 82 → 85, permisos 136 → 136
(`consultations.amend` entra, `consultations.reopen` sale), migraciones 37 → 38. El detalle de la consulta suma `amendments`.

**Tests.** Corrida de `composer test` de la revisión (2026-11-20, sobre el código de `6c00790`; el cierre solo cambia documentación):
1631 casos, 1613 en verde y 18 omitidos (de MySQL), 16085 aserciones, en una sola corrida. Frente al cierre de la 4.1 (R-100: 1590
casos y 15776 aserciones): 41 casos más.

* `ConsultationCloseTest` (7, con *data provider*: 9 casos): el veterinario cierra y el peso pasa al paciente (auditado), sin peso, el
  paciente fallecido, el motivo y el diagnóstico obligatorios (uno, el otro y los dos), solo abierta, el administrador y otra ficha,
  la recepción.
* `ConsultationAmendmentTest` (7; 14 casos): la enmienda y una nota general (lo escrito no cambia, auditadas), el administrador y otra
  ficha, abierta y anulada (422), los límites y la validación, la recepción que no enmienda ni ve las enmiendas, la inmutabilidad del
  modelo.
* `ConsultationCancelTest` (5; 8 casos): anular abierta y cerrada (visible, ítems pendientes a `no_charge`, el turno atendido), un ítem
  facturado intacto, anular otra vez, el motivo, el veterinario y la recepción (403).
* `ConsultationImmutabilityTest` (1; 6 casos): PATCH, PUT y cerrar con una cerrada y una anulada, sin cambios.
* `ConsultationLockTest`: cerrar (paciente → consulta), enmendar (solo la consulta) y anular (consulta → ítems).

**MySQL, Newman y la base local** (en la implementación, sobre `54a161e`; la revisión no tocó la migración, el código de los bloqueos
ni la colección). MySQL (`syncvet_test_mysql`), los tests afectados: 60 de 60, 397 aserciones. Newman, `syncvet-fase-04-consultas`:
45 pedidos y 84 aserciones, sin fallas; la limpieza borró la enmienda, las 2 consultas, los turnos y el resto de la corrida.
`permissions:sync` en la base local: `consultations.reopen` fuera y `consultations.amend` en el administrador y el veterinario. La base
local, intacta en la revisión (2 clínicas, los 6 usuarios de la demo, las 5 agendas "Demo" y 310 turnos).

**Mutaciones** (2, R-100), detectadas y revertidas con el editor:

* Cerrar sin diagnóstico: `ConsultationCloseTest` ("sin diagnóstico" y "sin los dos").
* El PATCH sobre una cerrada: `ConsultationImmutabilityTest` (PATCH y PUT, cerrada y anulada) y `ConsultationUpdateTest`.

**Desvíos del diseño y precisiones.**

1. El peso no pasa a un paciente fallecido (§6.2: solo cambian su fecha y sus notas); la consulta se cierra igual.
2. Cerrar bloquea el paciente antes que la consulta (§3, paciente → consulta).
3. `CONSULTATION_AMENDMENT_IMMUTABLE` (409), nuevo: lo lanza el modelo, como `AUDIT_LOG_IMMUTABLE`; ninguna ruta lo dispara.
4. La enmienda responde 201 con el detalle de la consulta.
5. `field`: los campos editables de una abierta (`consulted_at` incluido) o `null`.
6. Anular pasa a `no_charge` solo los ítems pendientes; uno facturado no se toca.
7. `postman:cleanup` borra las enmiendas con el constructor de consultas (sin auditoría), solo detrás de su guardia.
8. Anular una cerrada no revierte el peso copiado al paciente (decisión del usuario en la revisión).
9. Pendiente de la 4.4: las aplicaciones de una consulta anulada se anulan con ella.

**Revisión.** Sin hallazgos en el orden de bloqueo (cerrar, editar, enmendar y anular siguen el de la 4.1 y el del cambio de dueño:
paciente → consulta → ítem). Ya estaban los tests de enmendar una anulada y de la recepción sin las enmiendas. Correcciones: el test
de bloqueo suma la enmienda; el test de la negativa de `postman:cleanup`, las enmiendas y las consultas; `consultas.md`, el peso al
anular; `backend/CLAUDE.md`, no migrar "para los tests".

### Entrega 4.3

Los servicios usados en la consulta. Implementación en `feature/back-4-3-servicios`, creada desde `c5e0873` (el cierre de la 4.2), en
el commit WIP `15d4346`; revisión con correcciones en `d847243`; el cierre va en un commit aparte. Decisiones en
[R-102](../../../01-arquitectura/decisiones/registro.md). Las reglas, en [`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Archivos.**

* Nuevos: `ConsultationItemService` (sumar, editar y quitar; reusa `lockEditable()`), `App\Support\ConsultationTotals` (el cálculo y
  los redondeos, por ítem y de la consulta), `ConsultationItemController`, `ConsultationItemResource`, `StoreConsultationItemRequest`,
  `UpdateConsultationItemRequest` y `ConsultationItemMessages`.
* Cambiados: `ShowConsultationResource` (`items` y `totals`, administrativos), `ConsultationService::loadDetail` (los ítems),
  `ConsultationItem` (la cantidad con `decimal:3`; deuda 2), `routes/api.php`, `lang/es/validation.php` y `PostmanCleanupCommand` (cuenta
  los ítems).
* Tests nuevos: `ConsultationItemTest`. Cambiados: `ConsultationImmutabilityTest`, `ConsultationLockTest`, `RouteBindingTest`,
  `RoleAccessTest` (el parámetro `item`) y `PostmanCleanupCommandTest`.
* Documentación: `consultas.md`, la guía de Postman y la colección `syncvet-fase-04-consultas` ampliada.

**Endpoints.** `POST consultations/{consultation}/items`, `PATCH` y `DELETE consultation-items/{item}`. Rutas 85 → 88; permisos (136) y
migraciones (38), sin cambios. El detalle de la consulta suma `items` y `totals`.

**Tests.** Corrida de `composer test` de la revisión (2026-11-20, sobre el código de `d847243`; el cierre solo cambia documentación):
1675 casos, 1657 en verde y 18 omitidos (de MySQL), 16460 aserciones, en una sola corrida. Frente al cierre de la 4.2 (R-101: 1631
casos y 16085 aserciones): 44 casos más.

* `ConsultationItemTest` (12, con *data providers*: 34 casos): el precio del catálogo, el veterinario de la consulta y los totales;
  los totales con BCMath (enteros, descuento, redondeos hacia arriba y hacia abajo, sin `float`, medio guaraní, descuento igual al
  subtotal, precio cero); las validaciones; `null` al editar (422); los totales de la consulta (sin los `no_charge`); el servicio
  inactivo; el veterinario de los honorarios activo; editar y quitar (auditados); la consulta cerrada o anulada; otra ficha y el
  administrador; la recepción (ve, no escribe); el servicio usado (`SERVICE_NAME_NOT_EDITABLE`, `SERVICE_IN_USE`, el precio del ítem).
* `ConsultationImmutabilityTest`: sumar, editar y quitar ítems con una cerrada o anulada (6 casos más; 12 en total).
* `ConsultationLockTest`: el veterinario (del pedido o de la consulta) y el servicio antes que la consulta, y la consulta antes que el
  ítem.
* `RouteBindingTest`: un ítem de otra clínica (`PATCH` y `DELETE`) y sumar a una consulta ajena, 404.

**MySQL y Newman.** MySQL (`syncvet_test_mysql`), los tests afectados por los bloqueos nuevos: 56 de 56 (350 aserciones) en la
implementación y 41 de 41 (276) en la revisión. Newman, `syncvet-fase-04-consultas`: 55 pedidos y 104 aserciones, sin fallas, en la
implementación y otra vez en la revisión (con el veterinario por defecto y los totales); la limpieza, sin datos residuales.

**Mutaciones** (2, R-100), detectadas y revertidas con el editor:

* Aceptar un servicio inactivo: `ConsultationItemTest::test_an_inactive_service_is_rejected`.
* Sumar un ítem a una consulta cerrada: `ConsultationImmutabilityTest` ("sumar un ítem", cerrada y anulada) y `ConsultationItemTest`
  (la consulta cerrada o anulada, y otra ficha).

**Desvíos del diseño y precisiones.**

1. Editar y quitar, por el ítem (`consultation-items/{item}`, como la tabla de endpoints del diseño); sumar, anidado bajo la consulta;
   sin `PUT` del ítem.
2. Las respuestas traen el detalle de la consulta (201 al sumar, 200 al editar y al quitar).
3. Sin `veterinarian_id`, el ítem toma el veterinario de la consulta (revisión; en la implementación quedaba en `null`).
4. Los ítems y los totales, solo en el detalle (también para la recepción); el listado no los trae.
5. Un subtotal de más de 12 dígitos, 422 en `quantity`.
6. Editar con `null` en la cantidad, el precio, el descuento o el veterinario, 422 (revisión; en la implementación dejaba el valor).
7. La cantidad con `decimal:3` en el modelo (deuda 2).
8. `postman:cleanup` cuenta los ítems de la corrida (se borran en cascada con su consulta).
9. Los totales de la consulta (`totals`), nuevos en la revisión, con el cálculo en `ConsultationTotals`.

**Revisión.** Sin errores en el diff. Correcciones por decisión del usuario: el veterinario de la consulta por defecto, `null` al
editar (422) y los totales de la consulta con los redondeos en un solo lugar. El uso del servicio ya tenía su test.

### Entrega 4.4

Las aplicaciones (vacunas, desparasitaciones y medicamentos). Implementación en `feature/back-4-4-aplicaciones`, creada desde
`1b7399f` (el cierre de la 4.3), en el commit WIP `1e63f46`; revisión con correcciones en `5ae5098`; el cierre va en un commit aparte.
Decisiones en [R-103](../../../01-arquitectura/decisiones/registro.md), con la del usuario sobre quién anula. Las reglas, en
[`pacientes.md`](../../../02-requisitos/reglas/pacientes.md) y [`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Archivos.**

* Nuevos: la migración `2026_11_21_000002_add_cancellation_to_patient_applications`; `PatientApplicationService`,
  `PatientApplicationController`, `PatientApplicationResource` y `PatientApplicationException`; `StorePatientApplicationRequest`,
  `IndexPatientApplicationRequest`, `CancelPatientApplicationRequest` y `PatientApplicationMessages`.
* Cambiados: `ConsultationService` (`ensureEditable()`, separado de `lockEditable()`; anular la consulta anula sus aplicaciones; el
  detalle carga las aplicaciones), `ShowConsultationResource` (`applications`), `PatientApplication` (la anulación; deuda 2),
  `UserService` (el uso de `cancelled_by`), `RolesAndPermissionsSeeder` y `tests/Fixtures/clinic-roles.json`
  (`patient-applications.cancel`), `routes/api.php`, `lang/es/validation.php` y `PostmanCleanupCommand` (las aplicaciones de la corrida).
* Tests nuevos: `PatientApplicationTest`. Cambiados: `ConsultationImmutabilityTest`, `ConsultationLockTest`, `RouteBindingTest`,
  `RoleAccessTest` (el parámetro `application` y `PATIENT_APPLICATION_NOT_YOURS` como 403 de negocio), `PermissionsSyncCommandTest` y
  `PostmanCleanupCommandTest`.
* Documentación: `pacientes.md`, `consultas.md`, la guía de Postman y la colección nueva `syncvet-fase-04-aplicaciones`.

**Endpoints.** `GET` y `POST patients/{patient}/applications`, `POST patient-applications/{application}/cancel`. Rutas 88 → 91,
permisos 136 → 137, migraciones 38 → 39. El detalle de la consulta suma `applications`.

**Tests.** Corrida de `composer test` de la revisión (2026-11-20, sobre el código de `5ae5098`; el cierre solo cambia documentación):
1711 casos, 1693 en verde y 18 omitidos (de MySQL), 16807 aserciones, en una sola corrida. Frente al cierre de la 4.3 (R-102: 1675
casos y 16460 aserciones): 36 casos más.

* `PatientApplicationTest` (16, con *data providers*: 31 casos): con consulta (lote, próxima dosis, el veterinario de la consulta,
  auditada) y sin consulta (otra ficha y el administrador); las reglas de la consulta (otro paciente, otra ficha, cerrada y anulada); el
  paciente fallecido; las validaciones; la recepción (lista, no carga ni anula); el listado (filtros, anuladas marcadas, sin N+1); anular
  con consulta abierta (otra ficha, la suya, otra vez, el administrador) y de una cerrada (422); sin consulta (la propia de hoy, la de otro
  y la de ayer, el administrador); el borde de la medianoche; el motivo; las aplicaciones en el detalle de la consulta; y anularlas con la
  consulta.
* `ConsultationImmutabilityTest`: cargar una aplicación con una cerrada o anulada y anular una de una cerrada (3 casos más; 15 en total).
* `ConsultationLockTest`: cargar (veterinario → paciente → consulta), anular (consulta → aplicación) y anular la consulta (ítems →
  aplicaciones).
* `RouteBindingTest`: las aplicaciones de un paciente de otra clínica y anular una ajena, 404.

**MySQL, Newman y la base local** (en la implementación, sobre `1e63f46`; la revisión no tocó la migración, los bloqueos ni la
colección). MySQL (`syncvet_test_mysql`), los tests afectados: 74 de 74, 512 aserciones. Newman, `syncvet-fase-04-aplicaciones`: 29
pedidos y 43 aserciones, sin fallas; la limpieza borró las 2 aplicaciones, la consulta, el paciente, el cliente y la ficha, y dio de baja
el usuario. `permissions:sync` en la base local: `patient-applications.cancel` en el administrador y el veterinario, no en la recepción.

**Mutaciones** (2, R-100), detectadas y revertidas con el editor:

* Permitir anular una aplicación de una consulta cerrada: `ConsultationImmutabilityTest` ("anular una aplicación closed") y dos de
  `PatientApplicationTest`.
* Que anular la consulta no anule sus aplicaciones: `PatientApplicationTest::test_cancelling_the_consultation_cancels_its_applications`.

**Desvíos del diseño y precisiones.**

1. Quién anula (decisión del usuario, R-103), con `PATIENT_APPLICATION_NOT_YOURS` (403), un `error_code` nuevo.
2. Al anular, primero "ya anulada" y después la ficha y el estado de la consulta (`lockEditable()` separado en el bloqueo y
   `ensureEditable()`).
3. El veterinario de la aplicación: el de la consulta o la ficha de quien carga; no se recibe en el pedido.
4. "El mismo día" es el de la carga (`created_at`, en Asunción).
5. El listado pagina como los demás, sin tope de días ni rango por defecto y sin `search`.
6. `next_dose_due_at` posterior al día de aplicación en Asunción.
7. `postman:cleanup` borra las aplicaciones de la corrida antes que sus consultas y pacientes.
8. La anulación y `dose_number` como entero en el modelo (deuda 2).
9. Las aplicaciones, resumidas, en el detalle de la consulta (revisión: un faltante frente al diseño).

**Revisión.** Ya estaban cubiertos otra ficha (403 `CONSULTATION_NOT_YOURS`), la regla del mismo día y la recepción. Correcciones: el
test del borde de la medianoche y las aplicaciones en el detalle de la consulta, con su test.

### Entrega 4.5

Los adjuntos de la consulta (imágenes y PDF, en un disco privado). Implementación en `feature/back-4-5-adjuntos`, creada desde
`2fa780c` (el cierre de la 4.4), en el commit WIP `36f825a` (2026-11-22); revisión con correcciones en `fac086f` (2026-11-24); el
cierre va en un commit aparte. Decisiones en [R-104](../../../01-arquitectura/decisiones/registro.md). Las reglas, en
[`consultas.md`](../../../02-requisitos/reglas/consultas.md).

**Archivos.**

* Nuevos: `ConsultationAttachmentService`, `ConsultationAttachmentController` y `StoreConsultationAttachmentRequest`; el disco
  `consultation_attachments` en `config/filesystems.php`.
* Cambiados: `ConsultationAttachment` (`Auditable`, la ruta oculta y como `"(archivo)"` en la auditoría), `AuditLog`
  (`consultation_attachment` en `AUDITABLE_TYPES`), `ConsultationService` (el detalle carga los adjuntos), `ShowConsultationResource`
  (`attachments`, sin la ruta, solo con `consultations.show-clinical`), `OrphanFileService` y `PruneOrphanFilesCommand` (el disco
  nuevo), `PostmanCleanupCommand` (los adjuntos de la corrida y sus archivos), `routes/api.php` y `lang/es/validation.php`.
* Tests nuevos: `ConsultationAttachmentTest`. Cambiados: `ConsultationImmutabilityTest`, `RouteBindingTest`, `RoleAccessTest` (el
  parámetro `attachment`) y `PostmanCleanupCommandTest`.
* Documentación: `consultas.md`, la guía de Postman y la colección `syncvet-fase-04-consultas` ampliada, con sus archivos de prueba en
  `docs/05-pruebas/postman/archivos/`.

**Endpoints.** `POST consultations/{consultation}/attachments` (multipart, campo `file`), `GET
consultations/{consultation}/attachments/{attachment}` (el archivo; las dos con `scopeBindings`) y `DELETE
consultation-attachments/{attachment}`. Rutas 91 → 94; permisos (137) y migraciones (39), sin cambios. El detalle de la consulta suma
`attachments`. Sin `error_code` nuevos.

**Tests.** Corrida de `composer test` de la revisión (2026-11-24, sobre el código de `fac086f`; el cierre solo cambia documentación):
1749 casos, 1731 en verde y 18 omitidos (de MySQL), 17154 aserciones, en una sola corrida. Frente al cierre de la 4.4 (R-103: 1711
casos y 16807 aserciones): 38 casos más.

* `ConsultationAttachmentTest` (17, con *data providers*: 33 casos): cada tipo permitido con el nombre generado; el tipo por el
  contenido (un PNG llamado `.pdf`, un PDF llamado `.png`; HTML, texto y SVG rechazados); 10 MB y 10 MB más un byte (413), y el que
  corta PHP; el archivo obligatorio; el tope de 20; otra ficha no sube ni quita; la descarga con y sin permiso (los encabezados y el
  nombre); los adjuntos en el detalle solo con `consultations.show-clinical`; el adjunto de otra consulta (404); el archivo que falta y
  la ruta ajena (404); quitar una ruta ajena sin borrarla; la descarga de una cerrada y de una anulada; quitar con la auditoría; el
  saneado del nombre; el disco privado; `files:prune-orphans`.
* `ConsultationImmutabilityTest`: subir y quitar un adjunto con una cerrada o anulada (4 casos más; 19 en total).
* `RouteBindingTest`: subir, descargar y quitar los adjuntos de otra clínica, 404.

**Newman** (en la implementación, sobre `36f825a`; la revisión no tocó lo que usa la colección): `syncvet-fase-04-consultas` ampliada,
66 pedidos y 132 aserciones, sin fallas (la primera corrida tuvo una falla del script de la colección, corregida); la limpieza borró el
adjunto que quedaba con su archivo. MySQL no se corrió: sin migraciones ni un orden de bloqueo nuevo (consulta → adjunto, como consulta
→ ítem).

**Mutaciones** (2 en la implementación y 1 en la revisión), detectadas y revertidas con el editor:

* Validar el tipo por la extensión: los 5 casos de `test_the_type_comes_from_the_content_not_from_the_name`.
* Descargar sin el permiso: `test_download_with_and_without_permission` y 5 de `RoleAccessTest`.
* Quitar sin el control de la carpeta (`owns()`): `test_remove_never_deletes_a_foreign_file` (revisión).

**Desvíos del diseño y precisiones.**

1. El tope de 20 responde 422 `VALIDATION_ERROR` en `file`, sin un `error_code` nuevo.
2. El archivo no se vuelve a codificar (es un estudio clínico): lo protegen el tipo por el contenido, el nombre generado, el disco
   privado y la descarga con `nosniff`, `Content-Security-Policy: default-src 'none'; sandbox` y `Cache-Control: private, no-store`.
3. La descarga es `attachment`, con el nombre saneado y la extensión del tipo detectado; `original_name` guarda el nombre subido,
   saneado, para mostrar.
4. El saneado del nombre: sin carpetas, sin controles (C0 y C1), sin marcas de dirección del texto (revisión) ni `: * ? " < > |`, con
   los espacios juntos, sin puntos ni espacios en los bordes y hasta 255 caracteres; vacío o con UTF-8 inválido, "adjunto".
5. El archivo se escribe antes de la transacción y se borra si falla; quitar borra el archivo después del commit, y nunca uno con una
   ruta fuera de la carpeta de su consulta.
6. El archivo que falta, 404 `RESOURCE_NOT_FOUND`.
7. Una consulta cerrada o anulada conserva sus adjuntos, y se descargan.
8. La auditoría, con la ruta como `"(archivo)"`.
9. El aislamiento por clínica, en `RouteBindingTest`.
10. `postman:cleanup` borra los adjuntos de la corrida con su auditoría y sus archivos después de la transacción.
11. Los tests usan archivos reales: `UploadedFile::fake()` informa el tipo por la extensión.
12. **El límite de PHP:** con `upload_max_filesize=8M` y `post_max_size=10M` (el PHP 8.3.30 de una de las PC), un archivo de más de
    8 MB responde 413 antes de Laravel. Hace falta al menos `10M` y `11M`. En la PC de la revisión (PHP 8.3.16) están en `2G`. En
    producción, lo mismo en su PHP y en el servidor web (FASE 10).

**Revisión.** Sin hallazgos graves; la arquitectura, los permisos, el orden de bloqueo y la separación por clínica, correctos.
Correcciones: el saneado del nombre (los controles C1 y las marcas de dirección: `informe‮fdp.exe` se veía como `informeexe.pdf`), los
tests de quitar una ruta ajena y de la descarga de una cerrada o anulada, y los temporales de `ConsultationImmutabilityTest`.

### Entrega 4.6

El historial del paciente (consultas y aplicaciones juntas). Implementación en `feature/back-4-6-historial`, creada desde `0b83449`
(el cierre de la 4.5), en el commit WIP `fb45c21` (2026-11-24); la revisión, con una corrección en los tests, en `7aef80b`; el cierre va en un
commit aparte. Decisiones en [R-105](../../../01-arquitectura/decisiones/registro.md). Las reglas, en
[`pacientes.md`](../../../02-requisitos/reglas/pacientes.md).

**Archivos.**

* Nuevos: `PatientHistoryService`, `PatientHistoryController`, `IndexPatientHistoryRequest` y `PatientHistoryResource`; la ruta en
  `routes/api.php`.
* Tests nuevos: `PatientHistoryTest`. Cambiados: `InteractsWithApiRoutes` (un grupo de permisos por middleware, `permission_groups`,
  y `permissionsReach()`), `ClinicRolesTest` y `RoleAccessTest` (varios permisos a la vez en una ruta) y `RouteBindingTest`.
* Documentación: `pacientes.md`, la guía de Postman y la colección `syncvet-fase-04-aplicaciones` ampliada.

**Endpoints.** `GET patients/{patient}/history` (nombre `patients.history`), con `consultations.index` **y**
`patient-applications.index` (dos middleware: hacen falta los dos). Rutas 94 → 95; permisos (137) y migraciones (39), sin cambios. Sin
`error_code` nuevos.

**Tests.** Corrida de `composer test` de la revisión (2026-11-24, sobre el código de `7aef80b`, con la corrección de la revisión en
`RoleAccessTest`; el cierre solo agrega documentación): 1767 casos, 1749 en verde y 18 omitidos (de MySQL), 17374 aserciones, en una
sola corrida. Frente al cierre de la 4.5 (R-104: 1749 casos y 17154 aserciones): 18 casos más.

* `PatientHistoryTest` (11, con *data providers*: 17 casos): consultas y aplicaciones juntas, de la más reciente a la más vieja, sin
  las de otro paciente; la misma fecha (la consulta primero y después el id mayor); la paginación (de a 20, hasta 100) y los
  `per_page` inválidos (422); las anuladas, marcadas; el diagnóstico y el peso solo con `consultations.show-clinical`; un fallecido
  conserva su historial; un paciente sin registros; con uno solo de los dos permisos, 403; sin N+1.
* `RouteBindingTest`: el historial de un paciente de otra clínica, 404.

**Newman** (en la implementación, sobre `fb45c21`; la revisión no tocó lo que usa la colección): `syncvet-fase-04-aplicaciones`
ampliada, 32 pedidos y 53 aserciones, sin fallas (la primera corrida tuvo dos errores del script de la colección, corregidos); la
limpieza no dejó datos. MySQL no se corrió: sin migraciones, concurrencia ni búsqueda de texto (el `UNION ALL` se prueba en MySQL con
la suite completa del cierre de la fase).

**Mutaciones** (2, en la implementación), detectadas y revertidas con el editor:

* Quitar `permission:patient-applications.index`: `PatientHistoryTest::test_it_requires_both_permissions` y `ClinicRolesTest`.
* Dar el peso sin el permiso clínico:
  `PatientHistoryTest::test_the_diagnosis_and_the_weight_only_with_the_clinical_permission`.

**Desvíos del diseño y precisiones.**

1. **Los dos permisos a la vez:** dos middleware (`permission:consultations.index` y `permission:patient-applications.index`), no
   `permission:a|b` (uno u otro). La infraestructura de los tests de permisos suponía un `permission:` por ruta: ahora junta un grupo
   por middleware (uno de cada grupo) y `ROUTES_WITH_ANOTHER_PERMISSION` acepta una lista.
2. **El peso, solo con `consultations.show-clinical`** (confirmado por el usuario): el diseño nombraba solo el diagnóstico; el peso es
   parte del examen clínico en el detalle (`ShowConsultationResource`) y la recepción ve el resumen administrativo.
3. **La forma de cada elemento:** `type`, `occurred_at`, `cancelled` y el elemento en `consultation` o en `application` (el otro,
   `null`); la consulta, con `ConsultationResource` más `weight_kg`; la aplicación, con `PatientApplicationResource`.
4. **El orden en la misma fecha** (confirmado por el usuario): la consulta antes que la aplicación y después el id mayor.
5. **La página en la base:** un `UNION ALL` de dos consultas livianas (tipo, id y fecha, cada una con el scope de la clínica) y después
   los modelos de la página con sus relaciones: 4 consultas más el conteo.
6. **Sin filtros:** solo `per_page` (1 a 100, 20 por defecto; los listados, 10) y `page`.
7. **La base local de una PC** tenía pendientes las 3 migraciones de la FASE 4: se migró y se corrió `permissions:sync` para Newman.

**Revisión.** Sin fallas en el código: las relaciones del historial son las de los listados (`withTrashed()` en el cliente, el
paciente y el veterinario) y el `UNION ALL` aplica el scope de la clínica. Una corrección en los tests: `RoleAccessTest::list()`
nombraba los permisos de la ruta como `a|b` (uno u otro); ahora usa los grupos (`a y b` en el historial), solo en los mensajes de
falla.

## Cierre de la FASE 4

Completada el 2026-11-24 (R-106). La clínica registra la atención de cada paciente:
- la consulta, abierta desde el turno fichado (que pasa a atendido), desde uno atendido el mismo día o sin turno;
- editarla mientras está abierta, solo su veterinario o el administrador;
- el cierre, con el motivo y el diagnóstico, que pasa el peso al paciente;
- las enmiendas de una consulta cerrada (sin reapertura) y la anulación;
- los servicios usados, con el precio del catálogo y los totales;
- las aplicaciones (vacunas, desparasitaciones y medicamentos, con o sin consulta), con su anulación;
- los adjuntos (imágenes y PDF, en un disco privado);
- el historial del paciente.

La recepción ve solo lo administrativo (`consultations.show-clinical`). Las ramas se encadenaron (`feature/back-4-diseno` →
`feature/back-4-1-consulta` → … → `feature/back-4-6-historial`), todas en GitHub, y se integran a `main` con un merge `--no-ff` desde
`feature/back-4-6-historial`, autorizado por el usuario (R-106). Las ramas de la fase se conservan.

| Entrega | Contenido | Commits | Suite al cerrar (SQLite) |
|---|---|---|---|
| Diseño | Análisis, preguntas 1 a 18 y entregas | `7962f97`, `cb49f7e` (R-099), `844a7c0` | — |
| [4.1](#entrega-41) | La consulta | `f9f95d4`, `36aef4c` (cierre `9e7c478`) | 1590 casos; 15776 aserciones |
| [4.2](#entrega-42) | Cierre, enmiendas y anulación | `54a161e`, `6c00790` (cierre `c5e0873`) | 1631 casos; 16085 |
| [4.3](#entrega-43) | Los servicios usados | `15d4346`, `d847243` (cierre `1b7399f`) | 1675 casos; 16460 |
| [4.4](#entrega-44) | Las aplicaciones | `1e63f46`, `5ae5098` (cierre `2fa780c`) | 1711 casos; 16807 |
| [4.5](#entrega-45) | Los adjuntos | `36f825a`, `fac086f` (cierre `0b83449`) | 1749 casos; 17154 |
| [4.6](#entrega-46) | El historial del paciente | `fb45c21`, `7aef80b` (cierre `8c5674b`) | 1767 casos; 17374 |

**Rutas, permisos y migraciones.** Las rutas de la API pasan de 78 a 95: 4 en la 4.1, 3 en la 4.2, 3 en la 4.3, 3 en la 4.4, 3 en
la 4.5 y 1 en la 4.6. Los permisos, de 135 a 137 (`consultations.show-clinical`, `consultations.amend` en lugar de
`consultations.reopen` y `patient-applications.cancel`). Las migraciones, de 36 a 39. Los `error_code` nuevos (`CONSULTATION_*`,
`PATIENT_APPLICATION_*` y los demás de la fase) están en [`consultas.md`](../../../02-requisitos/reglas/consultas.md) y [`pacientes.md`](../../../02-requisitos/reglas/pacientes.md).

**Suite al cerrar la fase** (2026-11-24, código de `7aef80b`; los commits posteriores del backend solo cambian documentación): 1767
casos con los *data providers*, en verde.
- SQLite (`composer test`, en la revisión de la 4.6): 17374 aserciones y 18 omitidos (solo de MySQL).
- MySQL (`composer test:mysql`, `syncvet_test_mysql`, en serie, en el cierre): 1767 de 1767 en verde, 17485 aserciones y sin omitidos.

Frente al cierre de la FASE 3 del backend y la F3 del frontend (R-098: 1511 casos y 15085 aserciones), son 256 casos más. `composer
audit`: "No security vulnerability advisories found.".

**Newman del cierre** (2026-11-24, `node docs/05-pruebas/newman-todas.mjs`, base de desarrollo, servidor en el puerto 8001): las 16
colecciones, con 589 pedidos y 1275 aserciones, sin fallas. Cada limpieza borró lo de su corrida.

| Colección | Pedidos | Aserciones |
|---|---|---|
| `syncvet-fase-01-clientes` | 24 | 48 |
| `syncvet-fase-01-especies` | 45 | 99 |
| `syncvet-fase-01-foto` | 23 | 60 |
| `syncvet-fase-01-pacientes` | 30 | 63 |
| `syncvet-fase-02-auditoria` | 16 | 47 |
| `syncvet-fase-02-configuracion` | 24 | 67 |
| `syncvet-fase-02-servicios` | 27 | 64 |
| `syncvet-fase-02-sucursales` | 44 | 123 |
| `syncvet-fase-02-usuarios` | 54 | 138 |
| `syncvet-fase-02-veterinarios` | 32 | 75 |
| `syncvet-fase-03-agendas` | 35 | 68 |
| `syncvet-fase-03-disponibilidad` | 50 | 81 |
| `syncvet-fase-03-series` | 26 | 44 |
| `syncvet-fase-03-turnos` | 61 | 113 |
| `syncvet-fase-04-aplicaciones` | 32 | 53 |
| `syncvet-fase-04-consultas` | 66 | 132 |

**Regresión del frontend** (2026-11-24, sobre el código del cierre). La primera corrida de `npm test` tuvo **una falla**:
`src/features/audit/search.test.ts` compara los tipos auditables del frontend con `AuditLog::AUDITABLE_TYPES` del backend. La 4.2 y la
4.5 sumaron `consultation_amendment` y `consultation_attachment`, y `frontend/src/features/audit/types.ts` no los tenía, así que el
filtro de la auditoría no los ofrecía. Se corrigió con los dos tipos, en el orden del backend ("Enmienda de consulta" y "Adjunto de
consulta"), en un commit aparte. Después, la corrida completa del frontend quedó en verde:
- `typecheck`, `lint` y `build`: sin errores ni avisos;
- `npm test`: 1835 tests en 89 archivos;
- `FictitiousDocumentsTest`: 3 tests y 69 aserciones;
- Playwright completo: 46 de 46, en una sola corrida.

El desvío salió porque las entregas del backend no corren la suite del frontend. El test que lo detectó es el que estaba pensado para
eso.

**Criterio de cierre**, punto por punto:

| Criterio | Evidencia |
|---|---|
| El flujo turno fichado → consulta (el turno queda atendido) → servicios y aplicaciones → cierre, también sin turno | `ConsultationStoreTest`, `ConsultationItemTest`, `PatientApplicationTest` y `ConsultationCloseTest`; de punta a punta, la colección `syncvet-fase-04-consultas` (66 pedidos) |
| Una cerrada no se edita (se corrige con enmiendas) y una anulada queda visible | `ConsultationImmutabilityTest` (cada ruta de escritura con una cerrada o anulada, 422), `ConsultationAmendmentTest` y `ConsultationCancelTest`; el historial marca las anuladas (`PatientHistoryTest`) |
| El administrador y el veterinario ven lo clínico; la recepción, solo lo administrativo | `ConsultationShowTest`, `ConsultationAttachmentTest` y `PatientHistoryTest` (con y sin `consultations.show-clinical`); `RoleAccessTest` (las 95 rutas por rol) |
| `composer test` y `composer test:mysql` en serie, en verde | La suite del cierre, en las dos bases |
| `composer audit` sin avisos | 2026-11-24: "No security vulnerability advisories found."; nada que actualizar |
| Newman con todas las colecciones, sin fallas y sin dejar datos | 16 colecciones, 589 pedidos y 1275 aserciones, con la limpieza de cada corrida |
| Las reglas en `docs/02-requisitos/reglas/consultas.md` | `consultas.md` (y el historial, en `pacientes.md`) |

**Deudas** ([`deuda-tecnica.md`](../../deuda-tecnica.md)). Ninguna nueva ni resuelta en la fase. La 2 se cumplió en los modelos que
usó la fase: `Consultation` (4.1), `ConsultationItem` (4.3), `PatientApplication` (4.4) y los modelos nuevos `ConsultationAmendment`
(4.2) y `ConsultationAttachment` (4.5); sigue abierta para las fases siguientes. Abiertas al cerrar: 2, 3, 5, 6, 15, 17, 19, 20, 21,
25, 26 y 29.

**Decisiones de la fase.** En el [registro](../../../01-arquitectura/decisiones/registro.md):
- R-099: el diseño y las 18 preguntas;
- R-100 (4.1), con el presupuesto por entrega;
- R-101 (4.2), R-102 (4.3), R-103 (4.4), R-104 (4.5) y R-105 (4.6);
- R-106: el cierre y el merge.

**Pendiente para lo que sigue.** Decidir con el usuario entre el frontend de la historia clínica (F4, con su análisis y su diseño) y el análisis de la FASE 5 del backend. Los
productos de la consulta y las recetas, en las fases del roadmap que los tienen (preguntas 9 y 10).
