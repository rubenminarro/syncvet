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
| 1.1 | Clientes: listado (búsqueda, tipo y paginación en la URL) y detalle (datos y pacientes) | No | `feature/front-1-1-clientes` (desvío 1) | IMPLEMENTADA, sin cerrar ([pendiente](#pendiente-para-el-cierre-11)) |
| 1.2 | Clientes: alta, edición (persona y empresa, el cambio de tipo) y baja | No | `feature/front-1-2-clientes-abm` | APROBADA (R-064) |
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

Implementada el 2026-11-13 en `feature/front-1-1-clientes` (desde `6ee193a`), commit WIP. Falta la revisión y el cierre
(`/cerrar-entrega`: el resultado, la línea de la corrida de `npm test` en "Resultado", el roadmap, `modulos.md`, el handoff y la
cifra de tests de `frontend/CLAUDE.md` §1).

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
| 2 | `SelectField` se adelanta de la 1.2 (el filtro de tipo), solo con la etiqueta y las opciones; la 1.2 le suma el error | Las páginas no repiten el marcado de Tabler (`frontend/CLAUDE.md` §5) | Para revisar |
| 3 | El cliente inexistente o de otra clínica (y un id inválido) muestra "No encontramos este cliente" **dentro del layout** (`notFoundComponent` de la ruta, con "Volver a los clientes"), no la página de no encontrado de la raíz | La de la raíz es de página completa, sin la barra ni el menú; el texto no dice si el cliente es de otra clínica | Para revisar |
| 4 | `validateSearch` devuelve siempre las tres claves (`undefined` si el valor se descarta) | Hallazgo: TanStack Router mezcla lo que devuelve sobre la búsqueda cruda de la URL; sin la clave, `?pagina=-4` llegaba a la API como `page=-4`. Con `buscar` en la URL no se veía (por eso la mutación 4 sobrevivió al primer test); el test prueba las dos URL | Para revisar |
| 5 | ESLint: `only-throw-error` permite también `NotFoundError` de `@tanstack/router-core` (ya permitía `Redirect`) | `throw notFound()` en el `loader` del detalle | Para revisar |
| 6 | El `loader` usa `queryClient.query({ ...opciones, staleTime: 'static' })`, no `ensureQueryData` | `ensureQueryData` está deprecado en TanStack Query 5.107 (lo marca ESLint); es el reemplazo que indica la biblioteca | Informativo |
| 7 | El error del listado se muestra dentro de la tarjeta ("No se pudieron cargar los clientes", "Probá de nuevo"), y el 403, con la página de sin permiso en la tarjeta; el del detalle, con la página de error del router ("Reintentar") | El listado se pide en el componente (con `keepPreviousData`, para que la tabla no parpadee); el detalle, en el `loader` | Para revisar |
| 8 | Detalle: "RUC" en lugar de "Documento" para una empresa; un dato vacío se muestra con "—"; cada paciente lleva su código; el nombre del paciente todavía no es un enlace (su detalle llega en la 1.3a) | Detalles de presentación no definidos en el diseño | Para revisar |
| 9 | La primera corrida completa de `npm test` dio 3 fallas por tiempo: `/ingresar` y `/perfil` (5 s) y "lee la búsqueda…" del listado (el segundo de `findByText`), con la PC cargada justo después de `lint` y `build`. Después de corregir el lint, la segunda corrida completa quedó en verde, y 5 vueltas de esos archivos no fallaron | Carga de la PC; no se reproduce | Informativo |
| 10 | La validación en el navegador simula el fallecido en la respuesta de la API (prueba 5) | La demo no tiene pacientes fallecidos y el pedido era no cambiar sus datos | Informativo |
| 11 | El paso 0 del pedido: deuda 21 (backend, baja), `countQueries` copiado en tres tests, en `deuda-tecnica.md` y `backend/CLAUDE.md` §7 | Pedido del usuario | Informativo |

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

### 1.3a Pacientes: listado, detalle, alta y edición

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

