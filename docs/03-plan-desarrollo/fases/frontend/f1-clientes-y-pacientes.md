# F1 — Clientes y pacientes (frontend)

Etapa: Datos de la clínica. Estado: **COMPLETADO** el 2026-11-16 (R-072; [cierre](#cierre-de-la-f1)) e integrado a `main`. Diseño
**APROBADO** por el usuario el 2026-11-12 (R-064), en `docs/front-f1-diseno` (desde `main` en `668eb48`), con las
[preguntas del diseño](#preguntas-del-diseño) respondidas: todas las recomendaciones, también la entrega front-01 del backend. El estado vigente de cada
fase está en [`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Las primeras pantallas de negocio, sobre la API ya cerrada de la [FASE 1 del backend](../backend/fase-01-clientes-y-pacientes.md):
clientes (persona o empresa), pacientes (con el fallecimiento, el cambio de dueño y la foto) y el catálogo de especies y razas. Se
usan las convenciones de la F0 tal cual ([`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §5): las claves de
`query-keys.ts`, el trato por `error_code`, `applyValidationErrors`, las fechas en `America/Asuncion`, el voseo y los permisos con
`can()`.

## Lo que da la API (resumen del análisis)

Las reglas completas están en [`backend/CLAUDE.md`](../../../../backend/CLAUDE.md) §6.1 a §6.3; acá, solo lo que decide la
interfaz.

| Recurso | Endpoints | Permisos (`clinic-roles.json`) | Notas para el frontend |
|---|---|---|---|
| Clientes | `GET clients` (`search`, `kind`, `page`, `per_page` de 1 a 100, 10 por defecto; orden: los más nuevos primero), `POST clients`, `GET/PATCH/DELETE clients/{client}` | `clients.index`, `.show`, `.store`, `.update`: los tres roles. `clients.destroy`: solo el administrador | `search` busca en documento, nombres, razón social, teléfono, email **y en el nombre o el código de sus mascotas** (literal, sin comodines, hasta 100). El detalle trae `patients` (todos, también los fallecidos, con `is_deceased`), `created_at` y `updated_at` |
| Pacientes de un cliente | `GET clients/{client}/patients` (`include_deceased`; sin paginar, por nombre), `POST clients/{client}/patients` | `patients.index`, `patients.store`: los tres | El dueño sale de la URL |
| Pacientes | `GET patients` (`search`, `species_id`, `include_deceased`, paginado, por nombre), `GET/PATCH/DELETE patients/{patient}`, `POST patients/{patient}/change-owner` | `.index`, `.show`, `.update`: los tres. `patients.destroy`: solo el administrador. `patients.change-owner`: administrador y recepcionista (**el veterinario no**) | El listado **no trae el dueño** (`PatientResource` no lo incluye, aunque el controller carga `client`): ver la [pregunta 1](#preguntas-del-diseño). El detalle trae `client` (`id`, `display_name`, `phone`, `email`, `deleted`), `color`, `allergies`, `notes`, `deceased_at` (UTC con `Z`), `age` (texto ya armado) y `has_photo` |
| Foto | `POST/GET/DELETE patients/{patient}/photo` (campo `photo`, multipart) | `patients.upload-photo`, `.show-photo`, `.delete-photo`: los tres | El `GET` devuelve el archivo (no JSON), con `Cache-Control: private, no-store`. JPG, PNG o WEBP de hasta 5 MB por el contenido; más grande, 413 `PAYLOAD_TOO_LARGE` con `errors.photo`. Sin foto, 404 `PATIENT_PHOTO_NOT_FOUND` |
| Especies y razas | `GET species` (con sus `breeds`; `include_inactive`), `POST species`, `PATCH/DELETE species/{species}`; `GET/POST species/{species}/breeds`, `PATCH/DELETE species/{species}/breeds/{breed}` | `species.index`: los tres. `species.store`, `.update`, `.destroy` (también para las razas): administrador y veterinario (**el recepcionista solo lista**) | `is_global` marca las del catálogo común: no se editan ni se borran (403 `CATALOG_GLOBAL_NOT_EDITABLE`). Un `GET species` alcanza para los selectores del paciente (trae las razas activas anidadas) |

`error_code` de negocio del módulo (todos van por el trato `business` de la tabla de la F0, salvo lo que cada entrega suma):

| `error_code` | HTTP | Mensaje de la API | Trato propio en la F1 |
|---|---|---|---|
| `CLIENT_KIND_NOT_EDITABLE` | 422 | "El cliente ya tiene facturas: no se puede cambiar de persona a empresa ni al revés." | Alerta arriba del formulario; el tipo vuelve al guardado |
| `CLIENT_IN_USE` | 409 | "El cliente tiene pacientes, turnos, consultas o facturas y no puede eliminarse." | En el diálogo de baja, el mensaje y solo "Cerrar" (los clientes no se inactivan) |
| `PATIENT_SAME_OWNER` | 422 | "El paciente ya pertenece a ese cliente." | En el diálogo de cambio de dueño; el navegador ya lo evita (el dueño actual no se ofrece) |
| `PATIENT_IN_USE` | 409 | "El paciente tiene consultas, aplicaciones o turnos y no puede eliminarse. Si falleció, registrá la fecha de fallecimiento." | En el diálogo de baja, el mensaje y el botón "Registrar el fallecimiento" (si no está fallecido) |
| `PATIENT_DECEASED` | 422 | "El paciente figura como fallecido: solo se pueden editar la fecha de fallecimiento y las notas." | Alerta arriba; el navegador ya bloquea los demás campos |
| `PATIENT_BREED_SPECIES_MISMATCH` | 422 | "La raza no corresponde a la especie del paciente." | Al campo `breed_id` (no a la alerta) |
| `PATIENT_PHOTO_NOT_FOUND` | 404 | (al ver o borrar) | Al ver: el lugar de la foto vacío, sin página de error; al borrar: aviso y se vuelve a pedir el paciente |
| `CATALOG_GLOBAL_NOT_EDITABLE` | 403 | "Este registro es del catálogo común: no se puede modificar ni eliminar." | No cae en `FORBIDDEN` (se decide por el código): alerta con el mensaje. El navegador ya oculta editar y borrar en las globales |
| `SPECIES_IN_USE`, `BREED_IN_USE` | 409 | "… está asignada a pacientes y no puede eliminarse. Podés inactivarla." | En el diálogo de baja, el mensaje y el botón "Inactivar" |
| `PAYLOAD_TOO_LARGE` | 413 | (la foto) | Ya está en la tabla de la F0: `errors.photo` al campo; sin `errors`, aviso |

## Hallazgos del análisis

| # | Hallazgo | Propuesta |
|---|---|---|
| H1 | `GET patients` no trae el dueño: el listado de pacientes no puede mostrar de quién es cada uno (el controller ya hace `with('client')`, pero `PatientResource` no lo saca) | [Pregunta 1](#preguntas-del-diseño): una entrega de soporte del backend chica (front-01) que sume `client: { id, display_name }` con `whenLoaded`, sin cambiar lo demás |
| H2 | `client.ts` no sabe recibir un archivo: un 2xx con un cuerpo que no es JSON es `HTTP_ERROR` (convención de la 0.1). La foto lo necesita | En la 1.4, `api.getBlob(path)`: con un 2xx devuelve el `Blob`; con un error, el `ApiError` de siempre (el cuerpo de error sí es JSON). Sin tocar el comportamiento de `api.get` |
| H3 | El email se rechaza con mayúsculas (regla `lowercase` del Request), no se pasa a minúsculas en la API | El formulario lo pasa a minúsculas y le saca los espacios de los extremos antes de mandar ([pregunta 6](#preguntas-del-diseño)) |
| H4 | `deceased_at` es la primera fecha con hora que manda el frontend: acá se verifica el **desvío 8 de la 0.1** (`toApiDateTime` con `-03:00`, PENDIENTE DE VERIFICACIÓN; [resultado de la 0.1](f0-base.md#entrega-01)). El Request la valida con `date` y `before_or_equal:now`, y el cast `AppDateTime` la convierte a la hora de Asunción | Entrega 1.3b: un test de Vitest del cuerpo mandado (`…T10:30:00.000-03:00`) y un caso de Playwright contra la API real: registrar 10:30, volver a pedir el paciente y ver 10:30 (la API devuelve `13:30:00Z`). Si la API lo rechaza, se registra como deuda y se manda en UTC (`toISOString()` de un `Date`) |
| H5 | `weight_kg` sale como texto decimal (`"12.50"`) y la edición de un fallecido compara los campos con los casts del modelo (R-021): mandar `12.5` o `"12.50"` tiene que dar lo mismo | Se manda como texto con punto, tal como vino; un caso de Playwright edita las notas de un fallecido con peso cargado |
| H6 | El detalle del cliente ya trae sus pacientes, también los fallecidos | No hace falta `GET clients/{client}/patients` en el detalle ([pregunta 3](#preguntas-del-diseño)) |
| H7 | Los documentos de los datos de prueba tienen que ser ficticios (R-011; `FictitiousDocumentsTest` revisa el backend y las colecciones) | Los tests del frontend usan los mismos documentos ficticios de las colecciones de Postman de la FASE 1 |

## Decisiones comunes de la fase

* **URL en castellano, búsqueda y paginación en la URL.** `/clientes?buscar=perez&tipo=empresa&pagina=2`,
  `/pacientes?buscar=…&especie=3&fallecidos=1&pagina=2`. Cada ruta valida su búsqueda con Zod en `validateSearch` (TanStack
  Router): un valor inválido se descarta (vuelve al valor por defecto), nunca rompe la página. Recargar o compartir la URL muestra
  lo mismo; "Atrás" vuelve a la página anterior del listado.
* **Espera al escribir.** El campo de búsqueda es local; un `useDebouncedValue` propio (300 ms, `src/lib/hooks/`, sin paquete
  nuevo) actualiza la URL con `replace: true` (escribir no llena el historial) y vuelve a `pagina=1`. La consulta usa
  `placeholderData: keepPreviousData` (la tabla no parpadea) y un indicador de carga chico mientras llega. Se recorta a 100
  caracteres, el máximo de la API.
* **Claves de las consultas** (`api.ts` de cada módulo; son de la clínica, no de la sucursal, así que **no** usan `branchKey`):

  | Clave | Qué |
  |---|---|
  | `['clients', 'list', { search, kind, page }]` | `GET clients` |
  | `['clients', 'detail', id]` | `GET clients/{id}` (con sus pacientes) |
  | `['clients', 'options', search]` | La búsqueda del selector del dueño nuevo (1.3b) |
  | `['patients', 'list', { search, species_id, include_deceased, page }]` | `GET patients` |
  | `['patients', 'detail', id]` | `GET patients/{id}` |
  | `['patients', 'photo', id]` | La foto como `Blob` (1.4) |
  | `['species', { include_inactive }]` | `GET species` con sus razas |

  Invalidaciones: cada mutación invalida lo que cambia, nunca toda la caché. Alta, edición o baja de un cliente: `['clients']`
  (también por la búsqueda por mascota) y, en la edición, `['patients', 'detail']` (el detalle del paciente muestra el nombre del
  dueño). Alta, edición, baja, fallecimiento o cambio de dueño de un paciente: `['patients']` y `['clients']` (el detalle del cliente
  lista sus pacientes y la búsqueda de clientes encuentra por mascota). Foto: `['patients', 'photo', id]` y `['patients', 'detail',
  id]` (`has_photo`). Especies y razas: `['species']` y `['patients']` (el nombre se muestra en los pacientes).
* **Formularios.** React Hook Form con Zod. Zod repite las reglas de forma de la API (obligatorios, largos, expresiones regulares,
  rangos) para avisar sin ir al servidor; lo que necesita la base (documento o email repetidos, microchip repetido, especie
  inactiva) lo dice el 422 de la API, que se vuelca con `applyValidationErrors`. Si las dos validaciones difieren, manda la de la
  API: el 422 siempre se muestra. Los mensajes de Zod, en voseo y con el mismo texto que el de la API cuando existe. Los
  campos opcionales vacíos se mandan como `null`. La edición manda el formulario completo (la API lo admite y compara).
* **Guardar y volver.** Con el 201 o el 200: aviso de éxito y al detalle. "Cancelar" vuelve sin preguntar (ver la
  [pregunta 11](#preguntas-del-diseño)).
* **Confirmación antes de borrar** con un diálogo propio (`ConfirmDialog` en `src/components/ui`, sin el JS de Tabler):
  `role="dialog"`, `aria-modal`, el foco en "Cancelar", Escape cierra y el botón de borrar en rojo con el nombre del registro. El
  error de negocio se muestra **dentro** del diálogo, con la acción que corresponda (inactivar, registrar el fallecimiento).
* **Permisos.** Cada ruta declara el suyo con `requirePermission`; los botones con `useCan()`. `PERMISSIONS` suma los de la F1 con
  los nombres de la API. Los tests usan `meFor(rol)` con los tres roles reales.
* **Menú.** "Clientes" (`clients.index`), "Pacientes" (`patients.index`) y "Especies" (`species.index`; [pregunta 8](#preguntas-del-diseño)):
  los tres roles ven los tres.
* **Sin paquetes nuevos.** La F1 no instala nada (`frontend/CLAUDE.md` §8): el *debounce*, el diálogo y los campos nuevos son
  propios; el dígito verificador del RUC es una función pura.
* **Componentes nuevos de `src/components/ui`** (cada uno en la entrega que lo usa primero): `SearchField` (1.1), `ConfirmDialog`,
  `SelectField`, `TextareaField`, `CheckboxField` y `RadioGroup` (1.2), `DateField` y `TimeField` (1.3a y 1.3b), `FileField` (1.4).

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| front-01 | Soporte del backend: el dueño en `GET patients` (aprobada con la [pregunta 1](#preguntas-del-diseño)) | — | `feature/front-01-dueno-pacientes`, desde `docs/front-f1-diseno` | COMPLETADO (R-065, [resultado](#entrega-front-01)) |
| 1.1 | Clientes: listado (búsqueda, tipo y paginación en la URL) y detalle (datos y pacientes) | No | `feature/front-1-1-clientes` (desvío 1) | COMPLETADO (R-066, [resultado](#entrega-11)) |
| 1.2 | Clientes: alta, edición (persona y empresa, el cambio de tipo) y baja | No | `feature/front-1-2-clientes-abm` | COMPLETADO (R-067, [resultado](#entrega-12)) |
| 1.3a | Pacientes: listado, detalle, alta (desde el cliente) y edición | front-01 | `feature/front-1-3a-pacientes` | COMPLETADO (R-068, [resultado](#entrega-13a)) |
| 1.3b | Pacientes: fallecimiento (y anularlo), cambio de dueño y baja; la verificación del desvío 8 | No | `feature/front-1-3b-pacientes`, desde `feature/front-1-3a-pacientes` (nombre del usuario al cerrar la 1.3a; el diseño decía `feature/front-1-3b-pacientes-estado`) | COMPLETADO (R-069, [resultado](#entrega-13b)) |
| 1.4 | Foto del paciente: ver (como `Blob`), subir y borrar | No | `feature/front-1-4-foto`, desde `feature/front-1-3b-pacientes` | COMPLETADO (R-070, [resultado](#entrega-14)) |
| 1.5 | Especies y razas: listado, alta, edición, inactivación y baja | No | `feature/front-1-5-especies`, desde `feature/front-1-4-foto` | COMPLETADO (R-071, [resultado](#entrega-15)) |

Cada rama sale de la anterior, como en la F0, y todo se integra a `main` al cerrar la F1. La entrega de pacientes del pedido original
se divide en dos (1.3a y 1.3b, [pregunta 12](#preguntas-del-diseño)): sola sería el doble que las demás. Las especies quedan al final:
los formularios del paciente solo leen `GET species`, que ya existe. Cada entrega recorre los pasos 4 a 7 de
[`prompts/README.md`](../../prompts/README.md).

## Diseño por entrega

### front-01 Soporte del backend: el dueño en el listado de pacientes

**Aprobada con la pregunta 1 (R-064).** `PatientResource` suma `client` con `whenLoaded('client')`: `{ id, display_name }` (el
detalle ya lo pisa con `ShowPatientResource`, más completo). `GET patients` ya carga `client`; `clients/{client}/patients` y el
detalle del cliente no lo cargan, así que no cambian. Sin rutas, permisos ni migraciones. Tests: el listado trae el dueño (persona
y empresa, `display_name`), sin N+1 (cantidad de consultas fija con 1 y con 5 pacientes), y el listado de un cliente no lo trae.
Postman: una aserción en la colección de pacientes. `backend/CLAUDE.md` §6.2 y la [guía de Postman](../../../05-pruebas/guia-postman.md).

#### Pendiente para el cierre (front-01)

Vacío: la entrega se cerró (R-065) y todo lo que había acá pasó a su [resultado](#entrega-front-01).

### 1.1 Clientes: listado y detalle

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/clientes?buscar=&tipo=&pagina=` | `clients.index` | Búsqueda ("Buscá por nombre, documento, teléfono, email o mascota"), filtro de tipo (Todos, Persona, Empresa), tabla: nombre (`display_name`, enlace al detalle), tipo, documento, teléfono, email. Paginación con `PaginationBar`. "Nuevo cliente" (en la 1.2, con `clients.store`). Vacío: "No hay clientes que coincidan con la búsqueda." o, sin búsqueda, "Todavía no hay clientes." |
| `/clientes/$id` | `clients.show` | Datos (tipo, documento, nombre o razón social, teléfono, email, dirección, ciudad, notificaciones, notas, alta y última modificación con `formatDateTime`) y la tarjeta "Pacientes" con los del detalle: nombre, especie, raza, sexo, edad; los fallecidos ocultos detrás de "Mostrar los fallecidos (N)" (filtro local, [pregunta 3](#preguntas-del-diseño)) |

**Consultas.** `['clients', 'list', …]` con `getPage`; `['clients', 'detail', id]`. Un id que no es un entero positivo va a la
página de no encontrado sin llamar a la API.

**Errores.** 404 al cargar el detalle: no encontrado; 403: sin permiso con el `message`; red o 5xx: la página de error con "Probá de
nuevo" (la lectura ya se reintentó una vez).

**Tests (Vitest).** `validateSearch` (valores inválidos, página 0, tipo desconocido, búsqueda de más de 100); la búsqueda espera
300 ms y manda un solo pedido, con `pagina` de vuelta en 1; la URL se lee al entrar (recarga); el filtro de tipo; la paginación;
el listado vacío con y sin búsqueda; el detalle con persona y empresa; los fallecidos ocultos y el botón que los muestra; 404 y 403
del detalle; el menú con los tres roles.

**Playwright.** Buscar un cliente del `DemoSeeder` por el nombre de su mascota y entrar al detalle; recargar con `?buscar=` y ver
el mismo resultado.

**Mutaciones a probar.** Sin `replace` (el historial se llena); sin la vuelta a la página 1; sin la espera.

**En el navegador.** Escribir rápido y ver un solo pedido en la pestaña Red; "Atrás" desde el detalle vuelve a la misma búsqueda y
página; el ancho de tablet (768 px): la tabla con desplazamiento horizontal propio, sin romper la página; la consola sin errores.

#### Pendiente para el cierre (1.1)

Vacío: la entrega se cerró (R-066) y todo lo que había acá pasó a su [resultado](#entrega-11).

### 1.2 Clientes: alta, edición y baja

**Pantallas.** `/clientes/nuevo` (`clients.store`) y `/clientes/$id/editar` (`clients.update`), el mismo `ClientForm`; la baja, con
un botón en el detalle (`clients.destroy`, solo el administrador) y el `ConfirmDialog`.

**Formulario.** Arriba, el tipo con `RadioGroup` (Persona, Empresa). Persona: nombre y apellido; empresa: razón social. Comunes:
documento, email, teléfono, dirección, ciudad, "Recibe recordatorios" (`notifications_enabled`, marcado por defecto) y notas.
Al cambiar de tipo, los campos del otro tipo se ocultan y se mandan en `null` (la API lo exige así: `prohibited` con un valor).

**Validación.**

| Campo | Zod (navegador) | Solo la API (422 al campo) |
|---|---|---|
| `first_name`, `last_name` | Obligatorios en persona, 2 a 100, la regla de `PersonName` (letras, espacios, apóstrofo o guion entre letras) | — |
| `business_name` | Obligatoria en empresa, 2 a 150, la expresión de la API | — |
| `document_number` | Obligatorio, 5 a 20, `[a-zA-Z0-9-]`; **con la forma de un RUC (`NNNNNNN-D`), el dígito verificador** ([pregunta 5](#preguntas-del-diseño)) | Documento en uso (por el número base: `99999903` y `99999903-6` son el mismo) |
| `email` | Obligatorio, formato de email, hasta 50; se pasa a minúsculas y sin espacios en los extremos antes de mandar | Email en uso; el dominio sin DNS si la clínica tiene `email_dns` |
| `phone` | Obligatorio, `^\+?[1-9]\d{7,14}$`; se le sacan los espacios y guiones que se escriban | — |
| `address`, `city`, `notes` | Largos y caracteres de la API | — |

El dígito verificador se calcula con `rucCheckDigit` (`src/features/clients/ruc.ts`), la misma cuenta que `ClinicService::rucCheckDigit`
(módulo 11 con factores 2 a 11 desde la derecha), con los casos de `tests/Unit/RucCheckDigitTest.php` copiados como tabla del test.
Una cédula sin guion o un documento con letras no se valida (como en la API). Mensaje: el mismo de `RucCheckDigit::MESSAGE`.

**Mutaciones.** `POST clients`, `PATCH clients/{id}`, `DELETE clients/{id}`; invalidan `['clients']` (y la edición,
`['patients', 'detail']`); la baja quita `['clients', 'detail', id]` y vuelve a `/clientes`.

**Errores.** 422 `VALIDATION_ERROR` a sus campos; `CLIENT_KIND_NOT_EDITABLE`, alerta arriba y el tipo vuelve al guardado;
`CLIENT_IN_USE` en el diálogo, con "Cerrar"; 404 en una acción, aviso y vuelta al listado; 403, alerta con el `message`.

**Tests (Vitest).** `rucCheckDigit` con la tabla del backend; cada regla de Zod (con `.each`); persona → empresa (los campos de
persona van en `null`); 422 de documento y de email a sus campos con el foco; `CLIENT_KIND_NOT_EDITABLE`; el email en minúsculas en
el cuerpo; la baja con confirmación (cancelar no llama a la API), con el 200 y con `CLIENT_IN_USE`; el botón de baja solo para el
administrador; las rutas sin permiso.

**Playwright.** Alta de una persona y de una empresa con documentos ficticios; documento repetido (el 422 en su campo); RUC con el
dígito mal (sin llamar a la API); edición; baja de un cliente sin pacientes y la baja rechazada de uno con pacientes.

**Mutaciones a probar.** Sin el control del dígito verificador; sin `null` en los campos del otro tipo; la baja sin confirmación.

**En el navegador.** El cambio de tipo con datos cargados; el foco en el primer error; el diálogo con el teclado (Tab, Escape); el
ancho de tablet.

#### Pendiente para el cierre (1.2)

Vacío: la entrega se cerró (R-067) y todo lo que había acá pasó a su [resultado](#entrega-12).

### 1.3a Pacientes: listado, detalle, alta y edición

**Antes, en el backend (commit aparte, decisión del usuario al cerrar la 1.2, R-067).** La [deuda 22](../../deuda-tecnica.md):
`PatientService` guarda el microchip solo con la regla `unique` del Form Request, y dos altas o ediciones simultáneas con el mismo
microchip responden 500. Se corrige como en `ClientService` (1.2): la violación del índice `patients_clinic_id_microchip_unique`
(MySQL) o `patients.microchip` (SQLite) responde el 422 de la regla `unique` en `microchip`, en el alta y en la edición; tests como
`ClientUniqueIndexTest` (SQLite y MySQL) y `ClientConcurrencyTest` (dos altas simultáneas, MySQL); `composer test:fast` y, al
final, la suite en serie en SQLite y MySQL y Newman de la colección de pacientes.

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/pacientes?buscar=&especie=&fallecidos=&pagina=` | `patients.index` | Búsqueda ("Buscá por nombre, código, microchip o dueño"), especie (de `GET species`), "Incluir los fallecidos"; tabla: código, nombre (enlace), especie, raza, sexo, edad, dueño (con front-01; enlace al cliente) y la marca "Fallecido" |
| `/pacientes/$id` | `patients.show` | Datos (con `birth_date` por `formatDateOnly` y "aprox." si es estimada, `age`, peso en kg con coma decimal, microchip, alergias resaltadas, notas), el dueño (enlace; "Cliente eliminado" si `deleted`) y, en la 1.3b y la 1.4, el estado y la foto |
| `/clientes/$id/pacientes/nuevo` | `patients.store` | El `PatientForm`; el botón "Nuevo paciente" en el detalle del cliente ([pregunta 7](#preguntas-del-diseño)) |
| `/pacientes/$id/editar` | `patients.update` | El mismo formulario |

**Formulario.** Nombre; especie y raza (`SelectField`: la raza se filtra por la especie y se vacía al cambiar de especie; "Sin
raza" es `null`); sexo (Macho, Hembra, Sin determinar, el texto de la API; por defecto "Sin determinar"); castrado; fecha de nacimiento (`DateField`, `Y-m-d`) y
"Es aproximada"; color; peso (kg); microchip; alergias; notas. Al editar, si la especie o la raza del paciente están inactivas, se
suman a las opciones con "(inactiva)" (la API las acepta solo si no cambian).

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `name` | Obligatorio, hasta 100, la expresión de la API | — |
| `species_id`, `breed_id` | Especie obligatoria; la raza, de la especie elegida | Especie o raza inactiva o eliminada (422 al campo); `PATIENT_BREED_SPECIES_MISMATCH` (al campo `breed_id`) |
| `birth_date` | Fecha sin hora, no posterior a `todayInAppZone()`; se manda tal cual (`Y-m-d`), sin pasar por `Date` | — |
| `weight_kg` | Opcional, de 0,01 a 2000 con hasta 2 decimales; acepta coma o punto y se manda como texto con punto (`"12.5"`) | — |
| `microchip` | Hasta 30, solo letras y números | Microchip en uso |
| `color`, `allergies`, `notes` | Largos y caracteres de la API | — |

**Consultas y mutaciones.** `['patients', 'list', …]`, `['patients', 'detail', id]`, `['species', { include_inactive: false }]`
(con `staleTime` de 5 minutos: cambia poco). `POST clients/{client}/patients` y `PATCH patients/{id}`; invalidan `['patients']` y
`['clients']`.

**Tests (Vitest).** `validateSearch`; la búsqueda y los filtros; el formulario: la raza filtrada y vaciada al cambiar de especie, la
especie inactiva del paciente en las opciones al editar, `birth_date` de mañana en Asunción rechazada con la PC en UTC (la suite
corre en UTC: a las 22:00 de Asunción ya es mañana en UTC), el peso con coma; 422 a cada campo y `PATIENT_BREED_SPECIES_MISMATCH` a
`breed_id`; `birth_date` mostrada sin correrse un día.

**Playwright.** Alta de un paciente desde el detalle de un cliente y su aparición en la tarjeta de pacientes; edición; búsqueda de
un paciente por el nombre del dueño en `/pacientes`.

**Mutaciones a probar.** La raza sin vaciar al cambiar de especie; `birth_date` comparada con la fecha de la PC; el peso con coma
mandado tal cual.

**En el navegador.** El selector de raza con una especie sin razas; la fecha de nacimiento en el selector nativo del navegador; el
ancho de tablet.

#### Pendiente para el cierre (1.3a)

Vacío: la entrega se cerró (R-068) y todo lo que había acá pasó a su [resultado](#entrega-13a).

### 1.3b Pacientes: fallecimiento, cambio de dueño y baja

**Fallecimiento.** En el detalle, "Registrar el fallecimiento" (`patients.update`) abre un diálogo con fecha (`DateField`) y hora
(`TimeField`), las dos con el momento actual de Asunción por defecto; se manda `PATCH patients/{id}` con el formulario completo y
`deceased_at: toApiDateTime(año, mes, día, hora, minuto)` (`2026-11-12T10:30:00.000-03:00`). Zod: no posterior a ahora, no anterior a
`birth_date` (comparando el día, como la API). Con el 200, el detalle muestra "Falleció el 12/11/2026 10:30" (`formatDateTime`) y el
formulario de edición de un fallecido deja editables solo la fecha de fallecimiento y las notas (los demás, deshabilitados, se
mandan con el valor guardado). "Anular el fallecimiento" manda `deceased_at: null`, con confirmación ([pregunta 10](#preguntas-del-diseño)).

**Verificación del desvío 8** (H4): el test de Vitest revisa el cuerpo exacto; el de Playwright, el ida y vuelta contra la API real.
El resultado se anota en el [resultado de la 0.1](f0-base.md#entrega-01) (el desvío pasa a verificado o a deuda).

**Cambio de dueño.** "Cambiar de dueño" (`patients.change-owner`: administrador y recepcionista) abre un diálogo con un buscador de
clientes (`['clients', 'options', search]`, `GET clients?search=…&per_page=10`, con la misma espera de 300 ms; el dueño actual no
se ofrece). `POST patients/{id}/change-owner` con `client_id`; invalida `['patients']` y `['clients']`. Errores: 422 en `client_id`
(el cliente se eliminó mientras tanto) en el diálogo; `PATIENT_SAME_OWNER`, en el diálogo. Un fallecido también cambia de dueño.

**Baja.** "Eliminar" (`patients.destroy`, solo el administrador) con el `ConfirmDialog`; con el 200, al detalle del dueño. Con
`PATIENT_IN_USE`, el mensaje y "Registrar el fallecimiento" (si no está fallecido), que cierra este diálogo y abre el otro.

**Tests (Vitest).** El cuerpo con `-03:00` y la hora de pared elegida (con la PC en UTC); los límites de la fecha (futura, antes del
nacimiento, el mismo día); el formulario de un fallecido con los campos bloqueados; anular; el cambio de dueño (búsqueda con espera,
sin el dueño actual, 422, `PATIENT_SAME_OWNER`); el botón oculto para el veterinario; la baja con `PATIENT_IN_USE` y su botón.

**Playwright.** Registrar el fallecimiento a una hora dada y verla igual después de recargar (desvío 8); editar las notas de un
fallecido con peso cargado (H5); cambiar el dueño y ver el paciente en el detalle del cliente nuevo; la baja rechazada de un
paciente con turnos del `DemoSeeder`, si los tiene, o la baja de uno recién creado.

**Mutaciones a probar.** `deceased_at` con `toISOString()` de un `Date` local (la hora corrida con la PC en UTC); el formulario del
fallecido con los campos habilitados; el dueño actual ofrecido en el buscador.

**En el navegador.** El ida y vuelta de la hora en la pestaña Red (lo mandado y lo devuelto); el diálogo de baja que lleva al de
fallecimiento.

#### Pendiente para el cierre (1.3b)

Vacío: la entrega se cerró (R-069) y todo lo que había acá pasó a su [resultado](#entrega-13b).

### 1.4 Foto del paciente

**Cliente de la API (H2).** `api.getBlob(path, options)` en `src/lib/api/client.ts`: el mismo armado del pedido (token, `Accept`);
con un 2xx devuelve `response.blob()`; con un error lee el JSON y arma el `ApiError` como siempre (también el cierre de sesión
con 401). `api.post` ya manda un `FormData` como multipart. Tests en `client.test.ts`.

**Ver.** En el detalle, si `has_photo`: `useQuery(['patients', 'photo', id])` con `getBlob`, `staleTime: Infinity` y `gcTime` de 5
minutos (la API manda `no-store`; la caché de Query es solo de la pestaña). El componente crea la URL con `URL.createObjectURL` en
un efecto y la libera con `URL.revokeObjectURL` al cambiar el `Blob` o al desmontar. Sin foto o con 404 `PATIENT_PHOTO_NOT_FOUND`:
el lugar vacío con el ícono de la especie, sin error. El `<img>` lleva `alt` con el nombre del paciente.

**Subir.** `FileField` con `accept="image/jpeg,image/png,image/webp"`; antes de mandar, el navegador controla el tamaño (hasta 5 MB,
`5 * 1024 * 1024` bytes, como `MAX_KILOBYTES = 5120` de la API) y el tipo declarado, con los mensajes de la API; así un archivo
grande no se manda (con PHP en 8 MB y 10 MB, `setup.md`, uno de más de 10 MB respondería un 413 sin `errors`). Vista previa local
(URL de objeto del `File`, liberada después) y "Subir la foto" ([pregunta 9](#preguntas-del-diseño)). `POST patients/{id}/photo`
con `FormData` (`photo`). Con el 201: invalida `['patients', 'photo', id]` y `['patients', 'detail', id]`. Errores: 422 (no es una
imagen, demasiados megapíxeles) y 413 en el campo; los demás, alerta.

**Borrar.** "Quitar la foto" con el `ConfirmDialog`; `DELETE patients/{id}/photo`; invalida lo mismo. Con 404, aviso y se vuelve a
pedir el paciente.

**Permisos.** Ver, subir y borrar: los tres roles (también con el paciente fallecido).

**Tests (Vitest).** `getBlob` (2xx con imagen, 404 JSON, 401 que cierra la sesión, cancelación); la URL de objeto creada y liberada
(espías de `URL.createObjectURL` y `revokeObjectURL`); un archivo de más de 5 MB y uno de otro tipo rechazados sin pedido; el 413
con `errors.photo` en el campo; el 413 sin `errors`, aviso; subir y borrar con su invalidación.

**Playwright.** Subir una imagen chica (un PNG de prueba en `e2e/fixtures/`), verla en el detalle después de recargar, reemplazarla
y quitarla.

**Mutaciones a probar.** Sin `revokeObjectURL`; sin el control de tamaño; `getBlob` que trata un 404 como éxito.

**En el navegador.** Una foto vertical de un celular (la orientación ya la aplica la API); la pestaña Memoria o Red: ningún pedido
de la foto al volver al detalle dentro de los 5 minutos; la consola sin errores.

#### Pendiente para el cierre (1.4)

Vacío: la entrega se cerró (R-070) y todo lo que había acá pasó a su [resultado](#entrega-14).

### 1.5 Especies y razas

**Pantalla.** `/especies` (`species.index`): las especies con sus razas en una lista que se despliega (`GET species?include_inactive=1`
para quien administra; sin él, para el recepcionista, que solo lista). Cada especie y raza muestra "Común" (`is_global`) o "Propia",
y "Inactiva". Acciones, solo con `species.store`/`update`/`destroy` (administrador y veterinario) y solo en las propias: "Nueva
especie", "Nueva raza" (también en una especie común: una raza propia puede ir en una global), renombrar, activar o inactivar
(`is_active`) y eliminar. Alta y edición en un diálogo con un solo campo (nombre).

**Validación.** Zod: obligatorio, 2 a 60 (especie) o 80 (raza), con al menos una letra (`CatalogMessages::NAME_REGEX`). El nombre
repetido (sin distinguir mayúsculas, acentos ni espacios) lo dice la API: 422 en `name`.

**Mutaciones.** `POST species`, `PATCH/DELETE species/{id}`, `POST species/{id}/breeds`, `PATCH/DELETE species/{id}/breeds/{breed}`;
invalidan `['species']` y `['patients']`. Errores: `CATALOG_GLOBAL_NOT_EDITABLE` (alerta), `SPECIES_IN_USE` y `BREED_IN_USE` en el
diálogo con "Inactivar", 404 (aviso e invalidar).

**Tests (Vitest).** El recepcionista sin acciones; las globales sin editar ni borrar; alta y renombre con 422 en `name`; inactivar;
la baja con `*_IN_USE` y el botón "Inactivar"; `CATALOG_GLOBAL_NOT_EDITABLE` como alerta y no como `/sin-permiso`.

**Playwright.** Crear una raza propia en "Perro", usarla en un paciente, intentar borrarla (rechazada) e inactivarla; el
recepcionista ve la lista sin botones.

**Mutaciones a probar.** Las acciones visibles en las globales; `CATALOG_GLOBAL_NOT_EDITABLE` tratado como `FORBIDDEN`.

**En el navegador.** La lista con muchas razas (el `SpeciesSeeder`); el ancho de tablet.

#### Pendiente para el cierre (1.5)

Vacío: la entrega se cerró (R-071) y todo lo que había acá pasó a su [resultado](#entrega-15).

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| front-01 | `backend/CLAUDE.md` §6.2, la [guía de Postman](../../../05-pruebas/guia-postman.md) y su colección, este documento, el roadmap y el handoff |
| 1.1 a 1.5 | `frontend/CLAUDE.md` (las convenciones nuevas: búsqueda en la URL, `getBlob`, `ConfirmDialog`; la tabla de errores si cambia), este documento (Resultado), el roadmap, `modulos.md` y el handoff |

## Preguntas del diseño

Respondidas por el usuario el 2026-11-12 (R-064): todas con la recomendación.

| # | Pregunta | Opciones | Recomendación | Respuesta |
|---|---|---|---|---|
| 1 | El listado de pacientes no trae el dueño (H1). ¿Se suma en el backend? | (a) Entrega de soporte front-01, chica y aditiva; (b) el listado sin dueño | **(a)**: en una veterinaria el paciente se reconoce por el dueño; el controller ya lo carga, falta solo sacarlo | Aprobada (usuario, 2026-11-12) |
| 2 | ¿Listados en tabla o en tarjetas? | Tabla (`DataTable`) o tarjetas | **Tabla**, también en tablet (con su desplazamiento horizontal); las tarjetas, solo para los pacientes dentro del detalle del cliente | Aprobada (usuario, 2026-11-12) |
| 3 | ¿Dónde se cargan los pacientes de un cliente? | (a) Del `GET clients/{id}`, que ya los trae; (b) `GET clients/{id}/patients` aparte | **(a)**: un pedido menos; los fallecidos se filtran en el navegador detrás de "Mostrar los fallecidos" | Aprobada (usuario, 2026-11-12) |
| 4 | ¿Confirmación antes de borrar? | Diálogo simple; diálogo que pide escribir el nombre; sin confirmación | **Diálogo simple** con el nombre en el texto y el botón rojo; escribir el nombre es demasiado para registros que la API ya protege con `*_IN_USE` | Aprobada (usuario, 2026-11-12) |
| 5 | ¿El dígito verificador del RUC se valida también en el navegador? | Sí (función pura con los casos del backend) o solo la API | **Sí**: el aviso llega al salir del campo; la cuenta es corta y queda atada a los casos de `RucCheckDigitTest` | Aprobada (usuario, 2026-11-12) |
| 6 | ¿El email se pasa a minúsculas solo (H3)? | Sí, o un error "en minúsculas" | **Sí**, en el envío: nadie escribe el email pensando en mayúsculas | Aprobada (usuario, 2026-11-12) |
| 7 | ¿El alta de un paciente es una página o un diálogo? | `/clientes/$id/pacientes/nuevo` o un diálogo en el detalle | **Página**: el formulario es largo y la URL permite volver | Aprobada (usuario, 2026-11-12) |
| 8 | ¿Dónde van las especies? | Menú propio "Especies" o dentro de Configuración | **Menú propio**: `/configuracion` es solo del administrador y el veterinario también administra el catálogo | Aprobada (usuario, 2026-11-12) |
| 9 | ¿La foto se sube al elegirla o con vista previa y botón? | Al elegir; vista previa y "Subir la foto" | **Vista previa y botón**: evita subir la foto equivocada | Aprobada (usuario, 2026-11-12) |
| 10 | ¿Se ofrece "Anular el fallecimiento"? (la API acepta `deceased_at: null`) | Sí, con confirmación; no | **Sí**, con confirmación y el mismo permiso de edición: corrige un error de carga | Aprobada (usuario, 2026-11-12) |
| 11 | ¿Avisar al salir de un formulario con cambios sin guardar? | Sí; no | **No en la F1**: se suma más adelante si hace falta, para todos los formularios a la vez | Aprobada (usuario, 2026-11-12) |
| 12 | ¿Pacientes en dos entregas (1.3a y 1.3b)? | Dos entregas; una sola | **Dos**: así las entregas quedan de tamaño parecido y el desvío 8 tiene su propia revisión | Aprobada (usuario, 2026-11-12) |
| 13 | ¿Cuántos por página? | Los 10 de la API; 20; un selector | **20**, sin selector (`per_page=20` fijo, no va en la URL) | Aprobada (usuario, 2026-11-12) |

## Criterio de cierre

* Las entregas aprobadas cerradas, con Vitest y Playwright en verde, `typecheck`, `lint` y `build` sin errores ni avisos.
* El desvío 8 de la 0.1 verificado contra la API real (o registrado como deuda con su corrección).
* Newman de **todas** las colecciones de `docs/05-pruebas/postman` desde la raíz (R-057), sin fallas.

## Resultado

### Entrega front-01

Estado: **COMPLETADO** el 2026-11-12 (R-065), en `feature/front-01-dueno-pacientes` (desde `docs/front-f1-diseno`), sin
integrar a `main`: se integra al cerrar la F1.

Implementada el 2026-11-12 en `feature/front-01-dueno-pacientes` (commit WIP `3e3e386`). Cambio: `app/Http/Resources/PatientResource.php`
suma `client` con `whenLoaded`; test nuevo `tests/Feature/Patients/PatientListOwnerTest.php` (6 casos). Sin rutas, permisos ni
migraciones nuevas (78, 135, 36), y sin cambios en `error_code` ni códigos HTTP.

**Corridas.** `composer test:fast`: 1451 casos en verde (14 omitidos, solo de MySQL). En serie, desde `backend/`: SQLite, 1451 casos
y 14225 aserciones (14 omitidos, solo de MySQL); MySQL, 1451 casos y 14299 aserciones, sin omitidos. Los casos que ya estaban
siguen en verde sin tocarlos: ninguna otra respuesta cambió. Newman, las 14 colecciones desde la raíz con el servidor en el puerto
8001: 483 pedidos y 1074 aserciones, sin fallas (las 2 de más son las de pacientes, 61 → 63).

**Mutaciones.** (1) `client` siempre, sin `whenLoaded`: falla el caso del listado del cliente y del detalle del cliente. (2) El
dueño cargado perezoso, sin `client` en el `with` del listado: falla el caso de N+1 (10 consultas frente a 5). (3) Sin `client` en
el `with` y con `whenLoaded`: fallan los casos del listado (la clave no sale).

**Desvíos.**

| # | Desvío | Motivo | Estado |
|---|---|---|---|
| 1 | `client` va última en `PatientResource` | `ShowPatientResource` la reemplaza por la suya (con `phone`, `email` y `deleted`); en PHP la clave conserva el lugar de su primera aparición, así que, al final, el detalle mantiene exactamente el orden de sus claves. Dos casos fijan las claves del listado y del detalle | Aceptado (usuario, R-065) |
| 2 | El listado no trae `deleted` del dueño (solo `id` y `display_name`, lo pedido) | Un paciente vivo no puede tener el dueño eliminado (`CLIENT_IN_USE`); si pasara (datos viejos), la relación ya es `withTrashed` y se muestra el nombre igual (caso con la baja hecha fuera del Service) | Aceptado (usuario, R-065) |
| 3 | El detalle del paciente ya traía el dueño (`id`, `display_name`, `phone`, `email`, `deleted`), también eliminado | Verificado con un caso; no se tocó | Informativo |
| 4 | El caso de N+1 compara 1 paciente con 6 (el diseño decía 1 y 5) y hace un pedido previo sin contar | El primer pedido también carga la caché de permisos de Spatie (5 consultas más) | Informativo |
| 5 | `client` es `null` si la relación no trae nada | Defensa: `patients.client_id` no admite nulos, no debería pasar | Informativo |
| 6 | Postman: dos aserciones en pedidos existentes de la colección de pacientes (17, el dueño en el listado; 18, el listado del cliente sin el dueño), no un pedido nuevo | Los pedidos ya existían | Informativo |
| 7 | Para detener el servidor de Newman, Claude Code intentó cerrar todos los `php.exe` de la PC; el modo automático lo bloqueó y se detuvo solo su tarea | El comando era demasiado amplio | Informativo: regla nueva en el [`CLAUDE.md`](../../../../CLAUDE.md) de la raíz, §2, Procesos (R-065) |

**Revisión** (2026-11-12, en una sesión que la revisó como quien no la escribió, sobre el WIP `3e3e386`): sin hallazgos que
corregir. `client` sale solo donde la relación se carga con `with` (el listado); `AppointmentResource` ya usa la misma forma
`{ id, display_name }` para el dueño de un turno; sin N+1; las demás respuestas, iguales (las claves del detalle, fijadas por un
caso). Observación sin cambio: `countQueries` es la tercera copia privada del mismo ayudante en los tests (unificarla toca tests de
otras entregas). `composer test:fast` sobre el código del WIP: 1451 casos en verde (14 omitidos, solo de MySQL).

**Validación.** Las suites en serie de SQLite y de MySQL y Newman de las 14 colecciones (cifras arriba), aceptadas por el usuario
como validación de la entrega, con los desvíos 1 y 2 aceptados (R-065). La suite del frontend no cambió: `npm test`, 269 tests en
21 archivos, en verde (sin código del frontend en la entrega).

### Entrega 1.1

Estado: **COMPLETADO** el 2026-11-13 (R-066), en `feature/front-1-1-clientes` (desde `feature/front-01-dueno-pacientes` en
`6ee193a`), sin integrar a `main`: se integra al cerrar la F1.

Implementada el 2026-11-13 (commit WIP `3ea03b9`), revisada como quien no la escribió (8 hallazgos, sin corregir en la revisión) y
con los hallazgos 1 a 7 corregidos el mismo día (commit WIP `104aee1`; el 8 quedó como informativo, decisión del usuario). Las
cifras vigentes son las de las [correcciones](#correcciones-de-la-revisión-11); las de "Cifras", justo abajo, son las de la
implementación.

**Qué se hizo.** `/clientes` (`src/routes/_sesion/clientes/index.tsx`, `ClientListPage`): búsqueda con espera de 300 ms
(`useDebouncedValue`, `src/lib/hooks/`) que cambia la URL con `replace` y vuelve a la página 1; el tipo y la página en la URL (con
historial); `validateSearch` con Zod (`features/clients/search.ts`); 20 por página con `getPage`, `keepPreviousData` y el
indicador chico en el campo; vacío con y sin búsqueda, cargando, error con "Probá de nuevo" y 403 con la página de sin permiso.
`/clientes/$id` (`$id.tsx`, `ClientDetailPage`): el `loader` valida el id (entero positivo, sin llamar a la API si no lo es) y pide
`GET clients/{id}`; un 404 es `notFound()`; los datos (persona o empresa) y los pacientes en tarjetas, con los fallecidos detrás de
"Mostrar los fallecidos (N)". Permisos `clients.index` y `clients.show` en `PERMISSIONS`, en las rutas y en el menú ("Clientes",
los tres roles). Componentes nuevos: `SearchField` y `SelectField`. Claves `['clients', 'list', filtros]` y
`['clients', 'detail', id]` (de la clínica: sin `branchKey`).

**Cifras.** `npm test`: 332 tests en 24 archivos, en verde (antes 269 en 21; los 3 archivos nuevos son `search.test.ts`,
`ClientListPage.test.tsx` y `ClientDetailPage.test.tsx`; también cambiaron los menús esperados de `permissions.test.ts` y
`permissions.test.tsx`). `typecheck`, `lint` y `build`, sin errores ni avisos. `npm run test:e2e`: 13 casos en verde (los 11 de antes
y 2 nuevos en `e2e/clientes.spec.ts`; `permisos.spec.ts` suma "Clientes" a los menús). Sin paquetes nuevos.

**Mutaciones** (las tres del diseño y dos más; cada una con los tests de `src/features/clients`): (1) sin `replace` al buscar,
(2) sin la vuelta a la página 1, (3) sin la espera (0 ms): las tres hacen fallar "la búsqueda espera 300 ms…". (4) `validateSearch`
sin las claves descartadas: sobrevivió al primer test (ver el desvío 4); con el test corregido, fallan los dos casos de la URL
inválida. (5) El 404 de la API sin pasar a `notFound()`: falla el caso del 404.

**Validación en el navegador** (2026-11-13): Edge sin ventana (`channel: 'msedge'`, el `@playwright/test` ya instalado) contra el
backend real (`syncvet.test`, base `syncvet`) y un `npm run dev` levantado para la prueba (y detenido al final), con un script fuera
del repositorio. 31 de 31 controles en verde:

| # | Prueba | Resultado | Detalle |
|---|---|---|---|
| 1 | `admin-a` y `recepcion-a`: menú y listado | OK | Menú con "Clientes" ("Configuración" solo el administrador); "Mostrando 1 a 20 de 132" (la base de desarrollo tiene 132 clientes en la Clínica A, de las colecciones de Postman) |
| 2 | Escribir rápido "Mishi" | OK | Un solo pedido con lo escrito (`search=Mishi&page=1&per_page=20`); encuentra a Laura Martínez por su mascota |
| 3 | Recargar | OK | Conserva la búsqueda en el campo y el resultado |
| 4 | Cambiar de página | OK | "Siguiente" a la página 2 (con otros clientes); recargar la conserva; "Atrás" vuelve a la 1 |
| 5 | Detalle y fallecidos | OK | Firulais a la vista y "Mostrar los fallecidos (1)"; mostrar y ocultar funcionan. La demo no tiene fallecidos: Mishi se marcó como fallecido **en la respuesta** de `GET clients/{id}` (interceptada por el script), sin tocar la base |
| 6 | "Atrás" desde el detalle | OK | Vuelve a `/clientes?buscar=Mishi`, con lo escrito en el campo |
| 7 | Cliente inexistente | OK | `/clientes/999999`: "No encontramos este cliente", dentro del layout |
| 8 | `vet-a` | OK | Tiene `clients.index` y `clients.show` (`clinic-roles.json`): menú "Inicio" y "Clientes"; el listado filtrado por empresa y el detalle de Granja San Jorge (RUC y razón social); `/configuracion` sigue sin permiso |
| 9 | 768 px | OK | Listado y detalle sin desplazamiento horizontal de la página (la tabla, 750 px dentro de su caja) |
| 10 | Consola | OK | Sin errores ni avisos de la aplicación; solo los dos registros de red del 404 esperado de la prueba 7 (una vez por usuario) |

Capturas (fuera del repositorio, en la PC de la validación): `01-listado-admin.png`, `02-detalle-admin-fallecidos.png`,
`03-listado-768.png` y `04-detalle-768.png`, en `%TEMP%\claude\…\scratchpad\capturas-1-1`. **Datos de la demo:** sin cambios
(solo ingresos y salidas, que crean y cierran tokens).

**Desvíos.**

| # | Desvío | Motivo | Estado |
|---|---|---|---|
| 1 | Rama `feature/front-1-1-clientes`, no `feature/front-1-1-clientes-listado` como dice la tabla de entregas | Pedido del usuario | Informativo |
| 2 | `SelectField` se adelanta de la 1.2 (el filtro de tipo), solo con la etiqueta y las opciones; la 1.2 le suma el error | Las páginas no repiten el marcado de Tabler (`frontend/CLAUDE.md` §5) | Aceptado (usuario, R-066) |
| 3 | El cliente inexistente o de otra clínica (y un id inválido) muestra "No encontramos este cliente" **dentro del layout** (`notFoundComponent` de la ruta, con "Volver a los clientes"), no la página de no encontrado de la raíz | La de la raíz es de página completa, sin la barra ni el menú; el texto no dice si el cliente es de otra clínica | Aceptado (usuario, R-066) |
| 4 | `validateSearch` devuelve siempre las tres claves (`undefined` si el valor se descarta) | Hallazgo: TanStack Router mezcla lo que devuelve sobre la búsqueda cruda de la URL; sin la clave, `?pagina=-4` llegaba a la API como `page=-4`. Con `buscar` en la URL no se veía (por eso la mutación 4 sobrevivió al primer test); el test prueba las dos URL | Aceptado (usuario, R-066) |
| 5 | ESLint: `only-throw-error` permite también `NotFoundError` de `@tanstack/router-core` (ya permitía `Redirect`) | `throw notFound()` en el `loader` del detalle | Aceptado (usuario, R-066) |
| 6 | El `loader` usa `queryClient.query({ ...opciones, staleTime: 'static' })`, no `ensureQueryData` | `ensureQueryData` está deprecado en TanStack Query 5.107 (lo marca ESLint); es el reemplazo que indica la biblioteca | Informativo |
| 7 | El error del listado se muestra dentro de la tarjeta ("No se pudieron cargar los clientes", "Probá de nuevo"), y el 403, con la página de sin permiso en la tarjeta; el del detalle, con la página de error del router ("Reintentar") | El listado se pide en el componente (con `keepPreviousData`, para que la tabla no parpadee); el detalle, en el `loader` | Aceptado (usuario, R-066) |
| 8 | Detalle: "RUC" en lugar de "Documento" para una empresa; un dato vacío se muestra con "—"; cada paciente lleva su código; el nombre del paciente todavía no es un enlace (su detalle llega en la 1.3a) | Detalles de presentación no definidos en el diseño | Aceptado (usuario, R-066) |
| 9 | La primera corrida completa de `npm test` dio 3 fallas por tiempo: `/ingresar` y `/perfil` (5 s) y "lee la búsqueda…" del listado (el segundo de `findByText`), con la PC cargada justo después de `lint` y `build`. Después de corregir el lint, la segunda corrida completa quedó en verde, y 5 vueltas de esos archivos no fallaron | Carga de la PC; no se reproduce | Informativo |
| 10 | La validación en el navegador simula el fallecido en la respuesta de la API (prueba 5) | La demo no tiene pacientes fallecidos y el pedido era no cambiar sus datos | Informativo |
| 11 | El paso 0 del pedido: deuda 21 (backend, baja), `countQueries` copiado en tres tests, en `deuda-tecnica.md` y `backend/CLAUDE.md` §7 | Pedido del usuario | Informativo |

Los desvíos 1 a 11, aceptados por el usuario en la revisión.

#### Correcciones de la revisión (1.1)

La revisión (2026-11-13, sobre `3ea03b9`, sin corregir) dio 8 hallazgos; el usuario pidió corregir del 1 al 7 ("este listado es el
modelo de los próximos") y dejar el 8 como informativo. La convención que sale de acá quedó en `frontend/CLAUDE.md` §5
("Listados" y "Tests").

| # | Hallazgo | Corrección | Test |
|---|---|---|---|
| 1 | Alta. El campo de búsqueda perdía el espacio final: "Laura ", pausa, "M" daba "LauraM" (la URL llegaba sin el espacio y pisaba lo escrito) | `ClientListPage`: el campo toma lo de la URL solo si el cambio vino de afuera: no si coincide con lo escrito sin los espacios de los extremos (`normalizeSearchText(text)`, la regla del usuario) **ni si es lo que el campo mandó y todavía no llegó** (`debounced` distinto de `answered`; ver el desvío 12) | "un espacio al final no se pierde…" y "una tecla escrita mientras la URL cambia no se pierde" |
| 2 | Media. `?pagina=99` de 7 decía "Todavía no hay clientes." | `DataTable` (genérico): con `current_page > last_page` y sin filas, "Esta página no existe." y el `firstPageLink` del listado; `/clientes` le pasa "Ir a la página 1" (un `<Link>` con la misma búsqueda, sin `pagina`) | "una página más allá de la última…" |
| 3 | Media. El test de la espera pasaba con 50 ms | Relojes falsos después de cargar la página: cuatro teclas cada 100 ms, a los 299 ms ni URL ni pedido, a los 300 ms la URL (con `replace`) y un solo pedido. Los 300 ms van escritos en el test, no `SEARCH_DEBOUNCE_MS` (con la constante, una espera de 1000 ms también pasaba) | El mismo, reescrito |
| 4 | Media. Tres tests frágiles por tiempo | `configure({ asyncUtilTimeout: 3000 })` en `src/test/setup.ts`; `testTimeout: 15_000` en el proyecto `dom` de `vite.config.ts`; las esperas reales de los tests de la búsqueda, cambiadas por relojes falsos donde se mide tiempo | La suite completa |
| 5 | Baja. "Buscando…" sin test y sin anuncio para el lector de pantalla | `SearchField`: un `<span role="status" className="visually-hidden search-status">` siempre en el DOM, con "Buscando…" o vacío; el spinner, `aria-hidden` | "mientras llega otra búsqueda, el campo lo anuncia…" |
| 6 | Baja. `?buscar=1e3` llegaba como 1000; `?buscar=12345678901234567890` perdía dígitos; `?buscar=null` se descartaba | `src/app/search-params.ts`: `parseSearch` y `stringifySearch` del router (`router.ts`); las claves de `TEXT_SEARCH_PARAMS` (`buscar`) se leen y se escriben como texto, sin comillas, y las demás siguen con el JSON del router | `search-params.test.ts` (nuevo, 18 casos) y dos casos del listado (`?buscar=1e3`; lo escrito que parece un número va sin comillas) |
| 7 | Baja. `useDebouncedValue` con un objeto armado en cada render se re-renderizaba sin fin | El tipo admite solo primitivos (`DebounceablePrimitive`) | `typecheck` |
| 8 | Informativo. Un rol con `clients.show` y sin `clients.index` llegaría a `/sin-permiso` desde "Clientes" o "Volver a los clientes" del detalle; ningún rol real está en ese caso | Sin cambios (decisión del usuario) | — |

Además, el test pedido de **respuestas fuera de orden**: "una búsqueda vieja que responde tarde no pisa a la nueva" ("La" responde
600 ms después de "Granja"; la tabla sigue con "Granja").

**Mutaciones** (cada una con los tests de `src/features/clients`, revertida desde una copia del archivo):

| # | Mutación | Resultado |
|---|---|---|
| 1 | `SEARCH_DEBOUNCE_MS = 50` | Falla "la búsqueda espera 300 ms…" (pedido por el usuario) |
| 2 | `SEARCH_DEBOUNCE_MS = 1000` | Falla el mismo |
| 3 | `busy={false}` | Falla "…anuncia ("Buscando…")…" |
| 4 | Sin `firstPageLink` | Falla "una página más allá de la última…" |
| 5 | El router sin `parseSearch` ni `stringifySearch` | Fallan los dos casos de `?buscar=1e3` y del número sin comillas |
| 6 | `DataTable` con `>=` en lugar de `>` | Fallan los tres casos del listado vacío |
| 7 | H1 solo con la regla de `normalizeSearchText` (sin lo que está en camino) | Falla "una tecla escrita mientras la URL cambia…" |
| 8 | H1 sin ninguna de las dos (el código revisado) | Fallan los dos tests de H1 |

**Cifras.** `npm test`: 357 tests en 25 archivos, en verde (antes 332 en 24; el archivo nuevo es `src/app/search-params.test.ts`).
`typecheck`, `lint` y `build`, sin errores ni avisos. `npm run test:e2e`: 13 de 13 en verde (3,1 min). Una sola corrida de cada
uno al final, salvo un `lint` que falló antes (ver el desvío 13) y obligó a repetir la corrida completa.

**Validación en el navegador** (2026-11-13), solo de lo que cambió: Edge sin ventana (`channel: 'msedge'`) contra el backend real
(`syncvet.test`, base `syncvet`), como `admin-a`, con un script fuera del repositorio. 4 de 4 en verde:

| # | Prueba | Resultado | Detalle |
|---|---|---|---|
| 1 | Escribir "Laura ", pausa de 800 ms, "M" | OK | Después de la pausa, el campo "Laura " con la URL `?buscar=Laura`; al final, "Laura M" con `?buscar=Laura+M`. Un pedido por búsqueda (`search=Laura` y `search=Laura+M`) |
| 2 | `/clientes?pagina=99` | OK | "Esta página no existe. Ir a la página 1", con el pie "Mostrando 0 a 0 de 132", "Página 99 de 7"; el enlace va a `/clientes` y muestra la página 1 |
| 3 | `/clientes?buscar=1e3` | OK | El campo "1e3" y la API con `search=1e3` (no 1000) |
| 4 | Consola | OK | Sin errores ni avisos |

Capturas (fuera del repositorio, en la PC de la validación): `01-laura-m.png`, `02-pagina-99.png` y `03-buscar-1e3.png`, en
`%TEMP%\claude\…\scratchpad\capturas-1-1-correcciones`. **Datos de la demo:** sin cambios (solo un ingreso).

**Desvíos de las correcciones.**

| # | Desvío | Motivo | Estado |
|---|---|---|---|
| 12 | H1 suma a la regla del usuario (la URL distinta de `normalizeSearchText(text)`) una segunda condición: lo que el campo mandó y todavía no llegó (`debounced !== answered`) | Con la regla sola, la tecla escrita mientras la URL cambia se perdía: la URL "Laura" llegaba con el campo en "LauraM" (distinto) y lo pisaba (mutación 7). Se calcula en el render, sin `setState` dentro del efecto (lo prohíbe la regla de React de ESLint) | Aceptado (usuario, R-066) |
| 13 | La primera corrida final tuvo 2 errores de `lint`: `DataTable` exportaba `isPageOutOfRange` junto al componente (Fast Refresh) y la primera versión de H1 guardaba lo enviado con `setState` dentro del efecto. Se corrigieron (la función quedó interna y H1 pasó a la versión del desvío 12) y se repitió la corrida completa | La regla de "una sola vez" se cumplió sobre el código final | Informativo |
| 14 | La página fuera de rango se resolvió en `DataTable` (genérico, con la prop `firstPageLink`), no solo en `/clientes` | Los próximos listados la heredan; el enlace lo arma cada página porque conoce su ruta y su búsqueda | Aceptado (usuario, R-066) |
| 15 | La validación en el navegador usó un `npm run dev` que ya estaba en `:5173` (PID 34856, iniciado a las 10:02, desde `frontend/`, con `--strictPort`): el que levantó Claude Code no pudo tomar el puerto. Sirve el árbol de trabajo actual (HMR), así que la prueba vale para el código corregido. **No se detuvo** (no lo levantó esta sesión): puede ser el de la validación anterior, que el pendiente daba por detenido | Regla de procesos del `CLAUDE.md` de la raíz | Aceptado (usuario, R-066); el proceso no lo cerró Claude Code |
| 16 | En la prueba 3 hubo dos pedidos iguales al entrar (`?search=1e3…` dos veces); al escribir, uno por búsqueda | Probablemente el doble efecto del `StrictMode` de React en desarrollo (monta, desmonta y vuelve a montar: el primer pedido se cancela y sale otro); no se investigó. Verificarlo con `npm run build` y `npm run preview` (sin `StrictMode` de desarrollo) queda opcional (usuario, R-066) | PENDIENTE DE VERIFICACIÓN (opcional) |

**Validación.** El usuario la validó en el navegador el 2026-11-13: "el listado y el detalle se ven bien y es rápido". Además, la de
Edge sin ventana de la implementación (31 de 31) y la de las correcciones (4 de 4), aceptadas por el usuario como validación de la entrega, con los desvíos 1 a 16 aceptados (R-066); el 16 sigue PENDIENTE DE VERIFICACIÓN. La suite del
backend no cambió: la entrega no toca su código (solo una línea de `backend/CLAUDE.md` §7, la deuda 21).

Corrida de `npm test` del frontend (2026-11-13): 357 tests en 25 archivos, en verde.

### Entrega 1.2

Estado: **COMPLETADO** el 2026-11-13 (R-067), en `feature/front-1-2-clientes-abm` (desde `feature/front-1-1-clientes` en
`78e9657`), sin integrar a `main`: se integra al cerrar la F1.

Implementada el 2026-11-13 (commit WIP `657100e`), revisada como quien no la escribió (7 hallazgos, sin corregir en la revisión) y con los hallazgos 1 a 6 corregidos el mismo día: el backend, en un commit aparte (`594ad20`), y el frontend con la documentación (commit WIP `bb8cab5`). Las cifras vigentes son las de las [correcciones](#correcciones-de-la-revisión-12); las de "Cifras", justo abajo, son las de la implementación.
todavía.

**Qué se hizo.** `/clientes/nuevo` (`clients.store`) y `/clientes/$id/editar` (`clients.update`, archivo `$id_.editar.tsx`: no se
dibuja dentro del detalle), con el mismo `ClientForm` (`features/clients/ClientForm.tsx` y `ClientFormPages.tsx`). Las reglas de
forma de `StoreClientRequest` con los mensajes de `ClientMessages` en `features/clients/form.ts` (`clientFormErrors`, el esquema
de Zod y `clientFormToPayload`: los campos del otro tipo y los opcionales vacíos en `null`, el email sin espacios y en minúsculas,
el teléfono sin espacios ni guiones). El dígito verificador en `features/clients/ruc.ts` (`rucCheckDigit`, `hasValidRucCheckDigit`
y `RUC_MESSAGE`). Un 422 a sus campos con `applyValidationErrors` (las claves que no son de un campo, a la alerta);
`CLIENT_KIND_NOT_EDITABLE`, alerta y el tipo vuelve al guardado; 403 y los demás, alerta con el `message`; 404 al guardar, aviso y
al listado. La baja, en el detalle (`clients.destroy`, solo el administrador), con `ConfirmDialog`; `CLIENT_IN_USE` dentro del
diálogo con solo "Cerrar". "Nuevo cliente" en el listado (`clients.store`) y "Editar" en el detalle (`clients.update`). El
`loader` del detalle pasó a `features/clients/loader.ts` (lo comparten las dos rutas). Componentes nuevos: `ConfirmDialog`,
`RadioGroup`, `CheckboxField` y `TextareaField`; `Button` admite `ref`. `success()` de `src/test/api.ts` admite el `message`.
Permisos `clients.store`, `clients.update` y `clients.destroy` en `PERMISSIONS`. Sin paquetes nuevos.

**Cifras.** `npm test`: 466 tests en 29 archivos, en verde (antes 357 en 25; los nuevos son `ruc.test.ts`, `form.test.ts`,
`ConfirmDialog.test.tsx` y `ClientFormPages.test.tsx`; `ClientDetailPage.test.tsx` y `ClientListPage.test.tsx` suman la baja y los
botones por rol). `typecheck`, `lint` y `build`, sin errores ni avisos. `npm run test:e2e`: 16 de 16 en verde (4,0 min; los 13 de
antes y 3 nuevos en `e2e/clientes-abm.spec.ts`: alta de persona con el documento repetido, edición y baja del cliente recién
creado; alta de empresa con el RUC mal, sin llamar a la API, y después bien; la baja de Laura Martínez de la demo, `CLIENT_IN_USE`).
La corrida final se hizo dos veces (desvío 10).

**Mutaciones** (cada una con los tests relacionados, revertida desde una copia del archivo):

| # | Mutación | Resultado |
|---|---|---|
| 1 | Sin el control del dígito verificador | Fallan el caso de `form.test.ts` y "el RUC con el dígito verificador equivocado…" del alta |
| 2 | Sin `null` en los campos del otro tipo (`first_name` siempre) | Fallan "persona → empresa…" de `form.test.ts` y del alta |
| 3 | La baja sin confirmación (el botón llama a la API) | Fallan "Cancelar en la confirmación no llama a la API" y el de la baja |
| 4 | El email sin pasar a minúsculas | Fallan el cuerpo del alta, el de la edición y el de `form.test.ts` |
| 5 | La baja vuelve a `/clientes` sin la búsqueda de donde se vino | Falla "con la confirmación, DELETE y al listado de donde se vino…" |

**Validación en el navegador** (2026-11-13): Edge sin ventana (`channel: 'msedge'`) contra el backend real (`syncvet.test`, base
`syncvet`) y un Vite propio en `:5180` (que **no** quedó detenido: ver el desvío 13), con un script fuera del repositorio. 19 de 19 en verde (segunda corrida;
ver el desvío 11):

| # | Prueba | Resultado |
|---|---|---|
| 1 | `admin-a`: "Nuevo cliente" y el alta, con "Persona" y "Recibe recordatorios" marcados | OK |
| 2 | Guardar vacío: los obligatorios con sus mensajes y el foco en "Nombre", sin `POST` | OK |
| 3 | Cambio de tipo con datos cargados: persona → empresa (los nombres se ocultan, el documento queda) → persona (vuelven) | OK |
| 4 | Email `@example.com`: el navegador lo acepta y la API lo rechaza (la base de desarrollo tiene `EMAIL_DNS_CHECK=true`); el 422 en el campo, con el foco | OK |
| 5 | Alta de persona: al detalle, con el email en minúsculas y el teléfono sin espacios | OK |
| 6 | Documento repetido (el de Laura): el 422 en "Documento", con el foco | OK |
| 7 | Edición (teléfono y notas): al detalle con lo guardado | OK |
| 8 | `recepcion-a`: RUC con el dígito mal, el error en el campo y el foco, sin `POST`; con el dígito bien, la empresa creada; "Editar" sí, "Eliminar" no; edición de la empresa; editar a Laura y "Cancelar" | OK |
| 9 | `vet-a`: "Nuevo cliente" y "Editar" sí, "Eliminar" no (`clinic-roles.json`: `clients.store` y `clients.update` sí, `clients.destroy` no) | OK |
| 10 | 768 px: formulario, detalle y diálogo sin desplazamiento horizontal de la página | OK |
| 11 | El diálogo con el teclado: el foco en "Cancelar", Tab y Shift+Tab sin salir, Escape cierra y el foco vuelve a "Eliminar" | OK |
| 12 | Baja de Laura Martínez (demo, con pacientes): `CLIENT_IN_USE` en el diálogo, solo "Cerrar", con el foco | OK |
| 13 | Baja de los dos clientes creados: al listado con el aviso; la baja vuelve a la página del listado de donde se vino (`?pagina=2`) | OK |
| 14 | Los clientes creados ya no están (búsqueda por documento en la API) | OK |
| 15 | Consola | OK: sin errores ni avisos de la aplicación; solo los registros de red de los dos 422 y el 409 esperados |

Capturas (fuera del repositorio, en la PC de la validación), en `%TEMP%\claude\…\scratchpad\capturas-1-2`:
`00-listado-nuevo-cliente.png`, `01-formulario-persona.png`, `02-formulario-empresa-ruc.png`, `03-formulario-768.png`,
`04-dialogo-768.png`, `05-dialogo-baja.png`, `06-dialogo-baja-409.png` y `07-detalle-editar-eliminar.png`. **Datos de la demo:**
los clientes creados se borraron con la baja de la aplicación (ver el desvío 12).

**Desvíos.**

| # | Desvío | Motivo | Estado |
|---|---|---|---|
| 1 | La búsqueda del listado de donde se vino viaja en el estado del historial (`clientList`, `features/clients/navigation.ts`, validada con `validateClientListSearch`): la baja, el 404 en una acción y "Cancelar" del alta vuelven a `/clientes` **con esa búsqueda y página**, y el enlace "Clientes" del encabezado también. El diseño decía "vuelve a `/clientes`" | El pedido: "si la página quedó fuera de rango, se aplica la regla de la 1.1"; sin la página de donde se vino, la baja siempre volvería a la 1. En la URL no va (no es parte del detalle) | Aceptado (usuario, revisión) |
| 2 | Caché: el alta y la edición invalidan `['clients']` sin volver a pedir (`refetchType: 'none'`) y ponen la respuesta en `['clients', 'detail', id]` (el alta, con `patients: []`: el 201 no los trae); la edición invalida además `['patients', 'detail']`. La baja **vacía** los listados (`resetQueries`) antes de volver y saca el detalle después | Invalidar solo dejaba ver un momento el cliente borrado en el listado (los datos viejos mientras llega el nuevo pedido) | Aceptado (usuario, revisión); con test desde las correcciones (mutación Q1) |
| 3 | Guardar y "Cancelar" reemplazan la entrada del formulario (`replace`): "Atrás" no vuelve al formulario. Después de editar, el historial queda listado, detalle, detalle | Volver a un formulario ya guardado lo mandaría otra vez | Aceptado (usuario, revisión) |
| 4 | `ruc.test.ts` copia la tabla de `RucCheckDigitTest` con sus **RUC reales** (los publicados por la DNIT) | Pedido del usuario (los casos del backend, no inventados). `FictitiousDocumentsTest` del backend no revisa `frontend/`: hoy nada lo controla allá | Resuelto en las correcciones: los 7 son de empresas (ninguno de una persona física) y quedan, en `ALLOWED` de `FictitiousDocumentsTest`, que ahora revisa el frontend (corrección 5) |
| 5 | `SelectField` no suma el error (el formulario no tiene selectores); `CheckboxField` deja la pista fuera de la etiqueta (asociada con `aria-describedby`); `Button` admite `ref` (el foco del diálogo) | Lo que pidió el código | Aceptado (usuario, revisión) |
| 6 | El formato del email lo revisa `z.email()`, no la validación RFC de Laravel; pueden diferir en casos raros. Con `EMAIL_DNS_CHECK=true` (la base de desarrollo), `@example.com` pasa en el navegador y la API lo rechaza: el 422 llega al campo (prueba 4) | Manda la API (decisión común de la fase) | Aceptado (usuario, revisión); corregido después: el navegador revisa solo la forma mínima (corrección 3) |
| 7 | `playwright.config.ts`: el backend de Playwright corre con `EMAIL_DNS_CHECK=false` | Heredaba `backend/.env` (`true`): los emails de prueba `@example.com` no tienen MX y los casos dependerían de la red. La suite del backend ya corre así | Informativo (usuario, revisión) |
| 8 | Los campos llevan `maxLength` con el máximo de la API: los mensajes de "no debe tener más de…" de Zod no se ven en la interfaz salvo en el teléfono (sin `maxLength`); los cubren los tests de `form.test.ts` | El navegador no deja escribir de más | Informativo |
| 9 | Encabezado del listado y del detalle con `row`/`col` de Tabler ("Nuevo cliente", "Editar" y "Eliminar" a la derecha del título) | Con `d-flex` los botones quedaban debajo del título (visto en la primera captura) | Informativo |
| 10 | La primera corrida final tuvo 1 falla: el test de voseo tomó como texto en inglés el selector de los elementos con foco de `ConfirmDialog` (`"button:not([disabled]), …, select, …"`). Se armó como lista sin espacios y se repitió la corrida completa (`typecheck`, `lint`, `test`, `build`); `test:e2e` corrió una vez, sobre el código final | La regla de "una sola vez" se cumplió sobre el código final | Informativo |
| 11 | La primera corrida del script de validación dio 7 de 18: el alta de persona usó `@example.com` (desvío 6) y un selector de "Empresa" encontró 3 elementos (el listado). No creó ningún cliente. La segunda usó emails `@gmail.com` (con MX) y sumó la prueba 4 | Ajustes del script, no de la aplicación | Informativo |
| 12 | La baja de clientes es lógica (`SoftDeletes`): en la base de desarrollo quedan las filas eliminadas (con el sufijo `//deleted_…`) de los 2 clientes de la validación y su auditoría, y los tokens de los ingresos. Nada visible en la aplicación ni en la API | Es la baja de la aplicación; no se tocó la base a mano | Informativo |
| 13 | El Vite de `:5173` (PID 34856, el del desvío 15 de la 1.1) seguía corriendo; no se usó ni se detuvo (no lo levantó esta sesión). La validación usó uno propio en `:5180`; el informe lo dio por detenido, pero `TaskStop` cortó solo el envoltorio y el proceso de Vite (PID 27608) siguió escuchando hasta la revisión, que lo detuvo por su PID | Regla de procesos del `CLAUDE.md` de la raíz | Informativo |
| 14 | Textos propios no definidos en el diseño: "Editar el cliente", "Datos del cliente", las pistas del documento y del teléfono, "¿Querés eliminar a … ? No se puede deshacer." y el botón "Eliminar a …"; el aviso de éxito es el `message` de la API | Detalles de presentación | Informativo (usuario, revisión) |

**Validación del usuario** (2026-11-13): revisó los formularios en el navegador: "están bien".

#### Revisión (1.2)

La revisión (2026-11-13, sobre `657100e`, como quien no la escribió, sin corregir) comparó el navegador con la API con 195
casos pasados por `clientFormErrors` y por el `StoreClientRequest` real (con `TrimStrings` y `ConvertEmptyStringsToNull`): 183
coincidían y las 12 diferencias eran del email. También probó el doble envío y el foco del diálogo en Edge, y corrió mutaciones.
Encontró 7 hallazgos:

| # | Gravedad | Hallazgo |
|---|---|---|
| 1 | Alta | El doble clic en Guardar mandaba dos `POST`: el segundo chocaba con el índice único y la API respondía **500** (un solo cliente, por el índice). En la edición, dos `PATCH` |
| 2 | Media | `ConfirmDialog`: con un clic en el texto, Shift+Tab sacaba el foco al botón de la página (caso A); el botón rojo se deshabilitaba al confirmar y, después de un error (un 403), el foco quedaba en `body` y Escape no cerraba (caso B) |
| 3 | Media | El email (`z.email()`) rechazaba 11 formas que la API acepta (`josé@…`, dominios con ñ, `juan!x@…`, …) |
| 4 | Media | Pasaban los tests 14 de 15 mutaciones: los límites de largo, el RUC con espacios, la caché de la baja, la invalidación del detalle de pacientes, el botón deshabilitado y la vuelta de la edición (ver el desvío 15) |
| 5 | Media | `FictitiousDocumentsTest` no revisaba el frontend: con su regla, había 15 números fuera del rango ficticio (los RUC con el dígito mal escritos literales, los 7 RUC reales y los importes de `money`) |
| 6 | Baja | El Vite de `:5180` de la implementación seguía corriendo (desvío 13) |
| 7 | Informativo | El fondo se desplazaba con el diálogo abierto; el foco del 422 sigue el orden de la API (Ciudad y Dirección no se alcanzan); sin aviso de cambios sin guardar (pregunta 11) |

Desvío 4: los 7 RUC de `ruc.test.ts` son los de `RucCheckDigitTest` (rankings de contribuyentes de la DNIT de 2010: Telecel,
Petropar, Banco Itaú, Cervecería Paraguaya, DHL, BBVA y Agrotec), todos de empresas, de la serie de las personas jurídicas que
empieza con 80; ninguno de una persona física. En el backend, `FictitiousDocumentsTest` usaba como ejemplos de documento real
dos números de 7 dígitos (el rango de las cédulas reales), que podían ser de personas.

#### Correcciones de la revisión (1.2)

Decisiones del usuario: corregir los hallazgos 1 a 6, con los desvíos 1, 2, 3, 5 y 6 aceptados (y los demás, informativos).

| # | Corrección | Tests |
|---|---|---|
| 1 | **Frontend**: Guardar se deshabilita también con `formState.isSubmitting` (`ClientForm`) y una `ref` deja pasar un solo `mutate` a la vez, en el alta y en la edición (`ClientFormPages`; se suelta en `onSettled`). **Backend** (commit aparte): `ClientService` traduce la violación de los índices únicos de `clients` (`document_number`, `document_base` y `email`), identificada por el nombre del índice (MySQL) o por `tabla.columna` (SQLite), al 422 `VALIDATION_ERROR` del campo con el mensaje de la regla `unique`, en el alta y en la edición; otra violación sigue su camino | Vitest: dos clics seguidos y dos envíos del formulario dan un solo `POST`; dos clics en la edición, un solo `PATCH`; Guardar deshabilitado en el mismo clic y mientras espera. Playwright: `dblclick()` da un solo `201`, un solo cliente y ningún 500. Backend: `ClientUniqueIndexTest` (6 casos, SQLite y MySQL: el alta y la edición con el documento, la cédula del RUC y el email que otro pedido guardó en el medio) y `ClientConcurrencyTest` (MySQL: dos procesos piden la misma alta con el hueco del índice tomado; uno 201 y el otro 422 en `document_number`) |
| 2 | `ConfirmDialog`: las teclas se escuchan en `document` mientras está abierto (Escape cierra; Tab y Shift+Tab recorren solo el diálogo, desde donde esté el foco), el foco vuelve a "Cancelar" si sale del diálogo (`focusin`) o al terminar la espera, los botones inactivos usan `aria-disabled` (`Button` suma `focusableWhenInactive`) y el fondo no se desplaza (`overflow: hidden` en `body`, devuelto al cerrar) | `ConfirmDialog.test.tsx`: caso A (desde el contenedor, Shift+Tab al último botón, Tab al primero y Escape cierra; el foco que sale vuelve a "Cancelar"; Tab y Escape con el foco en `body`), caso B (el botón rojo conserva el foco, `aria-disabled`, sin confirmar dos veces; con el error, el foco en "Cancelar" y Escape cierra) y el desplazamiento |
| 3 | El email, en el navegador, solo con la forma mínima (`^[^\s@]+@[^\s@]+$`, después de pasarlo a minúsculas y sin espacios en los extremos); el formato completo y el DNS los valida la API | `form.test.ts`: pasan `josé@example.com`, `juan@ñandutí.com.py`, `juan!x@example.com` y `o'brien@example.com`; fallan `juan`, `juan@`, `@example.com`, `ju an@example.com` y `juan@@example.com` |
| 4 | Tests nuevos (sin cambios de código) | `form.test.ts`: cada largo en su borde (pasa en el límite y falla en el límite más uno; los mínimos, uno menos) y el RUC con espacios en los extremos. `ClientDetailPage.test.tsx`: después de la baja, con el listado demorado 300 ms, un `MutationObserver` comprueba que el cliente borrado no aparece nunca. `ClientFormPages.test.tsx`: guardar invalida `['patients', 'detail', …]` y no otras consultas; desde el listado, guardar vuelve al detalle con la búsqueda (`?buscar=Laura&pagina=2` en el encabezado) y reemplazando el formulario (3 entradas en el historial) |
| 5 | `FictitiousDocumentsTest` revisa también `frontend/src` y `frontend/e2e` (`*.ts` y `*.tsx`, con las rutas desde la raíz). Los RUC con el dígito mal se arman en el código: `wrongRuc()` en `src/test/clients.ts` (como el de `ClientStoreTest`) y, en `e2e/clientes-abm.spec.ts`, el RUC correcto con su dígito más uno. En `ALLOWED`: `ruc.test.ts` (los mismos RUC de `RucCheckDigitTest`: públicos, de empresas) y los importes de `money.ts` y `money.test.ts` (importes en guaraníes, no documentos). En el backend, los ejemplos de 7 dígitos pasaron a números con un cero adelante, que no pueden ser una cédula (`0456789`, `0.456.789`, `0 456 789` y `0999999`) | El test pasa de 40 a 69 aserciones; con un RUC con el dígito mal escrito literal en `form.test.ts`, falla en `frontend/src/features/clients/form.test.ts:108` |
| 6 | El documento: las dos líneas que daban el Vite de `:5180` por detenido (la validación y el desvío 13). `frontend/CLAUDE.md` §8 ("Velocidad"): un Vite levantado para una prueba se detiene por el PID del proceso `vite.js` (buscado por el puerto y confirmado por su línea de comando), no deteniendo la tarea que lo lanzó, y se comprueba después que el puerto quedó libre | — |
| 7 | El hallazgo 7 (informativo): el desplazamiento del fondo con el diálogo abierto se corrigió con la 2; el foco del 422 sigue el orden de la API (Ciudad y Dirección no llegan a la API sin pasar el navegador) y no hay aviso de cambios sin guardar (pregunta 11): sin cambios | — |

**Mutaciones** (cada una con los tests relacionados, revertida desde una copia del archivo): las 14 que pasaban en la revisión
ahora fallan, y también 6 sobre lo nuevo.

| # | Mutación | Resultado |
|---|---|---|
| R1 a R8 | Nombre máximo 100 → 99, razón social 150 → 149, documento mínimo 5 → 6, email 50 → 49, teléfono de 15 a 14 dígitos, nombre mínimo 2 → 3, ciudad 100 → 99, notas 1000 → 999 | Cada una falla en "…: pasa en el límite y falla pasándolo" |
| R9 | El RUC revisado sin recortar los espacios | Falla "el RUC con espacios en los extremos…" |
| Q1 | La baja invalida los listados en lugar de vaciarlos | Falla "después de la baja, el cliente borrado no aparece nunca…" |
| Q3 | La edición sin invalidar el detalle de los pacientes | Falla "guardar invalida el detalle de los pacientes…" |
| U1 | Guardar sin deshabilitarse (`loading={false}`) | Falla "mientras espera la API, Guardar está deshabilitado" |
| U2, U3 | La edición vuelve al detalle sin la búsqueda; sin `replace` | Falla "desde el listado: guardar vuelve al detalle…" |
| N1 | Sin la `ref` | Falla "dos envíos seguidos del formulario: un solo POST" |
| N2 | Sin `isSubmitting` | Fallan "Guardar se deshabilita en el mismo clic…" y otro |
| N3 a N6 | El diálogo sin el foco de vuelta al salir, sin el foco al terminar la espera, con `disabled` en el botón rojo, sin bloquear el desplazamiento | Cada una falla en `ConfirmDialog.test.tsx` |
| B1 | Backend: `ClientService` sin el `catch` | Fallan los 6 casos de `ClientUniqueIndexTest` (500) y `ClientConcurrencyTest` (`[201, 500]`) |
| F1 | Un RUC con el dígito mal escrito literal en `form.test.ts` | Falla `FictitiousDocumentsTest` |

**Otros Services con el mismo hueco** (aviso, sin cambios; al cerrar, la [deuda 22](../../deuda-tecnica.md), prioridad media, que se corrige al empezar la 1.3a, como commit aparte del backend; decisión del usuario): `PatientService` guarda el microchip (índice
`patients_clinic_id_microchip_unique`) con solo la regla `unique` del Form Request, sin bloqueo ni `catch`: dos altas o ediciones
simultáneas con el mismo microchip darían 500. Los demás índices únicos de negocio de los módulos hechos están cubiertos: por
`CatalogLock` (sucursales, depósitos, especies, razas, la configuración, usuarios), por un `catch` (servicios, veterinarios,
usuarios y las series de `DocumentSequenceService`) o se escriben fuera de la API (`clinics.ruc`, `clinic:create`).

**Cifras.** `npm test`: 502 tests en 29 archivos, en verde (antes 466; sin archivos nuevos). `typecheck`, `lint` y `build`, sin
errores ni avisos. `npm run test:e2e`: 17 de 17 en verde (3,0 min; el caso nuevo es el del doble clic), en la segunda corrida (ver
el desvío 16). Backend, desde `backend/`: `composer test:fast`, 1458 casos (1443 en verde y 15 omitidos, los de solo MySQL); en
serie, SQLite, 1458 casos y 14262 aserciones (15 omitidos); MySQL, 1458 casos y 14342 aserciones, sin omitidos. Newman de la colección de clientes (`syncvet-fase-01-clientes`), desde la raíz con un servidor propio en `:8002` levantado desde `backend/` (detenido por su PID, con el puerto libre comprobado): 24 pedidos y 48 aserciones, sin fallas.

**Validación en el navegador** (2026-11-13), solo de lo que cambió: Edge sin ventana contra `syncvet.test` (base `syncvet`) y un
Vite propio en `:5182`, detenido por el PID de `vite.js` y con el puerto libre comprobado. 8 de 8 en verde:

| # | Prueba | Resultado | Detalle |
|---|---|---|---|
| 1 | Doble clic en Guardar | OK | Un solo `POST` (201), un solo cliente con ese documento y ningún 500 (el log del backend no tiene choques nuevos con el índice) |
| 2 | Un email con tilde (`josé.tilde.syncvet@gmail.com`) | OK | El navegador lo deja pasar y la API lo guarda (el detalle lo muestra) |
| 3 | Caso A | OK | Clic en el título, Shift+Tab → "Eliminar a José Tilde"; Tab → "Cancelar"; el fondo con `overflow: hidden`; Escape cierra y el foco vuelve a "Eliminar" |
| 4 | Caso B con la API real (Laura, demo con pacientes; el `DELETE` demorado 800 ms) | OK | Mientras espera, el foco sigue en "Eliminar a Laura Martínez" (`aria-disabled`); con el 409, en "Cerrar"; Escape cierra |
| 5 | Caso B con un 403 (el `DELETE` interceptado) | OK | Con el error, el foco en "Cancelar"; Escape cierra |
| 6 a 8 | Baja de los 2 clientes creados desde el diálogo, y que ya no estén | OK | — |

Consola: solo los registros de red del 409 y del 403 esperados. Capturas (fuera del repositorio, en la PC de la validación):
`01-email-con-tilde.png`, `02-dialogo-caso-a.png`, `03-dialogo-caso-b-409.png` y `04-dialogo-caso-b-403.png`, en
`%TEMP%\claude\…\scratchpad\revision-1-2\capturas-1-2-correcciones`. **Datos de la demo:** los 2 clientes creados se borraron con
la baja de la aplicación (quedan sus filas eliminadas y la auditoría, como en el desvío 12).

**Desvíos de las correcciones.**

| # | Desvío | Motivo | Estado |
|---|---|---|---|
| 15 | La revisión informó "13 de 15 mutaciones"; en realidad pasaban 14 (todas salvo "guardar sin poner la respuesta en el detalle") | Un error de cuenta del informe; la corrección se verificó sobre las 14 | Informativo |
| 16 | La primera corrida de `test:e2e` dio 16 de 17: el caso del doble clic falló en el ayudante `deleteFromDetail`, que esperaba volver a `/clientes` exacto, y la baja vuelve con la búsqueda de donde se vino (`?buscar=…`, desvío 1). El doble clic ya había pasado (un solo 201). El ayudante acepta ahora la búsqueda y la corrida completa se repitió (17 de 17) | Un test, no la aplicación | Informativo |
| 17 | El email con la parte local entre comillas y un espacio (`"juan perez"@example.com`) sigue rechazado en el navegador (la API lo acepta) | La forma mínima elegida no admite espacios | Aceptado (usuario, cierre) |
| 18 | Dos tests más que los pedidos para el doble envío: "dos envíos seguidos del formulario" (la `ref`) y "Guardar se deshabilita en el mismo clic" (`isSubmitting`) | Con solo los dos clics seguidos, cada mecanismo tapaba al otro: quitar la `ref` o `isSubmitting` por separado no hacía fallar ningún test (N1 y N2) | Informativo |
| 19 | El caso B se validó con un 409 real (Laura) y con un 403 interceptado (`page.route`) | La API no le responde 403 al administrador, el único que ve "Eliminar" | Informativo |
| 20 | El motivo de `ruc.test.ts` en `ALLOWED` no escribe "80.000.000": la primera versión lo hacía y el propio test lo marcó como documento | — | Informativo |
| 21 | `FictitiousDocumentsTest` va en el commit WIP del frontend, no en el del backend: su `ALLOWED` nombra archivos del frontend y cambia con ellos | El commit aparte del backend tiene solo la corrección 1 (`ClientService` y sus dos tests) | Informativo |
| 22 | En `:8001` sigue un servidor de PHP del 12/11 a las 09:06 (PID 12580, probablemente el de Newman de front-01) y en `:5173` el Vite del desvío 13; no se tocaron | No los levantó esta sesión (regla de procesos del `CLAUDE.md` de la raíz) | Informativo |
| 23 | La primera corrida de Newman usó `base_url` sin `/api` (`http://127.0.0.1:8002`): los 24 pedidos dieron 404 y no se creó nada. La segunda, con `/api`, sin fallas | Un error al pasar la variable | Informativo |

**Validación.** El usuario revisó los formularios en el navegador ("están bien") y dio la entrega por validada con las
correcciones, con la validación de Edge sin ventana de la implementación (19 de 19) y de las correcciones (8 de 8) como evidencia, y
los desvíos 1, 2, 3, 5 y 6 aceptados (los demás, informativos; el 4, resuelto en las correcciones con las excepciones
justificadas de los RUC públicos de empresas; el 17, la única diferencia del email con la API, aceptado) (R-067). Newman de la
colección de clientes, 48 de 48. El hueco de `PatientService` quedó como la [deuda 22](../../deuda-tecnica.md), prioridad media: se
corrige al empezar la 1.3a, como commit aparte del backend (decisión del usuario; anotado en el [diseño de la 1.3a](#13a-pacientes-listado-detalle-alta-y-edición)).

Corrida de `npm test` del frontend (2026-11-13): 502 tests en 29 archivos, en verde.

### Entrega 1.3a

Estado: **COMPLETADO** el 2026-11-14 (R-068). Pacientes: listado, detalle, alta (desde el cliente) y edición, en `feature/front-1-3a-pacientes` (desde `feature/front-1-2-clientes-abm`
en `d36c37b`), sin integrar a `main` (se integra al cerrar la F1). Commits: `f12f3c7` (backend, deuda 22), `c68511c` (WIP de la
implementación), `150216e` (WIP de las correcciones de la revisión y el presupuesto por entrega) y el de este cierre (R-068).

**Implementación.**

* **Backend, deuda 22** (commit aparte `f12f3c7`): `PatientService::respectingMicrochipIndex` traduce la violación del índice del
  microchip (`patients_clinic_id_microchip_unique` en MySQL, `patients.microchip` en SQLite) al 422 de la regla `unique` en
  `microchip`, en el alta y en la edición. `PatientUniqueIndexTest` (4 casos, SQLite y MySQL) y `PatientConcurrencyTest` (dos altas
  simultáneas con dos procesos, MySQL: 201 y 422). Mutaciones: 4 de 4 detectadas (toda violación tomada como del microchip; la
  columna sola en vez de `patients.microchip`; el alta y la edición sin la traducción). `composer test:fast`: 1463 tests en verde;
  `composer test` (SQLite en serie): 1463 tests, 1447 en verde y 16 saltados (solo MySQL), 14274 aserciones; `composer test:mysql`:
  1463 de 1463, 14360 aserciones. Newman de `syncvet-fase-01-pacientes` contra un servidor propio en `:8001` (detenido por su
  PID): 30 pedidos, 63 de 63 aserciones. La deuda 22 pasó a "Resueltas" y `backend/CLAUDE.md` §3, §6.2 y §7 se actualizaron.
* **Frontend**: `/pacientes` (tabla de 20, búsqueda, especie y "Incluir los fallecidos" en la URL, con el dueño enlazado),
  `/pacientes/$id` (datos, alergias resaltadas, el dueño o "Cliente eliminado"), `/clientes/$id/pacientes/nuevo` y
  `/pacientes/$id/editar` (`PatientForm`), el nombre del paciente como enlace y "Nuevo paciente" en el detalle del cliente, el menú
  "Pacientes" y los permisos `patients.index`, `.show`, `.store` y `.update`. Vitest: 632 tests en 35 archivos, en verde (antes 502
  en 29); `typecheck`, `lint` y `build` sin errores ni avisos. Mutaciones: 5 de 5 detectadas (la raza sin vaciar al cambiar de
  especie, "hoy" de la PC en vez del de Asunción, el peso con coma mandado tal cual, sin el bloqueo del doble envío, sin invalidar
  `['clients']`). Playwright: el caso nuevo `e2e/pacientes.spec.ts` (alta desde Laura Martínez, edición, microchip repetido → 422 y
  búsqueda por el dueño en `/pacientes`, borrando por la API lo que crea) y `e2e/permisos.spec.ts` con "Pacientes" en el menú.
* **Validación en el navegador** (2026-11-14): Edge sin ventana contra `syncvet.test` (base `syncvet`) y un Vite propio en `:5181`
  (detenido por su PID), con un script fuera del repositorio, como `admin-a`, `recepcion-a` y `vet-a`: 26 de 26 en verde (segunda
  corrida, ver el desvío 9). Capturas en `%TEMP%\claude\…\scratchpad\capturas-1-3a`: `01-listado.png`, `02-formulario-alta.png`,
  `03-detalle.png`, `04-listado-768.png`, `05-detalle-768.png` y `06-formulario-768.png`. **Datos de la demo:** los pacientes 29 y
  30 ("Valida Uno", uno por corrida) se crearon y se borraron por la API (`DELETE`, 200; después, 404).

**Desvíos.**

| # | Desvío | Motivo |
|---|---|---|
| 1 | La sincronización del campo de búsqueda con la URL de `ClientListPage` pasó a un hook común, `useSearchText` (`src/lib/hooks/use-search-text.ts`, con `SEARCH_DEBOUNCE_MS`), y `normalizeSearchText`, `SEARCH_MAX_LENGTH` y la validación del id (`parseRouteId`) a `src/lib/search-text.ts`; `clients/search.ts` los reexporta | Pacientes necesita la misma lógica (el espacio final, la tecla durante la navegación, "Atrás"): copiarla duplicaba la parte más delicada de la 1.1. Sin cambio de comportamiento: los 57 tests del listado y la búsqueda de clientes, en verde sin tocarlos |
| 2 | `SelectField` suma `error`, `hint`, `ref` (para `register`) y la marca de obligatorio; `DateField` es un `TextField` con `type="date"` | El formulario del paciente es el primero con selectores (el desvío 5 de la 1.2 lo dejó para cuando hiciera falta) |
| 3 | La especie o la raza inactiva del paciente se muestra con "(inactivada)", no "(inactiva)" como dice el diseño | `voseo.test.ts` toma "inactiva" como el imperativo de tú de "inactivar" |
| 4 | Al editar un paciente con una especie inactiva se ofrece solo su raza actual (con "(inactivada)"), no las demás razas activas de esa especie | `GET species` sin `include_inactive` no trae la especie inactiva ni sus razas (la clave del diseño es `['species', { include_inactive: false }]`). Para ofrecerlas habría que pedir `include_inactive=1` en la edición: **decisión del usuario en la revisión: queda así para el MVP** |
| 5 | El alta guarda y va al detalle del **paciente** nuevo (no al del cliente); el detalle del cliente muestra el paciente porque el alta invalida `['clients']` | "Guardar y volver: al detalle", como el alta de clientes; el caso de Playwright entra al cliente desde el enlace del dueño y ve la tarjeta |
| 6 | El formulario llama "Sin dato" al sexo `unknown` (el diseño); el listado y el detalle muestran la etiqueta de la API, "Sin determinar" | El formulario usa sus propias etiquetas; las vistas muestran el `label` de la API sin reescribirlo. Resuelto en las correcciones de la revisión: el formulario dice "Sin determinar", como la API (y el diseño) |
| 7 | `/pacientes` no tiene "Nuevo paciente": el alta es solo desde el cliente (con un texto que lo dice debajo del título) | Pregunta 7 del diseño: el dueño sale de la URL |
| 8 | El backend: en `PatientUniqueIndexTest` el duplicado que arma el oyente entra en la transacción del alta y se revierte con ella (se verifica que no quede ningún paciente, no "solo el primero" como en clientes); "otra violación sigue su camino" se prueba en el servicio (una edición que repite el código), porque por la API ninguna otra violación llega a esa regla | Sin ese test, la mutación "toda violación es del microchip" sobrevivía |
| 9 | La primera corrida de la validación en el navegador dio 21 de 26: las 5 fallas eran del script (leía la tabla antes de que llegara la respuesta nueva, que `keepPreviousData` reemplaza después; la tarjeta del dueño se actualiza en segundo plano; comparaba contra "Mestizo", que también es una raza de Felino). Con esperas, 26 de 26 | Sin cambios en la aplicación |
| 10 | El selector nativo de la fecha no se vio con ventana (Edge sin ventana): se comprobó `type="date"`, el `max` con el día de Asunción y el rechazo de mañana sin `POST` | El diseño pedía mirar el selector nativo: queda para la revisión con ventana |
| 11 | La primera corrida completa de Playwright dio 14 de 18: los 3 de `permisos.spec.ts` (el menú esperado sin "Pacientes", corregido) y "alta de una empresa" de `clientes-abm.spec.ts`, cortado por el límite de 60 s esperando el campo Email con la PC cargada (8,5 min la corrida). Los dos archivos, solos: 10 de 10 | Resuelto: la corrida completa final, en verde (abajo) |

**Corrida completa final de Playwright** (2026-11-14): 18 de 18 en verde, en 4,4 min; los puertos `:8010` y `:5174` quedaron libres.

Los 11 desvíos, aceptados por el usuario en la revisión (el 4 queda así para el MVP; el 6, unificado en "Sin determinar").

**Revisión** (2026-11-14, con el [presupuesto por entrega](../../../../CLAUDE.md#presupuesto-por-entrega): lectura del diff, 4 mutaciones y
solo `npm test`, 632 en verde). Backend sin hallazgos. Hallazgos del frontend, de más grave a menos grave:

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | El sexo `unknown`: "Sin dato" en el formulario y "Sin determinar" en el listado y el detalle (desvío 6; decisión del usuario: unificar) | `PatientForm`, `form.ts`, el test y el diseño dicen "Sin determinar", el texto de `PatientSex::label()` |
| 2 | Una fecha de nacimiento a medio escribir: el `<input type="date">` da `''` (`validity.badInput`) y se mandaba `null`; en la edición borraba la fecha guardada sin aviso | Al enviar se lee `validity.badInput` del campo: el error "La fecha de nacimiento debe tener el formato AAAA-MM-DD." con el foco y no se manda (también con otros errores en el formulario). Tests en el alta y en la edición (jsdom no simula `badInput`: se pone en el campo) |
| 3 | Sin test de que el detalle muestre lo recién guardado (mutación M1, sin `setQueryData`, sobrevivía) | El test de la edición mira el detalle después de guardar, con `GET` devolviendo lo de antes |
| 4 | Sin test de que "Incluir los fallecidos" vuelva a la página 1 (M2 sobrevivía) | Caso desde `?pagina=2` |
| 5 | Sin test del 409 `CONFLICT` en la edición (M3 sobrevivía) | Caso con el aviso y el paciente pedido otra vez |
| 6 | `?especie=` de una especie que no está en el selector (inexistente o inactivada) mostraba "Todas las especies" con el filtro aplicado; y el `maxLength` del navegador cuenta unidades UTF-16 (un emoji es 2) y la API, caracteres (`mb_strlen`) | La especie se muestra elegida como "Especie no disponible" (no se descarta: una inactivada todavía tiene pacientes y la API los filtra bien). Sin `maxLength` nativo en el nombre, el color, las alergias y las notas del paciente y en las notas del cliente (1.2): el largo lo controla Zod con el mensaje de la API. Tests: 1000 emojis en las alergias se mandan (1001, el error); en las notas del cliente, 1000 letras `𠀀` (U+20000; un emoji no pasa la regla de caracteres de las notas) |

**Correcciones de la revisión** (2026-11-14): mutaciones M1, M2 y M3, ahora detectadas; también la fecha a medio escribir ignorada
y sin "Especie no disponible" (5 de 5). Corrida completa del frontend: `typecheck`, `lint` y `build` sin errores ni avisos; Vitest,
642 tests en 35 archivos, en verde. Playwright, solo `e2e/pacientes.spec.ts`: 1 de 1. Edge sin ventana contra `syncvet.test` (Vite
propio en `:5181`, detenido por su PID), 5 de 5 sin escrituras en la base: "Sin determinar" en el formulario; el alta con solo el
día escrito en el selector nativo (`badInput`), el error y ningún `POST`; la edición de un paciente con fecha (borrado el día),
el error, ningún `PATCH` y la fecha guardada sin cambios; "Sin determinar" en el listado y el detalle (PAC-000005); y
`?especie=999999` con "Especie no disponible" y la consola sin errores. Capturas en
`%TEMP%\claude\…\scratchpad\capturas-1-3a-correcciones`: `01-alta-fecha-a-medio-escribir.png` y
`02-listado-especie-no-disponible.png`. **Datos de la demo:** sin cambios.

**Último cambio antes del cierre** (2026-11-14, pedido del usuario): sin `maxLength` nativo también en los demás textos con letras de
clientes (nombre, apellido, razón social, dirección y ciudad), como en las notas: el largo se cuenta en caracteres, como la API
(`mb_strlen`). El documento y el email lo conservan. Tests: los campos sin `maxlength` y una dirección de 255 letras `𠀀` (2
unidades UTF-16 cada una) que se manda; en `form.test.ts`, la dirección (255) y la ciudad (100) cuentan caracteres (pasan en el
límite, fallan pasándolo). El nombre y el apellido no admiten letras fuera del plano básico (la regla de `PersonName`: solo letras
latinas), así que allí no puede darse. Con el presupuesto por entrega: `npx vitest related` de clientes, 355 tests en 16 archivos en
verde, y ESLint de los tres archivos tocados, sin errores.

**Validación.** El usuario probó la 1.3a en el navegador y salió todo bien (2026-11-14), con los 11 desvíos aceptados (el 4 queda así
para el MVP; el 6, unificado en "Sin determinar") (R-068). Evidencia: Edge sin ventana, 26 de 26 de la implementación y 5 de 5 de
las correcciones; Playwright, 18 de 18 en la implementación y `e2e/pacientes.spec.ts`, 1 de 1, después de las correcciones; backend
del commit `f12f3c7`, 1463 casos (14274 aserciones en SQLite en serie, 16 omitidos; 14360 en MySQL, sin omitidos) y Newman de la
colección de pacientes, 63 de 63.

Corrida de `npm test` del frontend (2026-11-14): 642 tests en 35 archivos, en verde.

Después de esa corrida, el último cambio sumó 3 tests (645 en 35 archivos), verificados solo con `vitest related` (el presupuesto por
entrega); la próxima corrida completa los cuenta.

### Entrega 1.3b

Estado: **COMPLETADO** el 2026-11-14 (R-069). Pacientes: fallecimiento (registrarlo, corregirlo y anularlo), cambio de dueño y baja,
en `feature/front-1-3b-pacientes` (desde `feature/front-1-3a-pacientes` en `5f3aac7`), sin integrar a `main` (se integra al cerrar
la F1). Commits: `9bf37ba` (WIP de la implementación), `c8ca294` (el hook que bloquea `cat` sin entrada, aparte) y el de este
cierre (R-069: las correcciones de la revisión y la documentación).

**Implementación.**

* **Frontend**: en `/pacientes/$id`, "Registrar el fallecimiento" (`patients.update`; diálogo con `DateField` y `TimeField`, por
  defecto ahora en Asunción), el aviso "Falleció el dd/MM/aaaa HH:mm" con "Corregir la fecha" y "Anular el fallecimiento" (con
  confirmación), "Cambiar de dueño" (`patients.change-owner`: buscador `['clients', 'options', search]` con 300 ms de espera, sin el
  dueño actual) y "Eliminar" (`patients.destroy`, `ConfirmDialog`, `PATIENT_IN_USE` en el diálogo con "Registrar el fallecimiento").
  La edición de un fallecido deja editables solo las notas. La fecha y la hora, en `features/patients/deceased.ts` (con sus tests);
  los diálogos, en `PatientActions.tsx`. `ConfirmDialog` suma `tone`, `size` y `confirmDisabled`; `TimeField` es nuevo;
  `PERMISSIONS` suma `patientsChangeOwner` y `patientsDestroy`. La marca "Fallecido" del listado y del detalle del cliente ya
  estaba (1.1 y 1.3a). Vitest: 689 tests en 37 archivos (antes 645 en 35); `typecheck`, `lint` y `build` sin errores. Mutaciones:
  4 de 4 detectadas (`deceased_at` con `toISOString()` de un `Date` local; la edición del fallecido con los campos habilitados; el
  dueño actual ofrecido en el buscador; sin la `ref` del doble envío, después del caso del desvío 10). Playwright: el caso nuevo
  `e2e/pacientes-estado.spec.ts`, 1 de 1 en 1,3 min.
* **Desvío 8 de la 0.1: VERIFICADO** (anotado en el [resultado de la 0.1](f0-base.md#entrega-01)): `deceased_at` se manda con
  `toApiDateTime` (`2026-11-10T10:30:00.000-03:00`), la API devuelve `2026-11-10T13:30:00.000000Z` y al recargar se ve 10:30, en
  Playwright (`syncvet_e2e`) y en Edge sin ventana (`syncvet.test`).
* **Validación en el navegador** (2026-11-14): Edge sin ventana contra `syncvet.test` con un Vite propio en `:5181` (detenido por su
  PID), script fuera del repositorio: **10 de 10** en verde (fallecimiento con `-03:00` y 13:30 UTC; la misma hora al recargar;
  "Fallecido" en el listado y en el detalle del cliente; la anulación; el cambio de dueño sin ofrecer el actual; el paciente en
  Carlos Duarte y ya no en Laura Martínez; la baja con el foco en "Cancelar" y 404 después; `vet-a` sin cambio de dueño ni baja;
  `recepcion-a` sin baja; la demo como estaba y la consola sin errores). Capturas en
  `%TEMP%\claude\…\scratchpad\capturas-1-3b`: `01-dialogo-fallecimiento.png`, `02-detalle-fallecido.png`, `03-dialogo-anular.png`,
  `04-dialogo-cambio-de-dueno.png` y `05-dialogo-baja.png`. **Datos de la demo:** el paciente "Valida Brisa" se creó en Laura
  Martínez por la API, pasó a Carlos Duarte y se borró desde la interfaz (después, 404); los pacientes de los dos clientes, como
  antes.

**Desvíos.**

| # | Desvío | Motivo |
|---|---|---|
| 1 | Registrar, corregir y anular el fallecimiento mandan `PATCH patients/{id}` **solo con `deceased_at`**, no el formulario completo | La API lo admite (`sometimes`) y deja lo demás como está; reenviar el formulario no agrega nada y expone a `PATIENT_DECEASED` o a una especie inactivada. H5 (el formulario completo de un fallecido) se prueba en la edición de las notas |
| 2 | La fecha de fallecimiento no está en el formulario de edición: se corrige desde el detalle ("Corregir la fecha", el mismo diálogo con la guardada). La edición de un fallecido deja editables solo las notas, con un aviso, y manda los demás campos con el valor guardado (no con lo que tengan los campos) | Un solo lugar para la fecha y la hora (con su validación y su `-03:00`); el diseño decía "editables solo la fecha de fallecimiento y las notas" |
| 3 | `ConfirmDialog` suma `tone` (`primary` para lo que no borra), `size` (`md`, con campos o una lista) y `confirmDisabled`; los diálogos del fallecimiento y del cambio de dueño son `ConfirmDialog` con campos. `TimeField` nuevo (`<input type="time">`) | Reutilizar el foco, el teclado y el error de negocio del diálogo de la 1.2 en lugar de un segundo diálogo |
| 4 | El buscador del dueño nuevo usa `useDebouncedValue` y `normalizeSearchText`, no `useSearchText`; sin texto no pide nada (muestra una ayuda) | `useSearchText` sincroniza con la URL y el diálogo no tiene URL |
| 5 | Con `PATIENT_IN_USE`, el botón de la acción del diálogo pasa a "Registrar el fallecimiento" (azul), en lugar de sumar un tercer botón; con un fallecido (o sin `patients.update`), solo "Cerrar" | `ConfirmDialog` tiene dos botones; el resultado es el que pide el diseño |
| 6 | La baja con el 200 va al detalle del dueño; si el dueño está eliminado o el usuario no tiene `clients.show`, al listado de pacientes | El detalle de un cliente eliminado da 404 |
| 7 | La hora tiene un mensaje del navegador, "La hora de fallecimiento no es válida."; la fecha usa los de `PatientMessages` | La API recibe un solo campo (`deceased_at`) |
| 8 | Playwright y el navegador hicieron la baja de un paciente recién creado; la baja rechazada (`PATIENT_IN_USE`) y el diálogo que lleva al del fallecimiento quedaron solo en Vitest | Lo pidió el usuario para esta entrega; probarlo contra la API necesita un paciente con turnos, y uno de la demo se borraría si no los tiene |
| 9 | El caso de Playwright lleva `test.setTimeout(180_000)` (dura 1,3 min). Las dos primeras corridas fallaron por el caso: hizo clic en "Eliminar" del **cliente** antes de que se dibujara el detalle del paciente (abrió el diálogo del cliente, sin confirmarlo; la base es `syncvet_e2e`). Ahora espera el título y usa `.patient-delete` | Sin cambios en la aplicación |
| 10 | La mutación "sin el `ref` del doble envío" sobrevivió al primer test (el doble clic lo frena el botón en espera); se sumó el caso de dos envíos del formulario (Enter) y quedó detectada | El `ref` protege el envío del formulario, no el botón |
| 11 | El selector de hora nativo de Edge muestra "10:30 a. m." (12 horas, por el idioma `es-PY` del navegador); el valor del campo es `10:30` | Informativo |

Los 11 desvíos, aceptados por el usuario en la revisión (también el 11, la hora en 12 horas del selector de Edge, que depende del
idioma de Windows).

**Revisión** (2026-11-14, con el [presupuesto por entrega](../../../../CLAUDE.md#presupuesto-por-entrega): lectura del diff, 4 mutaciones y
solo `npm test`, 689 en verde). Mutaciones: 3 de 4 detectadas (el mismo día del nacimiento rechazado, el valor por defecto con la
zona de la PC, `PATIENT_IN_USE` de un fallecido que ofrecía registrarlo); sobrevivió "sin el foco en la fecha tras el 422", porque
el foco nunca quedaba ahí (hallazgo 1). Hallazgos, de más grave a menos grave, y lo que decidió el usuario:

| # | Hallazgo | Corrección |
|---|---|---|
| H1 | Después de un 422 de la API en `deceased_at`, el error se veía en la fecha pero el foco terminaba en "Cancelar": `ConfirmDialog` lo llevaba ahí al terminar la espera, después de que el diálogo lo pusiera en el campo (confirmado con un test temporal) | `ConfirmDialog`, de forma general: al terminar la espera, el foco va al primer campo con error del diálogo (`aria-invalid="true"`) y, si no hay, a "Cancelar"; sin la acción (`*_IN_USE`), a "Cerrar". El diálogo del fallecimiento ya no mueve el foco por su cuenta tras el 422. Tests: en `ConfirmDialog.test.tsx` (con campo con error y sin él), en el 422 del fallecimiento y en la baja de clientes de la 1.2 (el 403 deja el foco en "Cancelar") |
| H2 | Después de la baja o del cambio de dueño, el detalle del dueño en la caché (el loader lo usa tal cual) mostraba un momento la lista vieja de pacientes, con el borrado o el que se fue | La solución de la baja de clientes de la 1.2: `resetClientDetails` (`features/patients/api.ts`) vacía con `resetQueries` el detalle del dueño en la baja (y en el 404 de una acción) y los de los dos clientes en el cambio de dueño; al entrar, se espera la lista nueva. Tests: la baja con el detalle del dueño en la caché nunca muestra al paciente; el cambio de dueño deja vacíos los dos detalles |
| H3 | `confirmDisabled` sin un test que lo distinga: `onConfirm` ya controla que haya un cliente elegido | Test en `ConfirmDialog.test.tsx`: `aria-disabled`, sin soltar el foco y sin confirmar; al habilitarse, confirma |
| H4 | El buscador del dueño nuevo sin el test de la respuesta fuera de orden (`frontend/CLAUDE.md` §5) | Test: la primera búsqueda contesta después que la segunda y la lista sigue con la segunda |
| H5 | `keepPreviousData` repetido (`clientOptionsQueryOptions` y el `useQuery` del diálogo); en la edición de un fallecido, Zod validaba también los campos bloqueados: un dato guardado que hoy no pasara mostraba un error en un campo deshabilitado y no dejaba guardar las notas | `keepPreviousData` solo en `clientOptionsQueryOptions`. `deceasedPatientFormSchema` (`form.ts`) valida solo las notas; `PatientForm` lo usa con `locked`. Test: un fallecido con un microchip guardado que hoy no pasa guarda las notas, y más de 1000 caracteres en las notas siguen rechazados |

**Correcciones de la revisión** (2026-11-14). Mutaciones: 3 de 3 detectadas (el foco siempre a "Cancelar"; la baja sin vaciar el
detalle del dueño; Zod validando todo en un fallecido). Corrida completa del frontend: `typecheck`, `lint` y `build` sin errores
ni avisos; Vitest, 694 tests en 37 archivos, en verde (5 nuevos). `lint` dio un error en el test nuevo de la baja (un `?.`
innecesario), corregido; después, `lint` y `typecheck` sin errores y ese archivo, 31 de 31. Playwright, solo
`e2e/pacientes-estado.spec.ts` y `e2e/clientes-abm.spec.ts` (por el cambio de `ConfirmDialog`): 5 de 5, en 3,8 min; `:8010` y
`:5174` libres después. Edge sin ventana contra `syncvet.test` (Vite propio en `:5181`, detenido por su PID, 21748), **5 de 5**:
el foco en la fecha tras un 422 de la API (simulado con `page.route`: la regla real ya la frena el navegador); la baja rechazada de
Laura Martínez (`CLIENT_IN_USE` real) con el foco en "Cerrar"; el botón del cambio de dueño inactivo hasta elegir y dos búsquedas
seguidas que muestran la última, sin cambiar el dueño; un fallecido que guarda las notas con lo demás bloqueado; la baja con el
detalle de Laura en la caché, que nunca muestra al borrado, 404 después, la demo como estaba y la consola sin errores. La primera
corrida se cortó en el tercer control por el script (buscaba "Carlos Duarte" entero, y la API busca por campo; ahora, por el
documento) y borró lo que había creado. **Datos de la demo:** el paciente "Valida Nube" se creó en Laura Martínez por la API y se
borró desde la interfaz (después, 404); los pacientes de Laura y de Carlos, como antes.

**Proceso.** Una tarea en segundo plano de la implementación quedó colgada casi 2 horas: el comando empezaba con `cat > /tmp/x`,
que esperaba el teclado, y el resto (un `node -e` que editaba `PatientDetailPage.tsx`) nunca corrió; ya había pasado en la 1.1. Se
detuvo por su PID y se comprobó que `9bf37ba` ya tenía todo lo que ese script quería hacer. Desde `c8ca294`, un hook PreToolUse
de `.claude/settings.json` (`.claude/hooks/block-cat-stdin.js`) rechaza todo `cat >` o `cat >>` sin entrada por un heredoc, una
redirección o una tubería, con la regla al principio del `CLAUDE.md` de la raíz (R-069). Probado: 14 casos con el script solo y,
en vivo, uno rechazado y uno con heredoc que pasa.

**Validación.** El usuario dio la entrega por validada con las correcciones H1 a H5 y los 11 desvíos aceptados (R-069). Evidencia:
Edge sin ventana, 10 de 10 de la implementación y 5 de 5 de las correcciones; Playwright, el caso nuevo en la implementación y 5 de
5 de `pacientes-estado` y `clientes-abm` después de las correcciones; el desvío 8 de la 0.1, verificado contra la API real.

Corrida de `npm test` del frontend (2026-11-14): 694 tests en 37 archivos, en verde.

### Entrega 1.4

Estado: **COMPLETADO** el 2026-11-14 (R-070). Foto del paciente: verla como `Blob`, subirla con vista previa, reemplazarla y quitarla,
en `feature/front-1-4-foto` (desde `feature/front-1-3b-pacientes` en `e20186e`), sin integrar a `main` (se integra al cerrar la F1).
Commits: `dde595a` (WIP de la implementación) y el de este cierre (R-070: las correcciones de la revisión y la documentación).

**Implementación.**

* **Frontend**: `api.getBlob` (`src/lib/api/client.ts`): el mismo armado del pedido que `apiRequest` (ahora en `send` y `failed`,
  sin cambiar su comportamiento), el token en la cabecera; con un 2xx devuelve el `Blob`; los errores JSON son el `ApiError` de
  siempre (el 401 cierra la sesión); un 2xx en JSON es su `ApiError` (`success: false`) o `HTTP_ERROR`; la cancelación se propaga
  tal cual. En `/pacientes/$id`, la tarjeta "Foto" (`features/patients/PatientPhoto.tsx`): la foto con `['patients', 'photo', id]`
  (`staleTime: Infinity`, `gcTime` de 5 minutos), solo si `has_photo` y con `patients.show-photo`, mostrada con `BlobImage` (nuevo:
  la URL de objeto se crea y se libera en la ref del `<img>`); sin foto, "Sin foto"; otro error, su mensaje en el lugar de la foto.
  Subir o reemplazar: `FileField` (nuevo), la vista previa y "Subir la foto"; el navegador frena lo que no es JPG, PNG o WEBP y lo
  de más de 5 MB (`features/patients/photo.ts`, con los mensajes de la API); 422 y 413 con `errors.photo`, al campo (con el foco al
  terminar la espera); 413 sin `errors`, alerta; 404 del paciente, aviso y al listado. "Quitar la foto" con `ConfirmDialog`; con
  `PATIENT_PHOTO_NOT_FOUND`, aviso y el paciente pedido de nuevo. Al subir, `resetQueries` de la foto (se espera la nueva, nunca se
  ve la vieja); al quitar, `Query.reset()` (sin volver a pedirla). Doble envío: el botón en espera y la `ref` de `useSingleAction`.
  `PERMISSIONS` suma `patientsShowPhoto`, `patientsUploadPhoto` y `patientsDeletePhoto` (los tres roles). El listado no descarga
  fotos (test). Vitest: 737 tests en 39 archivos (antes 694 en 37); `typecheck`, `lint` y `build` sin errores. Mutaciones: 4 de 4
  detectadas (sin `revokeObjectURL`; sin el control de tamaño; `getBlob` que toma un 404 como éxito; `invalidateQueries` en lugar de
  `resetQueries` al reemplazar, que mostraba la foto vieja mientras llegaba la nueva). Playwright: el caso nuevo
  `e2e/pacientes-foto.spec.ts` (subir `foto-paciente.png` a un paciente recién creado, verla al recargar, reemplazarla por
  `logo-clinica.webp` y quitarla), 1 de 1.
* **Validación en el navegador** (2026-11-14): Edge sin ventana contra `syncvet.test` (Vite propio en `:5181`, detenido por su PID,
  1796), script fuera del repositorio: **10 de 10** (subir; ver al recargar, con `no-store, private`; volver al detalle sin otra
  descarga; reemplazar por un WEBP, con la URL anterior liberada; un texto renombrado a `.png` rechazado por la API con 422; uno de 6
  MB frenado en el navegador sin pedido; quitar con el diálogo; `vet-a` con el campo para subir; sin los permisos de foto, simulado,
  sin la tarjeta ni pedidos; la demo como estaba). El control 9 se repitió solo: contaba también el módulo `photo.ts` que sirve Vite.
  Los dos errores de la consola los provocaron los controles (el `fetch` a la URL liberada y el 422 esperado). Capturas en
  `%TEMP%\claude\…\scratchpad\capturas-1-4`: `01-vista-previa.png`, `02-detalle-con-foto.png`, `03-texto-rechazado-por-la-api.png`,
  `04-mas-de-5-mb.png`, `05-dialogo-quitar.png` y `06-sin-permisos-de-foto.png`.
* **Foto vertical de un celular**: queda como prueba manual del usuario (la orientación del EXIF la aplica el backend, R-027).

**Desvíos.**

| # | Desvío | Motivo |
|---|---|---|
| 1 | `POST` y `DELETE patients/{id}/photo` responden **200** con el `ShowPatientResource`, no 201 como decía el diseño. El detalle toma esa respuesta (`has_photo`) sin otro pedido | Es el contrato de la API (`PatientPhotoController`); la interfaz no depende del código |
| 2 | La URL de objeto la maneja `BlobImage` (`src/components/ui`): la ref del `<img>` (con limpieza, React 19) crea la URL al montar y la libera al cambiar el `Blob` o al desmontar. No hay un hook con efecto y estado | `react-hooks/set-state-in-effect` rechaza el `setState` en el efecto; además, así el `<img>` nunca tiene la URL de un `Blob` ya liberado |
| 3 | `useSingleAction` y `usePatientGone` pasan de `PatientActions.tsx` a `features/patients/use-patient-action.ts` | Los usa también la foto, y `react-refresh/only-export-components` no deja exportar hooks desde un archivo de componentes |
| 4 | Al quitar la foto, la consulta se vacía con `Query.reset()` (`forgetPatientPhoto`), no con `resetQueries` ni `removeQueries`; al subir, `resetQueries` | Los dos la volvían a pedir (la consulta sigue habilitada hasta que el detalle dice `has_photo: false`): lo mostró el test del borrado, con dos descargas |
| 5 | El lugar vacío lleva un ícono genérico (`IconPaw`) y "Sin foto", no el de la especie | Tabler no tiene un ícono por cada especie del catálogo (también las propias) |
| 6 | El control del navegador miraba el tipo **declarado** y dejaba pasar un archivo sin tipo. **Reemplazado por la corrección 3**: ahora mira el contenido | La API mira el contenido; el navegador no rechaza nada que la API acepta (`frontend/CLAUDE.md` §5) |
| 7 | Con un 422 o 413 de la API en `photo`, el archivo elegido se descarta (sin vista previa) y la foto guardada sigue a la vista | Mantener la vista previa de un archivo rechazado invitaría a mandarlo otra vez |
| 8 | Sin `patients.show-photo` y con foto: "Tiene foto", sin descargarla; sin ninguno de los tres permisos, sin la tarjeta | Los tres roles los tienen todos; el caso solo se da con permisos a medida |
| 9 | "Un rol sin permiso" en el navegador se simuló quitando los permisos de foto de `me` (`page.route`): ningún rol real los pierde (`clinic-roles.json`). Con `vet-a` se comprobó que un rol real sí ve el campo | No hay un rol real sin esos permisos; en Vitest se prueba con los permisos quitados de un rol real |
| 10 | Los tests de la subida usan el `FormData` y el `File` de Node (`node:buffer`; el `FormData`, sacado de una `Response`), no los de jsdom | El `fetch` de Node no puede leer el `File` de jsdom: el pedido se cortaba (`AbortError`), un límite del entorno de test |
| 11 | La foto vertical de un celular (de "En el navegador" del diseño) no se probó en la entrega | No hay una en el repositorio; la orientación la aplica la API (R-027, con sus tests). La prueba el usuario a mano |

Los 11 desvíos, aceptados por el usuario en la revisión.

**Revisión** (2026-11-14, con el [presupuesto por entrega](../../../../CLAUDE.md#presupuesto-por-entrega): lectura del diff, 3
mutaciones y solo `npm test`, 737 en verde). Mutaciones: 3 de 3 detectadas (pedir la foto sin `patients.show-photo`; conservar el
archivo rechazado por la API; `getBlob` sin avisar a la sesión con un 401). Con tests temporales (borrados después) se confirmó que
la URL de objeto se libera también con respuestas fuera de orden al cambiar de paciente y que salir de la sesión vacía la foto y
las mutaciones de la caché (`queryClient.clear()`), libera la URL y no vuelve a pedirla. Hallazgos, de más grave a menos grave:

| # | Hallazgo | Corrección |
|---|---|---|
| 1 | Media (latente): el router reutiliza el componente al cambiar solo el `$id`, así que el archivo elegido, la vista previa, los errores y el diálogo pasaban al paciente siguiente ("Subir la foto" subiría la de Firulais a Michi; el aviso de un pedido anterior caía en el nuevo). Hoy no hay un enlace de un paciente a otro | **Convención nueva** (`frontend/CLAUDE.md` §5, "Una pantalla por registro"): la ruta de cada detalle dibuja su página con `key={id}` (`/pacientes/$id` y `/clientes/$id`). Tests: el archivo elegido no pasa al paciente siguiente; el diálogo de baja abierto no pasa al cliente siguiente |
| 2 | Baja-media: `getBlob` aceptaba como archivo cualquier 2xx que no fuera JSON, también una página HTML (el `index.html` de un servidor mal configurado), que quedaba como foto rota sin mensaje y sin vencer | `getBlob` responde `HTTP_ERROR` con una página HTML y con un tipo distinto de `expectedType`; la foto pide `image/`. Tests en `client.test.ts` y el mensaje en el lugar de la foto |
| 3 | Baja: el navegador rechazaba por el tipo declarado (la extensión): un JPG llamado `.gif`, que la API acepta, no se podía mandar | `photoFileError` (asíncrona) mira los primeros bytes (JPG `FF D8 FF`, la firma de 8 bytes del PNG, `RIFF….WEBP`), como finfo en la API, y el tamaño primero; un contador descarta el resultado de una elección vieja. Un texto renombrado a `.png` ya no llega a la API. Tests: los tres formatos, un JPG llamado `.gif`, un texto, un GIF, un SVG, un WAV, una firma cortada y un archivo vacío |
| 4 | Baja: con `has_photo` y el archivo faltante (404 `PATIENT_PHOTO_NOT_FOUND` al verla) se mostraban "Sin foto" con "Cambiar la foto" y "Quitar la foto" | `markWithoutPhoto`: con ese 404, el detalle pasa a `has_photo: false` (también al quitarla con 404). Test: "Elegir una foto" y sin "Quitar la foto" |
| 5 | Informativa: el `File` subido quedaba 5 minutos en la caché de mutaciones | La mutación lleva `gcTime: 0` y `reset()` en `onSettled`, como las contraseñas. El foco tras el error dependía de la vuelta de `isPending`, que con `reset()` React no llegaba a dibujar: ahora es un pedido de foco propio (un contador en el estado). Test: la caché de mutaciones vacía después de subir |

**Correcciones de la revisión** (2026-11-14). Mutaciones: 3 de 3 detectadas (sin la `key` en el detalle del paciente; sin mirar el
contenido del archivo; sin `has_photo: false` con el 404 al verla). Corrida completa del frontend: `typecheck`, `lint` y `build` sin
errores ni avisos; Vitest, 746 tests en 39 archivos, en verde (9 nuevos). Playwright, solo `e2e/pacientes-foto.spec.ts`: 1 de 1
(26,3 s; 1,3 min con la preparación), con `:8010` y `:5174` libres después. Edge sin ventana contra `syncvet.test` (Vite propio en
`:5181`, detenido por su PID, 6464), solo lo que Playwright no ve, **5 de 5**: un texto renombrado a `.png` frenado en el navegador
sin POST (lectura real de los bytes); un PNG llamado `.gif` aceptado y subido (200); el archivo borrado del disco con `has_photo` en
verdadero en la API: "Sin foto" y "Elegir una foto", sin "Quitar la foto"; otro paciente por el historial (`pushState` y
`popstate`, como Atrás o Adelante) sin la vista previa ni "Subir la foto"; la demo como estaba. El único error de la consola es el
404 de la foto faltante, provocado por el control. Capturas en `%TEMP%\claude\…\scratchpad\capturas-1-4-correcciones`:
`01-texto-frenado-en-el-navegador.png` y `02-archivo-faltante.png`. **Datos de la demo:** el paciente "Valida Foto" se creó en Laura
Martínez por la API y se borró por la API (después, 404); su archivo se borró del disco en el control 3 (el paciente eliminado
conserva la referencia a un archivo que ya no existe, sin efecto) y no quedó ningún archivo; los pacientes de Laura, como antes.

**Presupuesto por entrega** (pedido del usuario en la revisión, en el [`CLAUDE.md` de la raíz](../../../../CLAUDE.md#presupuesto-por-entrega)):
si la corrida final falla por un detalle, se corrigen y se repiten solo el paso que falló y los tests del archivo tocado; si un caso
de Playwright ya cubre un flujo, los controles en Edge son solo para lo que Playwright no ve. En el
[handoff](../../handoff.md#próximas-tareas), la tarea para después del cierre de la F1: una entrega `chore` para que la preparación
de Playwright reutilice `syncvet_e2e` cuando las migraciones no cambiaron y solo restaure los datos de la demo.

**Validación.** El usuario dio la entrega por validada con las correcciones 1 a 5 y los 11 desvíos aceptados (R-070). Evidencia:
Edge sin ventana, 10 de 10 de la implementación y 5 de 5 de las correcciones; Playwright del caso de la foto, en la implementación y
después de las correcciones.

Corrida de `npm test` del frontend (2026-11-14): 746 tests en 39 archivos, en verde.

### Entrega 1.5

Estado: **COMPLETADO** el 2026-11-16 (R-071). Especies y razas: listado, alta, cambio de nombre, activar o inactivar y baja, en
`feature/front-1-5-especies` (desde `feature/front-1-4-foto` en `28c5eb5`), sin integrar a `main` (se integra al cerrar la F1).
Commits: `8215039` y `dd15e51` (WIP de la implementación), `26772d1` (WIP de las correcciones de la revisión) y el de este cierre
(R-071: la documentación).

**Implementación.**

* **Frontend**: `/especies` (`species.index`, los tres roles) y "Especies" en el menú, entre "Pacientes" y "Configuración"
  (`features/species/SpeciesPage.tsx`): las especies con sus razas en una lista que se despliega (`aria-expanded`), cada una con
  "Común" o "Propia", "Inactivada" y la cantidad de razas. Por defecto, solo las activas (`speciesQueryOptions`, la consulta de
  los selectores del paciente); con `species.update` (administrador y veterinario), "Incluir las inactivas" (`?inactivas=1`,
  `GET species?include_inactive=1`; corrección H1 de la revisión); el recepcionista, sin la casilla y sin acciones. Solo en las
  propias, cada acción con su permiso: "Renombrar" (diálogo con un campo), "Inactivar" o "Activar" (sin confirmación) y "Eliminar" (`ConfirmDialog`;
  con `SPECIES_IN_USE` o `BREED_IN_USE`, solo el mensaje de la API (sin la pregunta) y "Inactivar …", que la inactiva desde el mismo diálogo; ya inactivada o sin
  `species.update`, solo "Cerrar"). "Nueva especie" y "Nueva raza" (también en una especie común) con `species.store`. El nombre,
  con Zod y los mensajes de la API (`features/species/name.ts`: obligatorio, de 2 a 60 u 80 caracteres en `Array.from`, sin los
  espacios de los extremos, al menos una letra y los caracteres de `CatalogMessages::NAME_REGEX`); el repetido, 422 en `name` al
  campo. `CATALOG_GLOBAL_NOT_EDITABLE`, en el diálogo (no `/sin-permiso`); 404, aviso, el diálogo cerrado y el catálogo de nuevo.
  Cada cambio (`afterCatalogChanged`, `features/species/api.ts`) vuelve a pedir el catálogo a la vista, **vacía** (`resetQueries`)
  las listas de especies que no están a la vista (la de los selectores del paciente; desvío 8) e invalida `['patients']` (sin
  pedir hasta mostrarse). Doble envío: el botón en
  espera y una `ref`. `PERMISSIONS` suma `speciesIndex`, `speciesStore`, `speciesUpdate` y `speciesDestroy`.
* **Pruebas**: Vitest, `name.test.ts` (21), `SpeciesPage.test.tsx` (21: los 18 de la primera sesión, más renombrar una especie,
  `BREED_IN_USE` en una raza activa con "Inactivar" por `species/1/breeds/7` y la lista de los selectores del paciente vaciada) y
  el menú de los tres roles con "Especies" (`permissions.test.tsx`). Mutaciones, primera sesión: 4 de 4 detectadas (las acciones
  en las comunes, 2 tests; el recepcionista pidiendo las inactivas, 1; sin "Inactivar" con `*_IN_USE`, 1; el nombre sin
  recortar, 1); segunda sesión (2026-11-16), 4 de 4: invalidar solo la lista con inactivas (1 test), la raza por la ruta de la
  especie (2), "Inactivar" desde el diálogo con `is_active: true` (2) e invalidar en lugar de vaciar las listas que no están a la
  vista (1). Playwright: `e2e/especies.spec.ts` (nuevo), con dos casos: la raza propia en "Canino" usada por un paciente
  (`BREED_IN_USE` e "Inactivar") y el recepcionista; y una especie propia con una raza: alta, cambio de nombre, inactivación y
  baja (se lleva la raza).

| # | Desvío | Motivo |
|---|---|---|
| 1 | La marca de lo inactivo dice "Inactivada", no "Inactiva" | El test de voseo toma "Inactiva" por el imperativo de tú de "inactivar" |
| 2 | Activar o inactivar no pide confirmación (se deshace con el mismo botón); el error va en un aviso | No borra nada y la API lo permite aunque esté en uso |
| 3 | Con `*_IN_USE`, el botón del diálogo pasa a "Inactivar …" (azul) en lugar de sumar uno, como `PATIENT_IN_USE` en la 1.3b | `ConfirmDialog` tiene dos botones |
| 4 | El caso de Playwright usa "Canino" (la especie común del `SpeciesSeeder`), no "Perro" como decía el diseño | Es el nombre real del catálogo común |
| 5 | La raza creada por Playwright queda inactivada en `syncvet_e2e` (no se puede borrar: la usó un paciente, también eliminado) | La base `syncvet_e2e` se recrea en cada corrida; en la demo (`syncvet.test`) no se usó ninguna raza nueva en un paciente |
| 6 | `useSingleAction` se repite en `features/species/CatalogDialogs.tsx` (es de pacientes, `use-patient-action.ts`) | Moverla a `src/lib/hooks` toca la 1.3b y la 1.4: queda para una entrega de limpieza si se acepta |
| 7 | "Nueva raza" se ofrece también en una especie inactivada | La API lo acepta (`StoreBreedRequest` y `BreedService` no lo controlan); el diseño no lo limita |
| 8 | Las listas de especies que no están a la vista se vacían (`resetQueries`), no se invalidan como decía el diseño ("invalidan `['species']`") | Hallado en Edge (2026-11-16): invalidada, el alta de un paciente mostraba la lista vieja, con la especie recién inactivada, mientras la volvía a pedir (`useSuspenseQuery` con datos en la caché). Es la convención de "Caché después de borrar o mover un registro hijo" de `frontend/CLAUDE.md` |
| 9 | La validación en Edge de la segunda sesión usó 6 controles (5 y la repetición del 3 después de corregirlo), no 5 | La repetición comprueba la corrección del desvío 8 |
| 10 | "Dejar la demo como estaba": las especies y razas creadas se borraron desde la interfaz, que es una baja lógica (`SoftDeletes`, con `//deleted_<id>_<timestamp>` en el nombre); las filas quedan en la base | Es la única baja que da la API; el `GET species?include_inactive=1` es igual antes y después |
| 11 | La demo (`syncvet.test`, Clínica A) tiene 17 especies propias inactivadas "Hurón doméstico NNNNNN", con una raza cada una, que no creó esta entrega (estaban antes de la validación) | Informativo: parecen restos de corridas de pruebas manuales o de Newman contra la demo; no se tocaron. Ocupan la mayor parte de la lista del administrador |

**Decisiones de la revisión (2026-11-16).** El usuario acepta los desvíos 1 a 10. Desvío 11: el listado de especies muestra solo
las activas por defecto, con "Incluir las inactivas" en la URL, como los demás listados (hallazgo a corregir; la API lo permite:
`GET species` sin `include_inactive` trae las activas con sus razas activas). Los restos "Hurón doméstico NNNNNN" son de la
colección de Postman de especies, que crea "Hurón {{run}}" y no lo borra: la limpieza va a la entrega `chore` de después de la F1
([handoff](../../handoff.md#próximas-tareas), tarea 3c).

**Cifras** (2026-11-14). Corrida completa del frontend: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test` falló en 5
tests de `src/lib/auth/permissions.test.ts`, un segundo test del menú que no se había actualizado con "Especies" (el de
`src/app/permissions.test.tsx` sí); corregido (y sumado el caso sin `species.index`), se repitieron solo `npm test` y el `lint` y el
`typecheck` de ese archivo, con la regla nueva del presupuesto: Vitest, 786 tests en 41 archivos, en verde (antes 746 en 39: 40
nuevos). Playwright, solo el caso nuevo `e2e/especies.spec.ts`: 1 de 1 (39,2 s; 1,5 min con la preparación), con `:8010` y `:5174`
libres después.

**Validación en el navegador** (2026-11-14): Edge sin ventana contra `syncvet.test` (Vite propio en `:5181`, detenido por su PID,
16260), solo lo que el caso de Playwright no ve, **6 de 6**: (1) como `vet-a`, "Especies" en el menú y Canino desplegado con sus 14
razas comunes, sin botones; (2) a 768 px de ancho, sin desplazamiento horizontal; (3) "12" frenado en el navegador y "  CANINO "
rechazado por la API (422, "Ya existe una especie con ese nombre." en el campo: sin distinguir mayúsculas ni espacios); (4) una
especie propia: alta, cambio de nombre, inactivación ("Inactivada") y baja; (5) `recepcion-a`, sin ningún botón de acción; (6) el
catálogo como estaba (el `GET species?include_inactive=1` igual antes y después). La consola: solo el 422 del control 3, provocado
por él. Capturas en `%TEMP%\claude\…\scratchpad\capturas-1-5`: `01-especies-canino-desplegado.png`, `02-tablet.png`,
`03-nombre-repetido.png`, `04-especie-propia-inactivada.png`, `05-dialogo-eliminar.png` y `06-recepcionista.png`. **Datos de la
demo:** la especie "Valida Especie" se creó, se renombró, se inactivó y se borró desde la interfaz; ninguna raza ni paciente nuevo.

**Segunda sesión (2026-11-16).** Lo que el pedido nombraba y faltaba: el caso de Playwright de la especie propia con una raza, tres
tests de Vitest (ver "Pruebas") y, en Edge, el selector del paciente con una especie inactivada, que encontró el desvío 8 (corregido
en `afterCatalogChanged`). **Cifras:** corrida completa del frontend, `typecheck`, `lint` y `build` sin errores ni avisos; Vitest,
789 tests en 41 archivos, en verde (3 nuevos). Playwright, solo `e2e/especies.spec.ts`: 2 de 2 (2,0 min con la preparación), con
`:8010` y `:5174` libres después. **Edge** sin ventana contra `syncvet.test` (Vite propio en `:5182`, detenido por su PID, 31864,
con el puerto libre comprobado), como `admin-a`: (1) una especie propia con una raza junto a las comunes: Canino "Común" sin
botones, la propia "Propia" con "Renombrar", "Inactivar" y "Eliminar"; (2) sin recargar, el alta de un paciente (Laura Martínez)
ofrece la especie propia y su raza; (3) inactivada, el alta todavía la ofrecía (**falla**, desvío 8); corregido y repetido: ya no
la ofrece; (4) a 768 px, sin desplazamiento horizontal; (5) la baja desde la interfaz y el `GET species?include_inactive=1` igual
antes y después (la primera vez el script contó la especie antes de que llegara la lista nueva; la repetición del control 3 lo
espera y da igual). Consola, sin errores. Ningún paciente guardado. Capturas en el scratchpad de la sesión,
`%TEMP%\claude\…\scratchpad\capturas-1-5`: `01-comunes-y-propias.png` (tomada antes de que llegara la lista con la raza: dice
"0 razas"), `02-propia-inactivada.png`, `03-tablet.png` y `04-dialogo-eliminar.png`.

**Revisión (2026-11-16).** Hallazgos: H1 (media), el listado mostraba siempre las inactivas a quien administra (el desvío 11,
que el usuario pidió corregir); H2 (baja-media), un 404 al tocar "Inactivar" o "Activar" y en los diálogos invalidaba
`['species']` en lugar de llamar a `afterCatalogChanged` (la lista de los selectores del paciente quedaba vieja, no vaciada), y
el del botón no tenía test (la mutación que lo quitaba sobrevivía); H3 (baja), con `*_IN_USE` el diálogo seguía diciendo
"¿Querés eliminar…? No se puede deshacer." debajo de "Inactivar …"; detalle 1, un comentario de `src/app/menu.ts` de unos 170
caracteres; detalle 2 (aceptado como informativo), el detalle de un cliente muestra un instante el nombre viejo de la especie
después de renombrarla. Mutaciones de la revisión: 2 de 3 detectadas (la del 404 del botón sobrevivió).

**Correcciones de la revisión (2026-11-16):**

* **H1**: `?inactivas=1` en la URL (`features/species/search.ts`, `validateSpeciesListSearch`, como `fallecidos` en
  `/pacientes`; la clave va siempre) y la casilla "Incluir las inactivas" arriba de la lista, solo con `species.update`; sin ese
  permiso, el parámetro se ignora. Sin el filtro, `/especies` usa `speciesQueryOptions()` (la misma consulta y la misma clave que
  los selectores del paciente, con su `staleTime` de 5 minutos); con él, `GET species?include_inactive=1`
  (`catalogQueryOptions`, `features/species/api.ts`). La casilla cambia la URL con historial y no repliega la lista.
* **H2**: el 404 del botón y el de los diálogos (`useCatalogError`) llaman a `afterCatalogChanged`.
* **H3**: con `*_IN_USE`, el diálogo deja solo el mensaje de la API (sin la pregunta ni "No se puede deshacer").
* **Detalle 1**: el comentario de `menu.ts`, partido.

| # | Desvío de las correcciones | Motivo |
|---|---|---|
| 12 | El pedido decía "como hizo la 1.3b con `PATIENT_IN_USE`", pero la 1.3b **no** cambia el texto: su diálogo sigue diciendo "¿Querés eliminar a …? No se puede deshacer." con `PATIENT_IN_USE` (`features/patients/PatientActions.tsx`). En la 1.5 se hizo lo pedido (solo el mensaje de la API); la 1.3b queda igual | Corregir la 1.3b es otra entrega: el usuario la sumó a la `chore` de después de la F1 ([handoff](../../handoff.md#próximas-tareas), tarea 3c) |
| 13 | Sin el filtro, el listado puede tener hasta 5 minutos de antigüedad respecto de los cambios hechos en otra PC (el `staleTime` de `speciesQueryOptions`); los cambios propios lo actualizan al instante (`afterCatalogChanged`) | Es lo pedido (usar `speciesQueryOptions`) y lo mismo que ven los selectores del paciente |
| 14 | Playwright: se ajustó también el caso 1 (la raza inactivada deja de verse sin la casilla; con ella, "Inactivada"; y el diálogo sin "No se puede deshacer"), no solo el 2 | Sin el ajuste, el caso 1 fallaba con el listado nuevo |

**Pruebas de las correcciones.** Vitest: `search.test.ts` (nuevo, 10), y en `SpeciesPage.test.tsx` (22) el recepcionista con
`?inactivas=1` (sin casilla, sin pedir las inactivas), el administrador y el veterinario (solo las activas, la casilla con
`?inactivas=1` y de vuelta sin el parámetro), el 404 al inactivar (aviso, el catálogo de nuevo y la lista de los selectores
vaciada) y el texto del diálogo antes y después de `SPECIES_IN_USE`; los tests que usan la especie inactivada entran con
`?inactivas=1`. Mutaciones, 3 de 3 detectadas: quitar `afterCatalogChanged` del 404 del botón (la mutación 3 de la revisión, 1
test), dejar la pregunta con `*_IN_USE` (1) y pedir las inactivas sin `species.update` (1).

**Cifras de las correcciones** (2026-11-16). Corrida completa del frontend: `typecheck` y `build` sin errores; `lint`, 2 errores
de estilo en el test nuevo (`as HTMLInputElement`; `non-nullable-type-assertion-style`), corregidos con un ayudante, y se
repitieron solo el `lint` y el `typecheck` de ese archivo y los tests de `features/species`, en verde (53). Vitest, 800 tests en 42
archivos, en verde (antes 789 en 41). Playwright, solo `e2e/especies.spec.ts`: el caso 2 pasó; el 1 falló por un detalle del caso
(volvía a tocar "Canino", que seguía desplegado, y lo plegaba); corregido, se repitió solo ese caso, que pasó (2,0 min). `:8010` y
`:5174` libres después.

**Edge de las correcciones** (2026-11-16): sin ventana contra `syncvet.test` (Vite propio en `:5183`, detenido por su PID, 28080,
con el puerto libre comprobado), como `admin-a`, **3 de 3**: (1) sin la casilla, 9 especies, ninguna "Hurón doméstico" ni
"Inactivada" y la URL sin parámetros; (2) con la casilla, `?inactivas=1`, 26 especies, las 17 "Hurón doméstico" con "Inactivada";
(3) "Eliminar" en la raza "Albino" (inactivada y en uso) de "Hurón doméstico 460603": antes, la pregunta; el `DELETE`, 409; después,
solo "La raza está asignada a pacientes y no puede eliminarse. Podés inactivarla." y "Cerrar" (sin "Inactivar": ya está
inactivada; el botón "Inactivar …" lo cubre el caso 1 de Playwright). La consola, solo el 409 del control 3, provocado por él.
**Demo como estaba**: el `GET species?include_inactive=1` igual antes y después; no se creó ni se borró nada. Capturas en
`%TEMP%\claude\…\scratchpad\capturas-1-5-correcciones`: `01-solo-activas.png`, `02-con-inactivas.png` y `03-raza-en-uso.png`.

**Tareas para después de la F1** (pedido del usuario en la revisión): en la entrega `chore` ([handoff](../../handoff.md#próximas-tareas),
tarea 3c), que la colección de Postman de especies borre lo que crea, limpiar los restos "Hurón doméstico NNNNNN" de la base de
desarrollo y quitar del diálogo de baja de un paciente (1.3b) la pregunta con `PATIENT_IN_USE` (desvío 12). El desvío 6 (mover
`useSingleAction` a `src/lib/hooks`) también queda para una entrega de limpieza.

**Validación.** El usuario dio la entrega por validada con las correcciones H1, H2, H3 y el detalle 1, los desvíos 1 a 10 y 12 a
14 aceptados y el 11 corregido como H1 (R-071). Evidencia: Edge sin ventana, 6 de 6 de la implementación (primera sesión), 6
controles de la segunda (con la repetición del 3) y 3 de 3 de las correcciones; Playwright de `e2e/especies.spec.ts`, 2 de 2
después de las correcciones.

Corrida de `npm test` del frontend (2026-11-16): 800 tests en 42 archivos, en verde.

### Cierre de la F1

**COMPLETADA el 2026-11-16 (R-072)**, con la entrega de soporte del backend (front-01). Las ramas se encadenaron
(`docs/front-f1-diseno` → `feature/front-01-dueno-pacientes` → `feature/front-1-1-clientes` → `feature/front-1-2-clientes-abm` →
`feature/front-1-3a-pacientes` → `feature/front-1-3b-pacientes` → `feature/front-1-4-foto` → `feature/front-1-5-especies`), todas en
GitHub, y se integran a `main` con un merge `--no-ff` desde `feature/front-1-5-especies`, autorizado por el usuario (R-072). Las
ramas se conservan.

**Qué se construyó.** Las primeras pantallas de negocio, sobre la API de la FASE 1 del backend y con las convenciones de la F0:
`/clientes` (listado con la búsqueda, el tipo y la página en la URL; detalle con sus pacientes; alta, edición y baja de persona y
empresa, con el dígito verificador del RUC en el navegador y el cambio de tipo), `/pacientes` (listado con el dueño, la especie y los
fallecidos en la URL; detalle; alta desde el cliente y edición; fallecimiento con su hora, corregirlo y anularlo; cambio de dueño y
baja), la foto del paciente (verla como `Blob`, subirla con vista previa, reemplazarla y quitarla) y `/especies` (las especies con
sus razas, "Común" o "Propia"; alta, cambio de nombre, activar o inactivar y baja de las propias). Componentes nuevos de
`src/components/ui`: `SearchField`, `ConfirmDialog`, `SelectField`, `TextareaField`, `CheckboxField`, `RadioGroup`, `DateField`,
`TimeField`, `FileField` y `BlobImage`; en el cliente de la API, `api.getBlob`. Sin paquetes nuevos. Del lado del backend: el dueño
en `GET patients` (front-01) y dos correcciones en commits aparte, el choque con un índice único de `clients` (1.2) y el microchip
repetido en dos pedidos simultáneos (deuda 22, 1.3a), los dos como 422 en su campo y nunca 500. En la cadena, contra `main`: 102
archivos de `frontend/` (31 de tests de Vitest y de Playwright) y 10 de `backend/` (`PatientResource`, `ClientService`,
`PatientService`, `backend/CLAUDE.md` y 6 de tests). Rutas, permisos y migraciones de la API sin cambios: 78, 135 y 36.

| Entrega | Contenido | Commits | Al cerrar |
|---|---|---|---|
| Diseño | Análisis y diseño de la F1 y de front-01 | `125b023`, `fe69aad` (R-064) | — |
| [front-01](#entrega-front-01) | El dueño en `GET patients` | `3e3e386` (cierre `6ee193a`) | Backend: 1451 casos; 14225 aserciones en SQLite y 14299 en MySQL |
| [1.1](#entrega-11) | Clientes: listado y detalle | `3ea03b9`, `104aee1` (cierre `78e9657`) | Frontend: 357 tests en 25 archivos |
| [1.2](#entrega-12) | Clientes: alta, edición y baja | `657100e`, `594ad20` (backend), `bb8cab5` (cierre `d36c37b`) | Frontend: 502 tests en 29 archivos; backend: 1458 casos (14262 y 14342 aserciones) |
| [1.3a](#entrega-13a) | Pacientes: listado, detalle, alta y edición | `f12f3c7` (backend, deuda 22), `c68511c`, `150216e` (cierre `5f3aac7`) | Frontend: 642 tests en 35 archivos; backend: 1463 casos (14274 y 14360 aserciones) |
| [1.3b](#entrega-13b) | Pacientes: fallecimiento, cambio de dueño y baja | `9bf37ba`, `c8ca294` (el hook de `cat`) (cierre `e20186e`) | Frontend: 694 tests en 37 archivos |
| [1.4](#entrega-14) | Foto del paciente | `dde595a` (cierre `28c5eb5`) | Frontend: 746 tests en 39 archivos |
| [1.5](#entrega-15) | Especies y razas | `8215039`, `dd15e51`, `26772d1` (cierre `7ef0a9e`) | Frontend: 800 tests en 42 archivos |

**Regresión del cierre** (2026-11-16, en `feature/front-1-5-especies` sobre `7ef0a9e`, más los ajustes de Playwright de este
cierre):

* **Backend**, desde `backend/`, en serie: `composer test` (SQLite), 1463 casos, 1447 en verde y 16 omitidos (solo de MySQL), 14274
  aserciones (6 min 37 s, con los controles del frontend corriendo a la vez); después `composer test:mysql` (`syncvet_test_mysql`),
  1463 casos en verde, sin omitidos, 14360 aserciones (4 min 31 s). Son las cifras de la 1.3a: el backend no cambió después. Frente
  a `main` antes de la F1 (cierre de la F0: 1445 casos; 14183 y 14257 aserciones), 18 casos más (front-01 y las dos correcciones).
  `composer audit`: "No security vulnerability advisories found.".
* **Frontend**, desde `frontend/`: `npm run typecheck`, `npm run lint` y `npm run build`, sin errores ni avisos; `npm test`, 800 de
  800 en 42 archivos (60 s). Una primera corrida, con la suite del backend en paralelo, dio 799: el test de voseo
  (`src/test/voseo.test.ts`, "los textos de src/ usan voseo") pasó el límite de 5 s (5,7 s; solo, tarda 0,5 s): deuda 24.
  `npm run test:e2e` (Playwright, Edge, contra `syncvet_e2e`): la primera corrida, 18 de 22 (desvíos 1 y 2); con los tests
  ajustados, 22 de 22 en 4,1 min. `npm audit`: 9 avisos moderados, todos el mismo, `minimatch` 10.2.6 (GHSA-g3hv-75wg-m249;
  [deuda 19](../../deuda-tecnica.md); npm lo cuenta ahora en 9 paquetes, no en 10).

| # | Desvío | Motivo |
|---|---|---|
| 1 | `e2e/permisos.spec.ts`: los tres casos del menú esperaban el menú sin "Especies"; se suma | La 1.5 agregó "Especies" al menú y, con el presupuesto por entrega, solo corrió `e2e/especies.spec.ts`: el caso de la F0 quedó viejo (Vitest ya lo tenía en `permissions.test.tsx`) |
| 2 | `e2e/pacientes-estado.spec.ts` falló en la corrida completa y pasó solo: escribía el documento en "Buscar clientes" y hacía clic en "Laura Martínez" sin esperar la URL. Laura ya está en el listado sin filtrar; si la búsqueda (300 ms después) cambia la URL antes de que se dibuje el detalle, la página vuelve al listado. Los cuatro lugares con ese patrón (`pacientes-estado`, `pacientes`, `pacientes-foto` y `especies`) esperan ahora `?buscar=…` antes del clic, como `clientes.spec.ts` | Solo los tests; lo que eso muestra de la aplicación queda como deuda 23 |

**Colecciones de Postman** (R-057: todas las de `docs/05-pruebas/postman`). Newman 6.2.3, desde la raíz, con el servidor levantado
desde `backend/` en el puerto 8001 contra la base de desarrollo (detenido por su tarea, con el puerto libre después); una vuelta,
sin fallas: 483 pedidos y 1074 aserciones (dos más que en la F0: la aserción del dueño de front-01 en pacientes).

| Colección | Pedidos | Aserciones | Fallas |
|---|---|---|---|
| Clientes | 24 | 48 | 0 |
| Especies | 41 | 93 | 0 |
| Foto | 23 | 60 | 0 |
| Pacientes | 30 | 63 | 0 |
| Auditoría | 16 | 47 | 0 |
| Configuración | 24 | 67 | 0 |
| Servicios | 27 | 64 | 0 |
| Sucursales | 44 | 123 | 0 |
| Usuarios | 54 | 136 | 0 |
| Veterinarios | 32 | 75 | 0 |
| Agendas | 35 | 68 | 0 |
| Disponibilidad | 50 | 81 | 0 |
| Series | 25 | 42 | 0 |
| Turnos | 58 | 107 | 0 |

**Criterio de cierre**, punto por punto:

| Criterio | Evidencia |
|---|---|
| Las entregas aprobadas cerradas | front-01, 1.1, 1.2, 1.3a, 1.3b, 1.4 y 1.5: R-065 a R-071, cada una con su revisión, sus correcciones y la validación del usuario ([Resultado](#resultado)) |
| Vitest en verde | `npm test`, 800 de 800 en 42 archivos (corrida de este cierre) |
| Playwright en verde | `npm run test:e2e`, 22 de 22, con los desvíos 1 y 2 de este cierre (solo en los tests) |
| `typecheck`, `lint` y `build` sin errores ni avisos | Sin errores ni avisos (corridas de este cierre; `lint` y `typecheck`, repetidos sobre los tests de Playwright tocados) |
| El desvío 8 de la 0.1 verificado contra la API real | VERIFICADO en la 1.3b (R-069; [resultado](#entrega-13b)): `deceased_at` sale como `…T10:30:00.000-03:00`, la API devuelve `13:30:00Z` y el detalle muestra 10:30 después de recargar (`e2e/pacientes-estado.spec.ts`, en verde en este cierre) |
| Newman de todas las colecciones, sin fallas | Las 14: 483 pedidos y 1074 aserciones, sin fallas (tabla de arriba) |

**Deudas** ([`deuda-tecnica.md`](../../deuda-tecnica.md)). Abiertas en la F1: 21 (`countQueries` copiado en tres tests del
backend; front-01), 23 (un clic en el listado durante la espera de la búsqueda puede volver al listado; este cierre) y 24 (el límite
de 5 s del test de voseo; este cierre), las tres de prioridad baja. Resuelta en la F1: 22 (el microchip repetido en dos pedidos
simultáneos, 1.3a). Actualizada: 19 (npm cuenta el aviso de `minimatch` en 9 paquetes). Abiertas al cerrar: del backend, 2, 3, 5, 6,
15, 17 y 21; del frontend, 19, 20, 23 y 24.

**Decisiones de la fase.** R-064 (diseño de la F1 y de front-01, con las 13 preguntas respondidas con la recomendación), R-065
(cierre de front-01), R-066 (cierre de la 1.1: la búsqueda en la URL), R-067 (cierre de la 1.2: `ConfirmDialog`, un solo envío por
formulario y el índice único de `clients` como 422), R-068 (cierre de la 1.3a: la deuda 22 y el presupuesto por entrega), R-069
(cierre de la 1.3b: el desvío 8 de la 0.1 verificado y el hook que bloquea `cat` sin entrada), R-070 (cierre de la 1.4: `api.getBlob`,
`BlobImage` y el tipo de la foto por el contenido), R-071 (cierre de la 1.5) y R-072 (este cierre y el merge), en el
[registro](../../../01-arquitectura/decisiones/registro.md).

**Pendiente para la F2** (datos maestros, sobre la API de la FASE 2 del backend: sucursales y depósitos, usuarios, veterinarios,
servicios, la configuración de la clínica con el logo y la consulta de la auditoría): antes, la entrega `chore/…` de las tareas 3b
y 3c del [handoff](../../handoff.md#próximas-tareas) (Playwright que reutiliza `syncvet_e2e`; la colección de especies que borra lo
que crea, la limpieza de "Hurón doméstico NNNNNN", la pregunta del diálogo de baja con `PATIENT_IN_USE` y `useSingleAction` en
`src/lib/hooks`); después, el análisis y el diseño de la F2, con sus preguntas, antes de cualquier código. Lo que la F1 deja
preparado: el modelo de listado (`/clientes`: búsqueda, filtros y página en la URL, `useSearchText`), el de formulario (`ClientForm`:
las reglas de forma con los mensajes de la API, un solo envío y el 422 por campo), `ConfirmDialog` con el trato de `*_IN_USE`
(inactivar desde el diálogo), la subida de un archivo con vista previa (para el logo de la clínica) y las descargas con token
(`api.getBlob`). `/configuracion` sigue provisoria (solo el administrador) hasta la F2.

Corrida de `npm test` del frontend (2026-11-16): 800 tests en 42 archivos, en verde.
