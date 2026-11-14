# F1 — Clientes y pacientes (frontend)

Etapa: Datos de la clínica. Estado: **APROBADO** por el usuario el 2026-11-12 (R-064), en `docs/front-f1-diseno` (desde `main` en `668eb48`), con las
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
| 1.3a | Pacientes: listado, detalle, alta (desde el cliente) y edición | front-01 | `feature/front-1-3a-pacientes` | APROBADA (R-064) |
| 1.3b | Pacientes: fallecimiento (y anularlo), cambio de dueño y baja; la verificación del desvío 8 | No | `feature/front-1-3b-pacientes-estado` | APROBADA (R-064) |
| 1.4 | Foto del paciente: ver (como `Blob`), subir y borrar | No | `feature/front-1-4-foto` | APROBADA (R-064) |
| 1.5 | Especies y razas: listado, alta, edición, inactivación y baja | No | `feature/front-1-5-especies` | APROBADA (R-064) |

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
raza" es `null`); sexo (Macho, Hembra, Sin dato; por defecto "Sin dato"); castrado; fecha de nacimiento (`DateField`, `Y-m-d`) y
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

Estado al 2026-11-14 (commit WIP): implementación hecha, con sus tests y la validación en el navegador; falta la revisión y el cierre
(`/cerrar-entrega`: el resultado, `frontend/CLAUDE.md`, el roadmap, `modulos.md` y el handoff).

**Hecho.**

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
| 4 | Al editar un paciente con una especie inactiva se ofrece solo su raza actual (con "(inactivada)"), no las demás razas activas de esa especie | `GET species` sin `include_inactive` no trae la especie inactiva ni sus razas (la clave del diseño es `['species', { include_inactive: false }]`). Para ofrecerlas habría que pedir `include_inactive=1` en la edición: **decisión para la revisión** |
| 5 | El alta guarda y va al detalle del **paciente** nuevo (no al del cliente); el detalle del cliente muestra el paciente porque el alta invalida `['clients']` | "Guardar y volver: al detalle", como el alta de clientes; el caso de Playwright entra al cliente desde el enlace del dueño y ve la tarjeta |
| 6 | El formulario llama "Sin dato" al sexo `unknown` (el diseño); el listado y el detalle muestran la etiqueta de la API, "Sin determinar" | El formulario usa sus propias etiquetas; las vistas muestran el `label` de la API sin reescribirlo. **Para la revisión:** unificar (el formulario con "Sin determinar") |
| 7 | `/pacientes` no tiene "Nuevo paciente": el alta es solo desde el cliente (con un texto que lo dice debajo del título) | Pregunta 7 del diseño: el dueño sale de la URL |
| 8 | El backend: en `PatientUniqueIndexTest` el duplicado que arma el oyente entra en la transacción del alta y se revierte con ella (se verifica que no quede ningún paciente, no "solo el primero" como en clientes); "otra violación sigue su camino" se prueba en el servicio (una edición que repite el código), porque por la API ninguna otra violación llega a esa regla | Sin ese test, la mutación "toda violación es del microchip" sobrevivía |
| 9 | La primera corrida de la validación en el navegador dio 21 de 26: las 5 fallas eran del script (leía la tabla antes de que llegara la respuesta nueva, que `keepPreviousData` reemplaza después; la tarjeta del dueño se actualiza en segundo plano; comparaba contra "Mestizo", que también es una raza de Felino). Con esperas, 26 de 26 | Sin cambios en la aplicación |
| 10 | El selector nativo de la fecha no se vio con ventana (Edge sin ventana): se comprobó `type="date"`, el `max` con el día de Asunción y el rechazo de mañana sin `POST` | El diseño pedía mirar el selector nativo: queda para la revisión con ventana |
| 11 | La primera corrida completa de Playwright dio 14 de 18: los 3 de `permisos.spec.ts` (el menú esperado sin "Pacientes", corregido) y "alta de una empresa" de `clientes-abm.spec.ts`, cortado por el límite de 60 s esperando el campo Email con la PC cargada (8,5 min la corrida). Los dos archivos, solos: 10 de 10 | Resuelto: la corrida completa final, en verde (abajo) |

**Corrida completa final de Playwright** (2026-11-14): 18 de 18 en verde, en 4,4 min; los puertos `:8010` y `:5174` quedaron libres.

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
