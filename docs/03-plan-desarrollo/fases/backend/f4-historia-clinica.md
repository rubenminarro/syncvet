# FASE 4 — Historia clínica (backend): diseño

Etapa: Atención. Estado: **DISEÑO PROPUESTO** el 2026-11-20 en `feature/back-4-diseno` (desde `main` en `6fb4805`, con la F3 del
frontend integrada, R-098), **sin aprobar**: ninguna entrega empieza hasta que el usuario responda las
[preguntas del diseño](#preguntas-del-diseño) (las que bloquean la 4.1 están marcadas) y lo apruebe. Completa el
[esqueleto de la fase](fase-04-historia-clinica.md) (el objetivo, las 6 entregas del roadmap y 3 preguntas); dónde vive cada uno lo
decide la [pregunta 1](#preguntas-del-diseño). El estado vigente de cada fase está en [`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

El veterinario atiende: abre la consulta desde el turno fichado (o sin turno, en una urgencia), registra el motivo, la anamnesis, el
examen físico con el peso y los signos vitales, el diagnóstico, el tratamiento y las indicaciones, lo que usó (los servicios) y las
vacunas y desparasitaciones aplicadas, adjunta estudios y cierra la consulta. La consulta cerrada no se borra ni se reescribe; el
historial del paciente reúne sus consultas y sus aplicaciones. Es el eslabón "Consulta → Diagnóstico y plan → Servicios + productos
usados + aplicaciones → Cierre" de la [regla de oro](../../../00-vision/principios.md): lo que se registra acá lo usan después el
stock (FASE 5), los documentos (FASE 6), los recordatorios (FASE 7) y la facturación (FASE 8).

## Alcance

**Dentro de la FASE 4:**

* La consulta (`CON-`): abrirla desde un turno fichado (y marcarlo atendido) o sin turno; editarla mientras está abierta; cerrarla;
  la corrección de una cerrada (reabrir o enmendar, [pregunta 4](#preguntas-del-diseño)); anularla con motivo; el listado y el
  detalle.
* Los servicios usados en la consulta (`consultation_items` de tipo `service`, con el precio del catálogo y el estado de cobro
  `pending`), solo con la consulta abierta.
* Las aplicaciones (`patient_applications`): vacunas, desparasitaciones internas y externas y medicamentos, con el lote y la próxima
  dosis copiados como texto.
* Los adjuntos de la consulta (`consultation_attachments`), en un disco privado, como la foto del paciente.
* El historial del paciente: sus consultas y sus aplicaciones en orden, para toda la clínica.
* El soporte de la limpieza de Newman (`postman:cleanup`) y la colección de la fase.

**Fuera de la FASE 4** (según el roadmap):

| Tema | Fase | En la FASE 4 |
|---|---|---|
| Productos, stock, lotes y su consumo desde la consulta | 5 (Inventario) | Sin ítems de producto ni descuento de stock ([pregunta 9](#preguntas-del-diseño)); la aplicación guarda el lote como texto |
| Recetas, certificados, constancias en PDF y plantillas | 6 (Documentos) | Las indicaciones quedan como texto en la consulta; `patient_documents` no se toca |
| Recordatorios de la próxima dosis y avisos al tutor | 7 (Recordatorios) | Se guarda `next_dose_due_at`; nadie lo envía todavía |
| Cobro, factura y SIFEN | 8 y 9 | El ítem queda `pending`; nada lo factura |
| Internación, cirugías con parte quirúrgico, laboratorio integrado | Sin fase (fuera del MVP) | — |
| Las pantallas de la historia clínica | Frontend, después de esta fase (D2, R-058) | Solo la API |

## Lo que ya existe

**El esquema** (del paquete base, R-001; deuda 2: los modelos salieron del esquema y se revisan al usarlos):

| Tabla | Columnas que importan | Observación |
|---|---|---|
| `consultations` | `code`, `branch_id`, `patient_id`, `client_id` (el dueño de ese momento), `appointment_id` (nullable y **única**), `veterinarian_id`, `consulted_at` (`datetime`), `reason` (255), `history` (texto, la anamnesis), `weight_kg` (6,2), `temperature_c` (4,1), `heart_rate`, `respiratory_rate`, `mucous_membranes` (50), `capillary_refill_time` (20), `body_condition_score`, `physical_exam`, `diagnosis`, `plan` (textos), `status` (`ConsultationStatus`: `open`, `closed`, `cancelled`), `created_by`, `closed_at`/`closed_by`, `cancelled_at`/`cancelled_by`/`cancellation_reason` | Índices: `clinic_id`+`code` única, `patient_id`+`consulted_at`, `branch_id`+`consulted_at`, `veterinarian_id`+`consulted_at`, `clinic_id`+`status`. `closed_at` y `cancelled_at` son `timestamp`: la regla vigente pide `datetime` para la hora de negocio (§3, deuda 17). No hay "tratamiento" ni "indicaciones" separados: solo `plan` ([pregunta 6](#preguntas-del-diseño)) |
| `consultation_items` | `type` (`ConsultationItemType`: `service`, `product`), `service_id`, `product_id`, `product_lot_id`, `veterinarian_id` (honorarios), `description`, `quantity` (12,3), `unit_price`, `discount`, `total` (14,2), `tax_rate`, `billing_status` (`pending`, `invoiced`, `no_charge`) | `cascadeOnDelete` con la consulta |
| `consultation_attachments` | `path`, `original_name`, `mime_type`, `size_bytes`, `uploaded_by` | Sin `deleted_at`; `cascadeOnDelete` |
| `patient_applications` | `patient_id`, `consultation_id` (nullable), `consultation_item_id`, `product_id`, `type` (`PatientApplicationType`: `vaccine`, `internal_deworming`, `external_deworming`, `medication`), `name`, `lot_number`, `lot_expires_at`, `dose`, `route`, `applied_at`, `veterinarian_id`, `dose_number`, `next_dose_due_at` (fecha), `notes`, `created_by` | Sin estado ni anulación ([pregunta 12](#preguntas-del-diseño)). Índices: `patient_id`+`type`+`applied_at`, `clinic_id`+`next_dose_due_at` |
| `patient_documents`, `document_templates` | Recetas y documentos emitidos | FASE 6 |

**Los modelos** `Consultation`, `ConsultationItem`, `ConsultationAttachment` y `PatientApplication` existen, con `BelongsToClinic` y
`Auditable` (sus tipos ya están en `AuditLog::AUDITABLE_TYPES`), sin Service, Request, Resource ni rutas. `DocumentSeries::CONSULTATION`
(`CON-`) existe. Las bajas ya los cuentan como uso: `ClientService` y `PatientService` (409 `*_IN_USE` con consultas),
`VeterinarianService` (consultas, ítems, aplicaciones y documentos), `UserService` (`created_by`, `closed_by`, `cancelled_by`,
`uploaded_by`) y `ServiceCatalogService` (el nombre fijo de un servicio usado en `consultation_items`).

**Los permisos** ya están en `RolesAndPermissionsSeeder::PERMISSIONS` y en la foto `tests/Fixtures/clinic-roles.json`:

| Permiso | Administrador | Recepción | Veterinario |
|---|---|---|---|
| `consultations.index`, `consultations.show` | Sí (`Gate::before`) | **Sí** ([pregunta 7](#preguntas-del-diseño)) | Sí |
| `consultations.store`, `consultations.update`, `consultations.close` | Sí | No | Sí |
| `consultations.reopen`, `consultations.cancel` | Sí | No | No |
| `consultation-items.store`, `.update`, `.destroy` | Sí | No | Sí |
| `consultation-attachments.index`, `.store`, `.destroy` | Sí | No | Sí |
| `patient-applications.index` | Sí | Sí | Sí |
| `patient-applications.store` | Sí | No | Sí |

**El turno** (FASE 3, [`turnos.md`](../../../02-requisitos/reglas/turnos.md)): `checked_in → attended` con `POST
appointments/{appointment}/attend` (administrador y veterinario; el veterinario, solo sus turnos y los de agendas sin veterinario, 403
`APPOINTMENT_NOT_YOURS`). **Atendido y cancelado son finales**: no hay "deshacer el atendido". La FASE 3 dejó una
[propuesta](fase-03-agenda-y-turnos.md#relación-con-la-consulta-fase-4) para esta fase (abajo, en el vínculo con el turno).

**El paciente** ([`pacientes.md`](../../../02-requisitos/reglas/pacientes.md)): `weight_kg` propio; un fallecido solo cambia la fecha
y las notas (`PATIENT_DECEASED`); el cambio de dueño no toca las consultas (guardan `client_id` de ese momento).

**La auditoría** ([`auditoria.md`](../../../02-requisitos/reglas/auditoria.md)): `Auditable` guarda `old_values` y `new_values` de cada
cambio; el detalle (`audit-logs/{id}`) los muestra al administrador; los archivos se auditan como `"(archivo)"`.

## Diferencias entre los documentos y lo que existe

Cada una es una pregunta; no se resuelve en el diseño.

| # | Diferencia | Pregunta |
|---|---|---|
| C1 | El nombre del documento: el pedido dice `f4-historia-clinica.md`; los del backend son `fase-NN-…` (ya existe `fase-04-historia-clinica.md`, con el roadmap enlazado) y el paso 2 ([`prompts/2-diseno.md`](../../prompts/2-diseno.md)) diseña en `fase-NN-*.md` | 1 |
| C2 | La D4 ([decisiones pendientes](../../../01-arquitectura/decisiones/README.md)) recomienda **reabrir** la consulta cerrada con permiso; `backend/CLAUDE.md` §3 dice que una consulta cerrada es **inmutable** y se corrige con otro registro; el seeder ya tiene `consultations.reopen` | 4 |
| C3 | El pedido de este diseño pide "tratamiento" e "indicaciones"; la tabla tiene un solo `plan` | 6 |
| C4 | El roadmap pone "ítems de servicio **y de producto** (sin stock)" en la 4.2, pero los productos se crean recién en la 5.1 (no hay API de productos) | 9 |
| C5 | El roadmap pone el cierre en la 4.5 y el criterio de cierre de la fase es "turno fichado → consulta → cierre"; recetas en la FASE 6 pero el pedido las nombra entre "lo que el roadmap ponga en la F4" | 3 y 10 |
| C6 | El seeder le da a la recepción `consultations.index` y `.show` (todo el contenido clínico); el pedido pide cuidar los datos sensibles | 7 |
| C7 | `closed_at` y `cancelled_at` de `consultations` son `timestamp`; la regla vigente pide `datetime` en la hora de negocio (deuda 17) | Migración de la 4.1 (sin pregunta: es la regla) |
| C8 | El esqueleto pide abrir la consulta "desde un turno **fichado**"; el pedido de este diseño habla de "un turno **atendido**" que abre o se liga a una consulta | 2 |

## Decisiones comunes de la fase

Lo que vale para todas las entregas, salvo que una pregunta diga otra cosa.

* **Arquitectura** (§4): `ConsultationController` (y uno por recurso hijo) → Form Request → `ConsultationService`,
  `ConsultationItemService`, `ConsultationAttachmentService`, `PatientApplicationService` → modelos → `XResource`/`ShowXResource` →
  `ApiResponse`. `ConsultationException` (`error_code` y HTTP) para lo de negocio. Rutas con nombre y su permiso (`InteractsWithApiRoutes`,
  `RoleAccessTest`, `RouteBindingTest`, `ValidationTranslationsTest`, `VoseoTest`, `SnakeCaseKeysTest`).
* **Clínica y sucursal**: todo con `BelongsToClinic` y fallo cerrado; una consulta, un paciente o un turno de otra clínica → 404 (o 422
  en un `*_id` del cuerpo). La consulta guarda la sucursal: la del turno, o la actual del usuario si no hay turno (sin sucursal actual,
  422 `CONSULTATION_NO_BRANCH`, como `APPOINTMENT_QUEUE_NO_BRANCH`). El historial y el listado son de **toda la clínica** (el paciente
  es de la clínica, no de la sucursal) con el filtro `branch_id`.
* **Fechas**: `consulted_at`, `closed_at`, `cancelled_at`, `applied_at` con `AppDateTime` (en la base, hora de Asunción; en la API,
  UTC con `Z`); los filtros con `ApiDateTime` (`from`/`to`: una fecha sola es el día completo de Asunción). `next_dose_due_at` y
  `lot_expires_at`, fechas (`yyyy-MM-dd`).
* **Textos**: `TrimStrings` como siempre; límites en caracteres (`mb_strlen`): `reason` 255, los textos clínicos hasta 10000
  ([pregunta 13](#preguntas-del-diseño)), `cancellation_reason` de 3 a 500 (como el turno).
* **Números del examen**: `weight_kg` de 0,01 a 9999,99; `temperature_c` de 30,0 a 45,0; `heart_rate` de 1 a 400;
  `respiratory_rate` de 1 a 200; `body_condition_score` de 1 a 9 ([pregunta 13](#preguntas-del-diseño)); todos opcionales mientras
  está abierta. Decimales con punto o coma, como el peso del paciente.
* **Dinero** (ítems): `decimal(14,2)` con BCMath, PYG a 0 decimales, el IVA del servicio (§3).
* **Concurrencia** (§3, se suma al orden de la FASE 3: clínica → veterinario → servicio → agenda → paciente → cliente → turno →
  **consulta**): abrir desde un turno bloquea el turno (`lockForUpdate`) y revisa su estado y que no tenga consulta; el índice único
  `appointment_id` es la segunda guarda (su violación → 409 `CONSULTATION_ALREADY_EXISTS`, por el nombre del índice, §3). Editar,
  cerrar, enmendar o anular: la consulta con `lockForUpdate` antes de leer su estado. Ítems y aplicaciones: la consulta con
  `lockForUpdate` (abierta) y el servicio con `sharedLock`. Los reintentos de `AppointmentService::withRetries` (40001/1213 hasta 3
  veces; 1205 → 409 `CONFLICT`).
* **Listados**: `ListFilters` (`search` hasta 100, literal con `SearchTerm`; `per_page` de 1 a 100, 10 por defecto; `page`); 422 con
  un filtro mal formado, nunca 500.
* **Errores nuevos** (todos en `ConsultationException`, mensajes con voseo):

| `error_code` | HTTP | Cuándo |
|---|---|---|
| `CONSULTATION_ALREADY_EXISTS` | 409 | El turno ya tiene una consulta (también la carrera del índice único) |
| `CONSULTATION_NOT_EDITABLE` | 422 | Editar, sumar un ítem, un adjunto o una aplicación a una consulta cerrada o anulada |
| `CONSULTATION_NOT_CLOSABLE` | 422 | Cerrar una que no está abierta |
| `CONSULTATION_CLOSE_INCOMPLETE` | 422 | Cerrar sin lo obligatorio (`errors` por campo, [pregunta 5](#preguntas-del-diseño)) |
| `CONSULTATION_NOT_CANCELLABLE` | 422 | Anular una anulada (o una con ítems facturados, desde la FASE 8) |
| `CONSULTATION_NOT_YOURS` | 403 | Un veterinario que edita, cierra o enmienda la consulta de otra ficha ([pregunta 8](#preguntas-del-diseño)) |
| `CONSULTATION_NO_BRANCH` | 422 | Sin turno y sin sucursal actual |
| `PATIENT_DECEASED` | 422 | Abrir una consulta de un paciente fallecido ([pregunta 11](#preguntas-del-diseño)); el mismo código de la FASE 1 |
| Reusados | | `APPOINTMENT_INVALID_TRANSITION` (422: el turno no está fichado), `APPOINTMENT_NOT_YOURS` (403), `RESOURCE_NOT_FOUND` (404), `VALIDATION_ERROR` (422), `CONFLICT` (409), `FORBIDDEN` (403), `PAYLOAD_TOO_LARGE` (413, adjuntos) |

## Modelo de datos

### La consulta

| Campo | Tipo | Reglas |
|---|---|---|
| `code` | `CON-000001` | `DocumentSequenceService::create(DocumentSeries::CONSULTATION)`; nunca `max(id)+1`; puede tener huecos |
| `branch_id` | FK | La del turno; sin turno, la sucursal actual del usuario |
| `patient_id` | FK | El del turno, o el del pedido; no fallecido ([pregunta 11](#preguntas-del-diseño)) |
| `client_id` | FK | El dueño **actual** del paciente al abrir; no cambia con un cambio de dueño posterior |
| `appointment_id` | FK única, nullable | El turno del que se abrió ([vínculo con el turno](#el-vínculo-con-el-turno)) |
| `veterinarian_id` | FK | La ficha de quien la abre; si no tiene ficha (el administrador), la del turno o la del pedido (obligatoria) |
| `consulted_at` | `datetime` | Ahora al abrir; editable mientras está abierta, no futuro, no anterior al fichado del turno |
| `reason` | 255 | El motivo (del turno, `appointments.reason`, si lo tiene) |
| `history` | texto | Anamnesis |
| Examen físico | `weight_kg`, `temperature_c`, `heart_rate`, `respiratory_rate`, `mucous_membranes`, `capillary_refill_time`, `body_condition_score`, `physical_exam` | Las existentes |
| `diagnosis` | texto | |
| `treatment` | texto | **Nueva** (o `plan` renombrada), [pregunta 6](#preguntas-del-diseño) |
| `instructions` | texto | **Nueva**: las indicaciones para el tutor (las usará la receta de la FASE 6) |
| `status` | `ConsultationStatus` | `open` → `closed`; `open` o `closed` → `cancelled`; nada sale de `cancelled` |
| `closed_at`, `closed_by` | `datetime`, FK | Al cerrar |
| `cancelled_at`, `cancelled_by`, `cancellation_reason` | `datetime`, FK, texto | Al anular |

El peso de la consulta actualiza el del paciente al cerrar ([pregunta 14](#preguntas-del-diseño)).

### Las enmiendas (si la pregunta 4 elige enmendar)

`consultation_amendments`: `id`, `clinic_id`, `consultation_id` (FK, `restrictOnDelete`), `field` (uno de los campos clínicos, o `null`
para una nota general), `content` (texto, de 3 a 10000), `reason` (de 3 a 500), `created_by`, `created_at` (`datetime`); sin
`updated_at` ni baja: **inmutable**, como `AuditLog`. Índice `consultation_id`+`created_at`. Auditable.

### Migraciones previstas

| Entrega | Migración | Qué hace |
|---|---|---|
| 4.1 | `…_adjust_consultations_for_phase_4` | `closed_at` y `cancelled_at` a `datetime` (C7); `treatment` e `instructions` (texto, nullable; o renombrar `plan`, pregunta 6); el índice `appointment_id` única se conserva con un nombre propio (para reconocer su violación, §3). Con su test en MySQL (`MySqlSessionTimezoneTest` y el de la columna) |
| 4.2 | `…_create_consultation_amendments_table` | Solo si la pregunta 4 elige enmiendas |
| 4.4 | `…_add_status_to_patient_applications` | Solo si la pregunta 12 elige anular aplicaciones: `cancelled_at`, `cancelled_by`, `cancellation_reason` |
| 4.5 | — | `consultation_attachments` ya alcanza; los archivos en el disco privado `consultation_attachments` (como `patient_photos`) |

Sin migraciones para los ítems (la tabla alcanza) ni para el historial. Las tablas están vacías en producción (no hay producción):
las migraciones no migran datos.

## El vínculo con el turno

Propuesta (de la FASE 3, con lo que este diseño agrega), sujeta a las preguntas 2 y 3:

* **Abrir desde un turno fichado**: `POST consultations` con `appointment_id`. En una transacción, con el orden de bloqueo de arriba:
  el turno tiene que estar **fichado** (si no, 422 `APPOINTMENT_INVALID_TRANSITION`; uno ya atendido, ver abajo), sin consulta (si no,
  409 `CONSULTATION_ALREADY_EXISTS`) y ser del veterinario que pide (las reglas de `attend`: 403 `APPOINTMENT_NOT_YOURS`). La consulta
  toma el paciente, el cliente (el dueño actual del paciente), la sucursal, el veterinario de la agenda (o la ficha de quien pide, si
  la agenda no tiene) y el motivo. **El turno pasa a atendido en la misma transacción** (`attended_at` = ahora), con su auditoría:
  "atendido" significa que el veterinario lo atendió, aunque la consulta siga abierta.
* **Un turno ya atendido sin consulta** (alguien usó `attend` y después quiere la historia clínica): [pregunta 2](#preguntas-del-diseño).
  La recomendación: se puede abrir la consulta de un turno atendido **el mismo día del turno** y sin consulta; el turno no cambia.
* **`POST appointments/{appointment}/attend` sigue** para los turnos sin historia clínica (peluquería, una vacuna rápida que se carga
  como aplicación sin consulta, si la pregunta 12 lo admite).
* **Consulta sin turno** (urgencia, o alguien que llegó sin turno): `POST consultations` sin `appointment_id`, con `patient_id` (y
  `veterinarian_id` si quien pide no tiene ficha). No crea un turno ([pregunta 3](#preguntas-del-diseño)).
* **Deshacer el atendido**: la FASE 3 no lo tiene (atendido es final) y esta fase no lo suma. **Anular la consulta no vuelve el turno
  a fichado**: el turno queda atendido (la atención ocurrió; lo que se anula es el registro). Si el usuario pide poder deshacer el
  atendido, va como una pregunta nueva con su propia transición.
* **Cancelar un turno con consulta**: imposible, porque el turno ya está atendido (final).
* **Lo que el turno muestra**: `ShowAppointmentResource` suma `consultation` (`id`, `code`, `status`) o `null` (aditivo: no rompe al
  frontend), y el listado de turnos y la cola, `has_consultation` (bool), para el botón "Abrir la consulta" o "Ver la consulta" de la
  F4 del frontend.

## Integridad clínica

* **Abierta**: se edita (con `PATCH`, parcial) por quien puede ([pregunta 8](#preguntas-del-diseño)); los ítems, los adjuntos y las
  aplicaciones se suman y se quitan.
* **Cerrar** (`POST consultations/{id}/close`): exige lo obligatorio ([pregunta 5](#preguntas-del-diseño)); fija `closed_at` y
  `closed_by`; desde ahí **no cambia ningún campo clínico, ítem, adjunto ni aplicación** (422 `CONSULTATION_NOT_EDITABLE`). El cierre
  es la "firma": queda quién y cuándo. No hay cierre automático ([pregunta 15](#preguntas-del-diseño)).
* **Corregir una cerrada** ([pregunta 4](#preguntas-del-diseño), la C2): la recomendación es la **enmienda**: un registro nuevo que se
  agrega y se muestra con la consulta, con autor, hora y motivo, sin tocar lo escrito; lo anterior nunca se borra. La alternativa de la
  D4 es reabrir con `consultations.reopen` (el administrador), editar y volver a cerrar, con la auditoría como único rastro de lo
  anterior.
* **Anular** (`POST consultations/{id}/cancel`, `consultations.cancel`, el administrador): con motivo; abierta o cerrada; la consulta
  queda visible en el historial, marcada "Anulada", con todo su contenido. Sus ítems pasan a `no_charge` (no se cobran). Sus
  aplicaciones, [pregunta 12](#preguntas-del-diseño). Desde la FASE 8, no se anula con un ítem facturado.
* **Nunca se borra**: no hay `DELETE consultations` (405, `HttpVerbsTest`). Un adjunto se quita solo con la consulta abierta (la baja
  física del archivo y de la fila, auditada como `"(archivo)"`).
* **Auditoría**: `Auditable` en la consulta, los ítems, las aplicaciones, las enmiendas y los adjuntos (sus rutas, como `"(archivo)"`).
  El detalle de la auditoría (solo el administrador) muestra los textos clínicos anteriores y nuevos ([pregunta 16](#preguntas-del-diseño)).

## Permisos por rol

Propuesta, sobre los permisos que ya están sembrados (cambian la foto `clinic-roles.json` y la de las transiciones solo si una pregunta
lo pide):

| Acción | Administrador | Veterinario | Recepción |
|---|---|---|---|
| Ver el listado y el detalle de una consulta | Sí | Sí, de toda la clínica | Según la [pregunta 7](#preguntas-del-diseño): la recomendación, **solo los datos administrativos** (código, fecha, paciente, cliente, veterinario, estado, servicios para cobrar), sin los textos clínicos ni el examen |
| Abrir | Sí (indica el veterinario si no tiene ficha) | Sí: sus turnos y los de agendas sin veterinario; sin turno, con su ficha | No |
| Editar, sumar ítems, adjuntos y aplicaciones (abierta) | Sí | La de su ficha ([pregunta 8](#preguntas-del-diseño)) | No |
| Cerrar | Sí | La de su ficha | No |
| Enmendar (cerrada) o reabrir | Sí | Enmendar la de su ficha (si la pregunta 4 elige enmiendas; permiso nuevo `consultations.amend`) | No |
| Anular | Sí | No | No |
| Ver el historial del paciente | Sí | Sí | Según la pregunta 7 (aplicaciones: sí, ya tiene `patient-applications.index`) |
| Ver y descargar adjuntos | Sí | Sí | No (`consultation-attachments.index` no lo tiene) |

Permisos nuevos posibles: `consultations.amend` (pregunta 4) y `patients.history` (o se reusa `consultations.index` + `patient-applications.index`:
recomendado, sin permiso nuevo). Los datos sensibles nunca van a un log ni a una traza; los adjuntos, en un disco privado y descargados
por la API (`api.getBlob` en el frontend), como la foto.

## Endpoints

Formato de las demás fases: `{success, message, data, meta, errors, error_code}`; fechas con hora en UTC con `Z`; `meta.pagination`
en los listados.

| Método y ruta | Permiso | Qué hace | Errores |
|---|---|---|---|
| `GET consultations` | `consultations.index` | Listado: `patient_id`, `client_id`, `veterinarian_id`, `branch_id`, `status` (uno o lista, `status[]`), `appointment_id`, `from`/`to` (sobre `consulted_at`, hasta 31 días; sin ellos, los últimos 30 días), `search` (código, nombre o código del paciente, cliente). Por `consulted_at` descendente e id | 422 en un filtro |
| `GET consultations/{consultation}` | `consultations.show` | El detalle (`ShowConsultationResource`: el examen, los textos, los ítems, las aplicaciones, los adjuntos sin ruta, las enmiendas, el turno, quién la creó, cerró o anuló) | 404 |
| `POST consultations` | `consultations.store` | Abrir: con `appointment_id` (desde el turno) o con `patient_id` (sin turno); opcionales `veterinarian_id`, `consulted_at`, `reason` y los campos clínicos. 201 con el detalle | 403 `APPOINTMENT_NOT_YOURS`; 404; 409 `CONSULTATION_ALREADY_EXISTS`; 422 `APPOINTMENT_INVALID_TRANSITION`, `PATIENT_DECEASED`, `CONSULTATION_NO_BRANCH`, `VALIDATION_ERROR` |
| `PATCH consultations/{consultation}` | `consultations.update` | Editar los campos clínicos y `consulted_at` (solo abierta) | 403 `CONSULTATION_NOT_YOURS`; 422 `CONSULTATION_NOT_EDITABLE`; 409 `CONFLICT` |
| `POST consultations/{consultation}/close` | `consultations.close` | Cerrar | 422 `CONSULTATION_NOT_CLOSABLE`, `CONSULTATION_CLOSE_INCOMPLETE` |
| `POST consultations/{consultation}/amendments` | `consultations.amend` (nuevo) | Enmienda (pregunta 4 = enmiendas) | 422 si no está cerrada |
| `POST consultations/{consultation}/reopen` | `consultations.reopen` | Reabrir (pregunta 4 = reabrir) | 422 si no está cerrada |
| `POST consultations/{consultation}/cancel` | `consultations.cancel` | Anular con `cancellation_reason` | 422 `CONSULTATION_NOT_CANCELLABLE` |
| `POST consultations/{consultation}/items` | `consultation-items.store` | Un servicio: `service_id`, `quantity`, opcionales `unit_price` (si no, el del catálogo), `discount`, `veterinarian_id`; activo | 422 `CONSULTATION_NOT_EDITABLE`, servicio inactivo |
| `PATCH consultation-items/{item}` / `DELETE consultation-items/{item}` | `.update` / `.destroy` | Cambiar o quitar (abierta) | 422 `CONSULTATION_NOT_EDITABLE` |
| `GET consultations/{consultation}/attachments/{attachment}` | `consultation-attachments.index` | Descargar (el tipo por el contenido) | 404 |
| `POST consultations/{consultation}/attachments` | `consultation-attachments.store` | Subir (multipart): imagen o PDF, hasta el límite ([pregunta 17](#preguntas-del-diseño)) | 413 `PAYLOAD_TOO_LARGE`; 422 |
| `DELETE consultation-attachments/{attachment}` | `.destroy` | Quitar (abierta) | 422 `CONSULTATION_NOT_EDITABLE` |
| `GET patients/{patient}/applications` | `patient-applications.index` | Las aplicaciones del paciente (`type`, `from`/`to` sobre `applied_at`) | 404 |
| `POST patients/{patient}/applications` | `patient-applications.store` | Una aplicación: `type`, `name`, `applied_at`, opcionales `consultation_id` (abierta, del mismo paciente), `lot_number`, `lot_expires_at`, `dose`, `route`, `dose_number`, `next_dose_due_at` (posterior a `applied_at`), `notes` | 422 |
| `GET patients/{patient}/history` | `consultations.index` y `patient-applications.index` | El historial: consultas (con su resumen) y aplicaciones, de la más reciente a la más vieja, paginado de a 20 (hasta 100) | 404 |

Rutas nuevas: unas 16 (78 → ~94, según las preguntas 4 y 12); con `scopeBindings()` en las anidadas.

## Entregas propuestas

Cada una sale de la anterior (`feature/back-4-N-…`), con su revisión y su cierre (los pasos 4 a 7 de
[`prompts/README.md`](../../prompts/README.md)). El orden cambia el del roadmap ([pregunta 3](#preguntas-del-diseño)): el cierre y la
corrección van antes que los ítems, porque son la integridad de lo que se escribe desde la 4.1.

| Entrega | Contenido | Migraciones | Tests (principales) | Newman |
|---|---|---|---|---|
| 4.1 | La consulta: abrir desde un turno fichado (y marcarlo atendido) o sin turno, editar abierta, listado y detalle; `consultation` en el detalle del turno y `has_consultation` en el listado y la cola; `postman:cleanup` borra las consultas de la corrida | La de C7, C3 | `ConsultationStoreTest` (desde el turno, sin turno, ya atendido, otro veterinario, fallecido, sin sucursal), `ConsultationUpdateTest`, `ConsultationIndexTest` (filtros, N+1), `ConsultationLockTest` y `ConsultationConcurrencyTest` (dos aperturas del mismo turno: una 201, otra 409; solo MySQL), `RoleAccessTest`, `RouteBindingTest`, `ClinicIsolation`, la foto de los roles si cambia | `syncvet-fase-04-consultas` (nueva) |
| 4.2 | Cierre, corrección (enmienda o reapertura) y anulación | Enmiendas (si la pregunta 4) | `ConsultationCloseTest`, `ConsultationAmendmentTest` (o `ReopenTest`), `ConsultationCancelTest` (el turno sigue atendido), la inmutabilidad (cada ruta de escritura con una cerrada → 422) | La de consultas, ampliada |
| 4.3 | Los servicios usados (ítems de tipo `service`) | — | `ConsultationItemTest` (precio del catálogo, descuento, totales con BCMath, servicio inactivo, cerrada), el uso del servicio (`SERVICE_NAME_NOT_EDITABLE`) | La de consultas |
| 4.4 | Las aplicaciones (vacunas, desparasitaciones y medicamentos) | La de la pregunta 12, si corresponde | `PatientApplicationTest` (con y sin consulta, próxima dosis, fallecido, cerrada) | `syncvet-fase-04-aplicaciones` (nueva) |
| 4.5 | Los adjuntos | — | `ConsultationAttachmentTest` (el tipo por el contenido, el tamaño, la descarga, el disco privado, `files:prune-orphans`) | La de consultas |
| 4.6 | El historial del paciente | — | `PatientHistoryTest` (orden, paginación, anuladas marcadas, la vista de la recepción según la pregunta 7, N+1) | La de aplicaciones |

**En cada entrega**: las reglas nuevas en un archivo `docs/02-requisitos/reglas/consultas.md` (y `pacientes.md` para el historial), la
guía de Postman y la colección; la suite con el presupuesto por entrega; MySQL solo si toca migraciones o concurrencia (la 4.1 y la
4.2). **Al cierre de la fase**: `composer test` y `composer test:mysql` en serie, `composer audit`, Newman con **todas** las colecciones
(`node docs/05-pruebas/newman-todas.mjs`, que limpia cada corrida) y la regresión del frontend sobre `main` (la API de la FASE 3 no
cambia, salvo los campos aditivos del turno).

## Preguntas del diseño

Cada una cambia el comportamiento: el usuario elige. La recomendación, en negrita. **Bloquea la 4.1**: las marcadas con **(4.1)**.

| # | Pregunta | Opciones | Recomendación | Bloquea |
|---|---|---|---|---|
| 1 | ¿Dónde vive el diseño? (C1) | (a) En `fase-04-historia-clinica.md` (la convención del backend y el enlace del roadmap), con este contenido, y `f4-historia-clinica.md` se borra; (b) en `f4-historia-clinica.md`, y `fase-04-…` queda como el esqueleto con el enlace; (c) los dos nombres, renombrando `fase-04-…` a `f4-…` en un commit propio | **(a)**: el roadmap, el handoff y los resultados ya enlazan `fase-04-…`, y "fN" es la convención del frontend (`f3-agenda.md`); un movimiento de archivos va en su propio commit | Sí, de forma (antes de aprobar) |
| 2 | ¿Se puede abrir la consulta de un turno ya **atendido** sin consulta? (C8) | (a) Sí, el mismo día del turno y por quien podría haberlo atendido; el turno no cambia; (b) no: solo desde un turno fichado (quien usó `attend` abre una consulta sin turno); (c) sí, sin límite de días | **(a)**: corrige el "marqué atendido y me olvidé de abrir la consulta" sin perder el vínculo, y el límite del día evita ligar una consulta de hoy a un turno viejo | **(4.1)** |
| 3 | ¿El orden de las entregas? (C5) | (a) El propuesto: 4.1 consulta, 4.2 cierre y corrección, 4.3 servicios, 4.4 aplicaciones, 4.5 adjuntos, 4.6 historial; (b) el del roadmap (ítems en la 4.2, cierre en la 4.5) | **(a)**: desde la 4.1 se escribe historia clínica; sin el cierre, nada de eso es inmutable durante cuatro entregas | **(4.1)** |
| 4 | ¿Cómo se corrige una consulta cerrada? (C2, la D4) | (a) **Enmiendas**: un registro nuevo (campo, texto, motivo, autor, hora), inmutable, que se muestra con la consulta; lo escrito no cambia; (b) **reabrir** con `consultations.reopen` (el administrador), editar y cerrar de nuevo, con la auditoría como rastro (la D4); (c) las dos: reabrir el mismo día por su veterinario, enmiendas después | **(a)**: es la práctica de la historia clínica (el *addendum*), cumple el §3 ("consulta cerrada inmutable, se corrige con otro registro") y lo anterior se lee sin ir a la auditoría; `consultations.reopen` queda sin ruta (o se quita del seeder) | **(4.1)**: define el modelo de la integridad, aunque se implemente en la 4.2 |
| 5 | ¿Qué es obligatorio para cerrar? (pregunta 3 del esqueleto) | (a) El motivo y el diagnóstico; (b) motivo, diagnóstico y peso; (c) nada: se cierra lo que haya | **(a)**: sin motivo ni diagnóstico la consulta no dice nada; el peso no siempre se toma (un control de herida) | No (la 4.2) |
| 6 | ¿"Tratamiento" e "indicaciones"? (C3) | (a) `plan` pasa a `treatment` y se suma `instructions` (las indicaciones para el tutor, que usará la receta); (b) se queda solo `plan`; (c) `plan` y se suma `instructions` | **(a)**: son dos lectores distintos (el veterinario y el tutor) y la FASE 6 imprime las indicaciones; la tabla está vacía, renombrar no migra datos | **(4.1)** |
| 7 | ¿Qué ve la recepción de una consulta? (C6) | (a) Solo lo administrativo (código, fecha, paciente, cliente, veterinario, estado y los servicios para cobrar), sin los textos clínicos ni el examen; (b) todo, como siembra hoy el seeder; (c) nada (se le quitan `consultations.index` y `.show`) | **(a)**: necesita saber qué cobrar y que el paciente se atendió, no el diagnóstico; dos Resources (o un campo `clinical` que solo llega con un permiso nuevo `consultations.show-clinical`) | **(4.1)** |
| 8 | ¿Quién edita y cierra una consulta abierta? | (a) Su veterinario (la ficha de la consulta) y el administrador; otro veterinario, 403 `CONSULTATION_NOT_YOURS`; (b) cualquier veterinario de la clínica | **(a)**: lo firmado es de quien atendió; un colega que sigue el caso abre otra consulta o (con la pregunta 4 = a) enmienda | **(4.1)** |
| 9 | ¿Ítems de producto en la FASE 4? (C4) | (a) No: solo servicios; los productos (con stock y lote) en la 5.5; (b) productos como texto libre sin `product_id` (y después no se pueden descontar); (c) adelantar la 5.1 (productos) a esta fase | **(a)**: sin la API de productos no hay qué elegir, y un producto de texto libre no se puede facturar ni descontar después | No (la 4.3) |
| 10 | ¿Recetas en la FASE 4? (C5) | (a) No: las `instructions` de la consulta como texto; la receta en PDF en la FASE 6 (que ya tiene las tablas); (b) una receta mínima ahora | **(a)**: la FASE 6 tiene plantillas, PDF, QR y anulación diseñados juntos | No |
| 11 | ¿Consulta de un paciente fallecido? | (a) No se abre después de `deceased_at` (422 `PATIENT_DECEASED`); una abierta antes se puede cerrar; (b) se abre igual (una necropsia) | **(a)**, con una precisión: si el usuario quiere registrar una necropsia, se pide como un tipo de consulta aparte | No |
| 12 | ¿Las aplicaciones se anulan? ¿Hay aplicaciones sin consulta? | (a) Se anulan con motivo (migración de la 4.4) y se admiten sin consulta (una vacuna aplicada en otra clínica, con `notes`); al anular la consulta, sus aplicaciones se anulan con ella; (b) no se anulan; sin consulta, no; (c) se anulan, siempre con consulta | **(a)**: el carné de vacunas se carga con lo anterior y un error de carga no puede quedar para siempre en el carné | No (la 4.4) |
| 13 | ¿Los límites de los textos y del examen? | (a) Los propuestos (textos hasta 10000; temperatura de 30 a 45 °C; frecuencia cardíaca 1–400; respiratoria 1–200; condición corporal 1–9); (b) otros | **(a)**: cubren especies chicas y grandes; la escala de condición corporal de 9 puntos es la más usada | **(4.1)** (son las reglas del Form Request) |
| 14 | ¿El peso de la consulta actualiza el del paciente? | (a) Sí, al cerrar (si tiene peso); (b) no, el paciente se edita aparte | **(a)**: el peso del paciente es "el último que se tomó"; al cerrar, para que una consulta abierta con un error no lo cambie | No (la 4.2) |
| 15 | ¿Las consultas abiertas se cierran solas? | (a) No: el listado con `status=open` muestra las pendientes de cierre; (b) un cierre automático a la medianoche | **(a)**: cerrar es firmar; uno automático firmaría por el veterinario | No |
| 16 | ¿La auditoría guarda los textos clínicos? | (a) Sí, como hoy (`old_values` y `new_values`, solo el administrador los ve); (b) solo los nombres de los campos que cambiaron | **(a)**: con la enmienda (pregunta 4 = a) la auditoría es un respaldo, y el administrador ya ve todo | No |
| 17 | ¿Qué adjuntos? | (a) Imágenes (JPEG, PNG, WebP) y PDF, hasta 10 MB cada uno y 20 por consulta; (b) cualquier archivo | **(a)**: estudios y fotos; el tipo por el contenido como la foto del paciente; 10 MB alcanza para un PDF de laboratorio | No (la 4.5) |
| 18 | ¿Qué incluye el historial del paciente? | (a) Consultas (con su resumen: código, fecha, veterinario, motivo, diagnóstico, estado) y aplicaciones, juntas por fecha; (b) solo consultas; (c) también los turnos | **(a)**: es lo que el veterinario mira antes de atender; los turnos ya tienen su lista | No (la 4.6) |

## Criterio de cierre

* El flujo turno fichado → consulta (el turno queda atendido) → servicios y aplicaciones → cierre funciona de punta a punta, también
  la consulta sin turno; una consulta cerrada no se edita (se corrige según la pregunta 4) y una anulada queda visible.
* `composer test` y `composer test:mysql` en serie, en verde; `composer audit` sin avisos; Newman con todas las colecciones, sin
  fallas y sin dejar datos; las reglas en `docs/02-requisitos/reglas/consultas.md`.

> El resultado de cada entrega y el cierre de la fase irán en `fase-04-resultados.md` (o según la [pregunta 1](#preguntas-del-diseño)).
