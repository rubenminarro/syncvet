# Guía de pruebas con Postman

Requests por fase, encadenados. Todos llevan `Accept: application/json` y, salvo el login, `Authorization: Bearer {{token}}`.
Las respuestas siguen el contrato de `CLAUDE.md` §4.

## FASE 0 — Autenticación

Entregas 0.3 y 0.4. Variables de la colección: `{{base_url}}` (por ejemplo, `http://syncvet.test`), `{{demo_password}}` (el
valor de `DEMO_PASSWORD`), `{{token}}` y `{{token_b}}`.

> **Validación manual:** hecha por el usuario el 2026-11-05 con los datos del `DemoSeeder` (entrega 0.4, R-012), con este alcance:
> login con un usuario de cada clínica (`recepcion-a` y `vet-b`, como las filas 2b y 2c) y `me` con el de la A, un paciente de otra clínica →
> 404 (como la 16, con `GET /api/patients/{id}`), cinco fallidos y el sexto → 429 (como la 11 y la 12, con la sexta también
> incorrecta), logout (como la 4) y `me` después → 401 (como la 5). Las
> demás filas no se recorrieron a mano: las respaldan los tests de `tests/Feature/Auth`, `tests/Feature/Tenancy` y
> `tests/Feature/Seeders`.

**Datos.** Los crea el `DemoSeeder`, solo en `local` (y en los tests). En `.env`, definir `DEMO_PASSWORD` (al menos 12 caracteres,
con mayúsculas, minúsculas, números y símbolos): es la contraseña de todos los usuarios de demostración. Después:

```bash
php artisan migrate:fresh --seed
php artisan db:seed --class=DemoSeeder
```

Es idempotente: correrlo otra vez no duplica nada ni cambia las contraseñas. Sobre una base sembrada antes de R-011 (con los
documentos de ejemplo anteriores) falla: recrearla con los dos comandos. Para usar otra `DEMO_PASSWORD`, repetir los dos
comandos. Solo usa sus clínicas (marcadas como de demostración, con RUC ficticios): si ya hay otra clínica con su RUC, o uno de
sus emails es de un usuario de otra clínica, falla sin tocar nada (R-010).

| Clínica | RUC | Rol | Email | Además |
|---|---|---|---|---|
| Clínica A | 99999901-0 | `administrador` | `admin-a@example.com` | |
| Clínica A | | `recepcionista` | `recepcion-a@example.com` | |
| Clínica A | | `veterinario` | `vet-a@example.com` | Ficha `VET-000001`, matrícula `MV-1001` |
| Clínica B | 99999902-8 | `administrador` | `admin-b@example.com` | |
| Clínica B | | `recepcionista` | `recepcion-b@example.com` | |
| Clínica B | | `veterinario` | `vet-b@example.com` | Ficha `VET-000001`, matrícula `MV-2001` |

Cada clínica tiene su sucursal `SUC-01` y su depósito `DEP-01`. Clientes: en la A, Laura Martínez (`99100001`, con Firulais y
Mishi), Carlos Duarte (`99100002`, con Rocky) y Granja San Jorge S.R.L. (`99999903-6`, con la yegua Lucero); en la B, Patricia
Ortiz (`99200001`, con Luna) y Jorge Villalba (`99200002`, con Toby y Kira). Todos los documentos son ficticios (R-011): RUC
`999999xx` con su dígito verificador y cédulas `99xxxxxx`. Los pacientes de cada clínica numeran desde
`PAC-000001`.

**Sesión.**

| # | Request | Body | Esperado |
|---|---|---|---|
| 1 | `POST /api/login` | `{"email":"admin-a@example.com","password":"{{demo_password}}"}` | 200; `data.token_type` = `Bearer`; `data.expires_at` 12 horas después, en UTC (`Z`); `data.user.clinic.name` = "Clínica A". Guardar `data.token` en `{{token}}` |
| 2 | `GET /api/me` con `{{token}}` | | 200: usuario, `clinic`, `current_branch` (`SUC-01`), `roles` = `["administrador"]`, `permissions` ordenados (los 119), `veterinarian` = `null` |
| 2b | `POST /api/login` con `recepcion-a@example.com` y `GET /api/me` con su token | | 200: `roles` = `["recepcionista"]`, solo los permisos de ese rol, `veterinarian` = `null` |
| 2c | `POST /api/login` con `vet-a@example.com` y `GET /api/me` con su token | | 200: `roles` = `["veterinario"]`, `veterinarian.code` = `VET-000001` |
| 3 | `POST /api/login` otra vez (mismo usuario) | igual al 1 | 200 con otro token: las dos sesiones quedan vigentes. Guardarlo en `{{token_b}}` |
| 4 | `POST /api/logout` con `{{token}}` | | 200, `data` = `null` |
| 5 | `GET /api/me` con `{{token}}` | | 401 `UNAUTHENTICATED` (revocado) |
| 6 | `GET /api/me` con `{{token_b}}` | | 200: el logout revocó solo el token actual |
| 7 | `GET /api/me` sin `Authorization` | | 401 `UNAUTHENTICATED` |

**Credenciales e intentos de más.**

| # | Request | Body | Esperado |
|---|---|---|---|
| 8 | `POST /api/login` con contraseña incorrecta | `{"email":"admin-a@example.com","password":"otra"}` | 422 `INVALID_CREDENTIALS`, "El email o la contraseña no son correctos." |
| 9 | `POST /api/login` con un email que no existe | `{"email":"nadie@example.com","password":"otra"}` | 422 `INVALID_CREDENTIALS`, el mismo mensaje que el 8 |
| 9b | `POST /api/login` con el email en mayúsculas | `{"email":"ADMIN-A@Example.com","password":"{{demo_password}}"}` | 200: el email se guarda y se busca en minúsculas |
| 10 | `POST /api/login` sin `password` | `{"email":"admin-a@example.com"}` | 422 `VALIDATION_ERROR`, `errors.password` = "Ingresá tu contraseña." |
| 11 | El 8 cinco veces (el 9b dejó el contador en cero) | | 422 cada vez |
| 12 | `POST /api/login` con la contraseña correcta | igual al 1 | 429 `AUTH_TOO_MANY_ATTEMPTS`, "Demasiados intentos. Probá de nuevo en N segundos.", encabezado `Retry-After` |
| 12b | `POST /api/login` con una variante Unicode del email | `{"email":"ādmin-a@example.com","password":"{{demo_password}}"}` | 422 `VALIDATION_ERROR`, `errors.email` = "Ingresá un email sin acentos ni caracteres especiales." |
| 13 | Igual al 1 con `admin-b@example.com` | | 200: el bloqueo es por usuario e IP |
| 14 | Esperar un minuto y repetir el 1 | | 200; un login correcto deja el contador en cero |

**Separación entre clínicas** (pendiente de la entrega 0.2). Con `{{token}}` de la clínica A (repetir el 1) y `{{token_b}}` de la
clínica B (login con `admin-b@example.com`):

| # | Request | Body | Esperado |
|---|---|---|---|
| 15 | `POST /api/clients` con `{{token_b}}` | El cliente 1 de la FASE 1 | 201. Guardar `data.id` como `{{client_b}}` |
| 16 | `GET /api/clients/{{client_b}}` con `{{token}}` | | 404 `RESOURCE_NOT_FOUND`: un id de otra clínica no existe para A |
| 17 | `PATCH /api/clients/{{client_b}}` con `{{token}}` | `{"phone":"+595981999999"}` | 404 `RESOURCE_NOT_FOUND` |
| 18 | `GET /api/clients` con `{{token}}` | | 200 sin el cliente de B |
| 19 | `POST /api/clients` con `{{token}}` | El mismo cuerpo del 15 | 201: documento y email son únicos por clínica |
| 20 | En tinker: `App\Models\Clinic::where('name', 'Clínica B')->update(['status' => 'suspended'])`. Después, `GET /api/me` con `{{token_b}}` | | 403 `CLINIC_NOT_AVAILABLE` |
| 21 | `POST /api/login` con `admin-b@example.com` | | 403 `CLINIC_NOT_AVAILABLE`; no se crea el token. Queda `LOGIN_FAILED` con el motivo "clínica suspendida" y no cuenta como intento fallido |
| 22 | `POST /api/login` con `admin-b@example.com` y una contraseña incorrecta | | 422 `INVALID_CREDENTIALS`: sin la contraseña correcta no se revela la suspensión |
| 23 | `POST /api/logout` con `{{token_b}}` | | 200: con la clínica suspendida el usuario igual cierra su sesión |

## FASE 1 — Clientes y pacientes

Se valida con cuatro colecciones de Postman, que son la fuente de verdad de los pasos: cada request lleva su nombre (con el código HTTP
esperado), sus encabezados, su body y un test que verifica el código HTTP y el dato clave. Esta guía no repite sus pasos ni tiene
requests propios para la FASE 1: una tabla aparte quedaba desactualizada y, si se seguía a mano, creaba datos que hacían fallar las
colecciones (R-021).

| Colección | Entrega | Qué valida |
|---|---|---|
| [`postman/syncvet-fase-01-clientes.postman_collection.json`](postman/syncvet-fase-01-clientes.postman_collection.json) | 1.2 (R-018); repetible desde la 1.3 (R-020) | Alta de persona y de empresa, RUC con el dígito verificador equivocado, documento repetido como cédula, con un cero o como RUC, edición, cambio de tipo, email en minúsculas, búsqueda por mascota, aislamiento entre clínicas, filtros mal formados, baja con pacientes (409) y sin ellos, y el documento liberado |
| [`postman/syncvet-fase-01-pacientes.postman_collection.json`](postman/syncvet-fase-01-pacientes.postman_collection.json) | 1.3 (R-020) | Alta bajo un cliente (el dueño sale de la URL, sin `custom_fields`), `code` prohibido, raza de otra especie, microchip repetido, peso, dueño en la edición, cambio de dueño (también de un fallecido y el 403 del veterinario), búsquedas, listado del cliente, fallecimiento en UTC, edición de un fallecido, listados sin y con fallecidos, baja y microchip liberado |
| [`postman/syncvet-fase-01-especies.postman_collection.json`](postman/syncvet-fase-01-especies.postman_collection.json) | 1.4 (R-023 y R-024) | Listado con y sin inactivas (forma de la respuesta e `is_global`), alta de especie propia, nombre repetido en mayúsculas y sin acento o de una global, nombre sin letras (422), alta inactiva, cambio de nombre, global no editable (403, también con un cuerpo inválido), razas propias en una especie propia y en una global (los espacios internos repetidos se juntan), nombre repetido dentro de la especie (también una global con dos espacios), raza de otra especie en la URL (404), `species_id` prohibido, el recepcionista lista pero no escribe, especie y raza en uso por un paciente (se inactivan, el paciente las sigue mostrando, un alta nueva no las acepta, la baja da 409), baja de una global (403), baja de una raza y de una especie sin uso con su raza, y el nombre liberado |
| [`postman/syncvet-fase-01-foto.postman_collection.json`](postman/syncvet-fase-01-foto.postman_collection.json) | 1.5 (R-026) | Paciente sin foto (`has_photo` falso, ver la foto da 404), un texto con extensión `.jpg` (422), subida de un PNG, descarga por los tres roles con `Content-Type`, `nosniff`, `no-store` y el nombre con el código, reemplazo por el veterinario, `has_photo` en el detalle, borrado (y 404 al repetirlo), paciente de otra clínica (404 al ver y al subir), y un fallecido que sube y borra la foto |

**Preparación.** Una base con el `DemoSeeder` y las migraciones al día (ver **Datos**, al comienzo): la primera vez, o después de
cambiar a una rama con migraciones nuevas, recrearla o correr `php artisan migrate`. Si la rama cambia los permisos de un rol (la
1.4 le suma al veterinario `species.store`, `species.update` y `species.destroy`) y la base no se recrea, volver a sembrar los
roles: `php artisan db:seed --class=RolesAndPermissionsSeeder` (no toca los datos). Sin eso, el veterinario recibe 403 `FORBIDDEN`
en la colección de especies. La 1.5 suma `patients.upload-photo`, `patients.show-photo` y `patients.delete-photo` al recepcionista y
al veterinario: sin volver a sembrar, la colección de la foto recibe 403. Levantar el servidor en el puerto 8001:

```bash
php artisan serve --port=8001
```

**Importar y correr.**

1. En Postman: **Import** → elegir el archivo de la colección.
2. Elegir el Environment **SyncVet local** en el selector de entornos (arriba a la derecha); la primera vez, crearlo como indica
   el párrafo siguiente. `base_url` ya es `http://127.0.0.1:8001/api` en las colecciones.
3. **Run collection** (Collection Runner): correrla entera, en orden, sin cambiar el orden de los requests, con **SyncVet local**
   elegido como Environment. Esperado: todos los tests en verde.

**Los archivos de la colección de la foto.** Sube los archivos de [`postman/archivos`](postman/archivos) (`foto-paciente.png`,
un PNG de 32 × 32, y `no-es-imagen.jpg`, un texto), con la ruta relativa a la raíz del repositorio
(`docs/testing/postman/archivos/…`). Para que el Runner los encuentre:

1. **Settings** (el engranaje) → **General** → **Working directory**: elegir la carpeta del repositorio (por ejemplo,
   `C:\laragon\www\syncvet`).
2. **Allow reading files outside working directory** puede quedar apagado.
3. Si un request de subida muestra el archivo en rojo o la API responde 422 "La foto es obligatoria.", Postman no encontró el
   archivo: revisar la carpeta de trabajo y volver a importar la colección.

**La contraseña, en un Environment.** Reimportar una colección (por ejemplo, después de un cambio en el repositorio) la reemplaza
con sus variables: `demo_password` vuelve a quedar vacía y el login falla. Un Environment no se toca al reimportar y sus variables
tienen prioridad sobre las de la colección, así que la contraseña se guarda una sola vez:

1. **Environments** (barra lateral) → **+** (o **Create Environment**) y llamarlo `SyncVet local`.
2. Agregar la variable `demo_password`, de tipo **secret**, con la `DEMO_PASSWORD` del `.env` como valor, y guardar (**Save**).
3. Elegirlo en el selector de entornos antes de correr cualquiera de las colecciones.

No exportar el Environment al repositorio ni completar `demo_password` en la colección antes de exportarla: la contraseña quedaría
en el archivo. Si cambia la `DEMO_PASSWORD` del `.env`, actualizar el valor en el Environment.

**Se corren más de una vez sin recrear la base.** El primer request de cada colección genera un número de corrida y, con él, los
documentos (cédulas ficticias `99xxxxxx`, R-011, y su RUC con el dígito verificador), los emails y el microchip de esa corrida. Cada
colección usa un rango propio que no se cruza con el de las otras: clientes, de 500000 a 749998; pacientes, de 750000 a 999998;
especies, de 400000 a 499998 (su documento queda en `994…`, y los nombres de especies y razas de la corrida llevan el número);
foto, de 000000 a 099998 (su documento queda en `990…`). Los
logins guardan los tokens y los pasos guardan los ids que usan los siguientes: no hay que copiar nada a mano.

Los emails nuevos usan `mailinator.com`: con `EMAIL_DNS_CHECK=true` (el valor del `.env`), la validación consulta el MX del dominio y
rechaza `example.com` (tiene un MX nulo) y los dominios sin servidor de correo.

**Datos del `DemoSeeder` que usan.** Usuarios de la clínica A: `recepcion-a@example.com` (`{{token}}`), `admin-a@example.com`
(`{{token_admin}}`) y `vet-a@example.com` (`{{token_vet}}`); de la clínica B, `recepcion-b@example.com` (`{{token_b}}`, en la
colección de la foto, para tomar un paciente de otra clínica). Laura Martínez (`99100001`, con Firulais) en la clínica A y Toby en la
clínica B. Para probar algo a mano, no crear en la clínica A otro paciente llamado Firulais o Toby: la colección de clientes espera
encontrar un solo Firulais y ningún Toby en esa clínica.

**Lo que no está en las colecciones.** `PATIENT_IN_USE` (409): la API todavía no crea consultas, aplicaciones ni turnos; lo prueba
`PatientDeleteTest`. La baja de una especie usada solo por un paciente eliminado (409), la concurrencia de dos altas del mismo nombre y las altas
masivas del builder: lo prueban `CatalogDeleteTest`, `CatalogLockTest` y `SharedCatalogInsertTest`. De la foto: un archivo de más
de 5 MB (413 `PAYLOAD_TOO_LARGE`; no se versiona un archivo de ese tamaño), WEBP y JPG, un SVG o un GIF (422), que el reemplazo
borre el archivo anterior del disco privado, un archivo perdido (404 con aviso en el log), la transacción que falla, la imagen vuelta a codificar sin EXIF
(tampoco el GPS) ni HTML pegado al final, los 4 bytes de JPEG seguidos de HTML y la imagen de más de 25 megapíxeles (422), la
`Content-Security-Policy` de la descarga, un `photo_path` fuera de la carpeta del paciente (404) o enviado en un `PATCH` (se
ignora) y que `/storage/...` no sirva la foto, ni con firma (R-027): lo prueba `PatientPhotoTest`.

**Con Newman.** Las cuatro colecciones también corren desde la consola, sin el Runner (la contraseña va como variable de entorno,
que tiene prioridad sobre la de la colección):

```bash
npx newman run docs/testing/postman/syncvet-fase-01-clientes.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
npx newman run docs/testing/postman/syncvet-fase-01-pacientes.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
npx newman run docs/testing/postman/syncvet-fase-01-especies.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
npx newman run docs/testing/postman/syncvet-fase-01-foto.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

Se corren desde la raíz del repositorio: la colección de la foto busca sus archivos con la ruta relativa a esa carpeta.

## FASE 2 — Datos maestros

Se valida con una colección por entrega, con las mismas reglas que las de la FASE 1: la colección es la fuente de verdad de los
pasos, y la preparación, el Environment **SyncVet local** y la forma de correrla son los de la sección anterior.

| Colección | Entrega | Qué valida |
|---|---|---|
| [`postman/syncvet-fase-02-sucursales.postman_collection.json`](postman/syncvet-fase-02-sucursales.postman_collection.json) | 2.1 (R-030 y R-031) | Listado con inactivas y detalle de la sucursal por defecto con su depósito, alta de una sucursal con su depósito por defecto, código repetido en minúsculas, nombre repetido en mayúsculas y sin acento, establecimiento mal formado o en uso y teléfono inválido (422), cambio de la sucursal por defecto (la anterior deja de serlo), quitar la por defecto sin marcar otra (422), inactivar la por defecto (409), cambio del establecimiento sin facturas con `PUT` parcial, alta de depósitos (un `branch_id` del cuerpo se ignora; código en uso y nombre repetido, 422), cambio del depósito por defecto, inactivar el depósito por defecto (409), `branch_id` al editar un depósito (422), listado de depósitos sin el inactivo, sucursal inactivada (no suma depósitos: 422 en `branch`; no pasa a ser la por defecto: 422), listados con y sin inactivas, sucursal de otra clínica (404), y el recepcionista que lista y no crea (403) |
| [`postman/syncvet-fase-02-usuarios.postman_collection.json`](postman/syncvet-fase-02-usuarios.postman_collection.json) | 2.2a y 2.2b (R-032 a R-035) | Roles que se asignan, alta de un usuario con roles y sucursales (email repetido con otras mayúsculas, contraseña débil, rol inexistente y sin sucursales: 422), detalle, cambio de roles y de sucursales (la actual sale de la lista y pasa a ser la primera), login del usuario nuevo y `me`, cambio de su contraseña por el administrador (el token anterior queda revocado: 401; login con la nueva), el administrador no cambia su propia contraseña por `users/{user}` (422), quitar el rol al último administrador (409) y, con otro administrador, quitarlo (200), listado con `search`, `role` y `branch_id`, filtros mal formados (422), usuario de otra clínica (404 al verlo y al editarlo), y el recepcionista que no lista, no da de alta ni ve los roles (403). Con la 2.2b: sucursal actual con `me/current-branch` (inexistente o no asignada, 422), contraseña propia con `me/password` (la actual incorrecta, 422; el cambio correcto conserva el token en uso), el administrador no se inactiva a sí mismo (409), inactivar al usuario (su token, 401; login con la contraseña correcta, 403 `USER_INACTIVE`; con una incorrecta, 422 `INVALID_CREDENTIALS`), listado sin el inactivo salvo `include_inactive`, reactivarlo, baja de un usuario con uso (409 `USER_IN_USE`), el recepcionista que no da de baja (403), baja sin uso (404 al buscarlo y su email libre para un alta nueva) y la baja del usuario nuevo |
| [`postman/syncvet-fase-02-veterinarios.postman_collection.json`](postman/syncvet-fase-02-veterinarios.postman_collection.json) | 2.3 (R-036 y R-037) | Alta del usuario de la corrida con el rol `veterinario`, alta de un veterinario con un usuario sin el rol (422) y con la matrícula de otro en minúsculas (422), alta con código `VET-`, el mismo usuario otra vez (422), matrícula repetida, `code` en el cuerpo y comisión fuera de rango al editar (422), detalle, listado con `search` y `branch_id`, la ficha en el detalle del usuario, quitarle el rol `veterinario` al usuario (409 `USER_IS_VETERINARIAN`), inactivar la ficha, listado sin el inactivo, con `status=inactive`, con `include_inactive=1` (sin comisión ni notas) y con `status` que manda, `include_inactive` inválido y filtros mal formados (422), el recepcionista que lista y no da de alta, no edita ni da de baja (403), baja sin uso (404 después), restauración con el mismo código (alta del mismo usuario), y la limpieza: baja de la ficha, baja del usuario (409 `USER_IN_USE`) y su inactivación |
| [`postman/syncvet-fase-02-servicios.postman_collection.json`](postman/syncvet-fase-02-servicios.postman_collection.json) | 2.4 (R-038 y R-039) | Alta con código `SRV-` (nombre limpio, categoría con su etiqueta, precio, IVA y duración), nombre repetido con otras mayúsculas y sin acento, precio con decimales, duración de más de 720 o que no es múltiplo de 5 y `code` en el cuerpo (422), detalle, edición del precio, listado con `search`, el recepcionista que lista y ve el detalle y no da de alta, no edita ni da de baja (403), inactivar el servicio, listado sin el inactivo, con `include_inactive=1` y con `status` que manda, filtros mal formados (422), baja sin uso (404 después), el nombre libre para un alta nueva y su baja, y el alta con "/" en el nombre y su baja |
| [`postman/syncvet-fase-02-configuracion.postman_collection.json`](postman/syncvet-fase-02-configuracion.postman_collection.json) | 2.5 (R-040 y R-041) | Lectura de la configuración por los tres roles (datos de la clínica, las claves y `has_logo`, sin la ruta del logo), edición de los datos y las claves (el correo en minúsculas), `null` que borra una clave con `PUT` parcial, una clave desconocida y el RUC (422), el recepcionista que no edita (403), un texto con extensión `.jpg` como logo (422), subida del logo (PNG), `has_logo` en `me`, descarga por el administrador, el veterinario y el recepcionista con `Content-Type`, `nosniff`, CSP y `no-store`, reemplazo por un WEBP, el recepcionista que no sube ni borra el logo (403), borrado del logo, descarga sin logo (404 `CLINIC_LOGO_NOT_FOUND`), `me` sin logo, y la restauración de los datos y las claves |
| [`postman/syncvet-fase-02-auditoria.postman_collection.json`](postman/syncvet-fase-02-auditoria.postman_collection.json) | 2.6 (R-042 y R-043) | Dos registros propios (edita las notas de un cliente de la clínica A y las deja como estaban), y desde el comienzo de la corrida: filtro por fecha con `Z`, sin zona (hora de Asunción) y fecha sola, con el tipo, el id y el evento; por usuario; por evento (el login de la corrida); detalle con los valores, sin contraseñas ni rutas; `auditable_id` sin tipo, `to` antes de `from`, tipo fuera de la lista y fecha mal formada (422); el recepcionista no ve el listado ni el detalle (403) |

#### Sucursales (2.1)

**Volver a sembrar los roles.** La 2.1 suma `branches.index` al recepcionista y al veterinario. Si la base no se recrea, correr
`php artisan db:seed --class=RolesAndPermissionsSeeder` (no toca los datos) antes de la colección: sin eso, "31 El recepcionista
lista las sucursales" recibe 403 `FORBIDDEN`.

**Se corre más de una vez sin recrear la base.** Su rango de corrida es de 100000 a 199998 y el número va en los códigos y los
nombres de la corrida. El pedido 02 busca dos códigos de establecimiento libres en todas las páginas del listado, y al terminar la
sucursal original vuelve a ser la por defecto. Las sucursales no se eliminan: cada corrida deja una sucursal inactiva, que ocupa
un código de establecimiento (hay 999).

**Usuarios.** `admin-a@example.com` (`{{token_admin}}`), `recepcion-a@example.com` (`{{token}}`) y `admin-b@example.com`
(`{{token_b}}`, para tomar una sucursal de la clínica B).

**Lo que no está en la colección.** El establecimiento de una sucursal con facturas (422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`): la
API todavía no crea facturas. Los usuarios que pierden la sucursal actual al inactivarla (elegir otra con `PATCH me/current-branch`
está en la colección de usuarios, desde la 2.2b). La concurrencia y el orden de los bloqueos. Lo prueban `BranchUpdateTest` y
`BranchLockTest`.

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-02-sucursales.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

#### Usuarios (2.2a y 2.2b)

**Necesita internet.** Los emails de la corrida son `@mailinator.com`, y la validación del email consulta su registro MX
(`EMAIL_DNS_CHECK`, como la de los clientes): sin conexión, el alta responde 422 en `email`. No hace falta volver a sembrar los
roles: la 2.2a y la 2.2b no cambian permisos.

**Migrar la base antes de correrla.** La 2.2b suma la columna `users.is_active`: en una base creada antes, correr
`php artisan migrate` (no hace falta recrearla). Sin la columna, los pedidos de la 2.2b fallan.

**53 requests.** La primera corrida en una base suma uno (54): crea la sucursal `SUC-USR`.

**Se corre más de una vez sin recrear la base.** Su rango de corrida es de 200000 a 299998, que no usa ninguna otra colección; el
número va en el email y en la contraseña del usuario de la corrida. La primera corrida crea la sucursal `SUC-USR` y las siguientes
la reusan (si quedó inactiva, la activan); ocupa un código de establecimiento. No deja usuarios: la corrida da de baja a los dos que crea (pedidos 47 y 50).

**Un solo administrador en la clínica A.** El pedido 16 (409 `USER_LAST_ADMIN`) necesita que `admin-a@example.com` sea el único
administrador activo de la clínica A. Si se agregó otro a mano, el pedido 15 lo avisa: sacarle el rol antes de correrla.

**Un veterinario con ficha.** La baja con uso (pedido 45) usa a `vet-a@example.com`, que tiene ficha de veterinario porque la crea
el `DemoSeeder`.

**Usuarios.** `admin-a@example.com` (`{{token_admin}}`), `recepcion-a@example.com` (`{{token}}`) y `admin-b@example.com`
(`{{token_b}}`, para tomar un usuario de la clínica B).

**Lo que no está en la colección.** La concurrencia y el orden de los bloqueos (`UserLockTest`), el email tomado entre el chequeo y
el guardado (`UserStoreTest`), el usuario sin sucursal actual que recibe sucursales (`UserUpdateTest`), el límite de intentos de
`me/password` (`OwnPasswordTest`), el token vigente de un usuario inactivo (`InactiveUserTokenTest`), la baja con un registro a su
nombre en una tabla de negocio (`UserDeleteTest`) y el límite de intentos del login de un inactivo (`LoginInactiveUserTest`).

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-02-usuarios.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

#### Veterinarios (2.3)

**Migrar la base antes de correrla.** La 2.3 amplía `veterinarians.license_number` a 60 caracteres: en una base creada antes, correr
`php artisan migrate` (no hace falta recrearla). No hace falta volver a sembrar los roles: la 2.3 no cambia permisos.

**Necesita internet.** El email del usuario de la corrida es `@mailinator.com` y la validación del email consulta su registro MX
(`EMAIL_DNS_CHECK`); la contraseña se revisa en Have I Been Pwned.

**Necesita el `DemoSeeder`.** El pedido 03 toma la matrícula de la ficha de `vet-a@example.com` (para la matrícula repetida, también
en minúsculas, pedidos 05b y 08), y el 05 usa a `recepcion-a@example.com` como usuario sin el rol `veterinario`.

**32 requests.**

**Se corre más de una vez sin recrear la base.** Su rango de corrida es de 300000 a 399998, que no usa ninguna otra colección; el
número va en el email y la contraseña del usuario de la corrida y en la matrícula de su ficha. **Cada corrida deja un usuario inactivo
y una ficha eliminada:** la corrida crea su usuario con el rol `veterinario` y su ficha, y al final elimina la ficha e inactiva al
usuario. El usuario no se puede eliminar, porque la ficha eliminada cuenta como uso (409 `USER_IN_USE`, pedido 26).

**Usuarios.** `admin-a@example.com` (`{{token_admin}}`) y `recepcion-a@example.com` (`{{token}}`).

**Lo que no está en la colección.** La baja de un veterinario con uso (409 `VETERINARIAN_IN_USE`): la API todavía no crea agendas,
turnos ni consultas, y ninguna ficha de la demo tiene uso. La cubre `VeterinarianDeleteTest`, y se suma a Postman en la FASE 3, con
las agendas (anotado en el [documento de la FASE 3](../fases/fase-03-agenda-y-turnos.md)). También la concurrencia y el orden de los
bloqueos (`VeterinarianLockTest`), el índice único como respaldo de la matrícula y el Service sin el Form Request
(`VeterinarianRestoreTest`), y la bajada de la migración (`VeterinarianLicenseMigrationTest`).

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-02-veterinarios.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

#### Servicios (2.4)

**Sin migrar ni volver a sembrar.** La 2.4 no tiene migraciones ni permisos nuevos.

**Necesita el `DemoSeeder`.** Usa `admin-a@example.com` y `recepcion-a@example.com`. No necesita internet: no da de alta usuarios.

**27 requests y 64 pruebas.**

**Se corre más de una vez sin recrear la base.** No tiene rango de corrida propio (las otras colecciones ya usan los de seis dígitos):
el número de la corrida va solo en los nombres de sus servicios ("Ecografía Postman `<run>`", "Castración macho/hembra `<run>`"), que
ninguna otra colección crea. **No deja servicios activos:** cada corrida da de baja sus tres servicios (pedidos 21, 24 y 26), que
quedan eliminados con el sufijo de baja en el nombre, así que los nombres quedan libres.

**Usuarios.** `admin-a@example.com` (`{{token_admin}}`) y `recepcion-a@example.com` (`{{token}}`).

**Lo que no está en la colección.** Un servicio usado: la baja (409 `SERVICE_IN_USE`) y el cambio de nombre (422
`SERVICE_NAME_NOT_EDITABLE`). La API todavía no crea agendas, turnos ni consultas; los cubren `ServiceDeleteTest` y
`ServiceUpdateTest`, y se suman a Postman en la FASE 3 (anotado en el [documento de la FASE 3](../fases/fase-03-agenda-y-turnos.md)).
También la concurrencia y el orden de los bloqueos (`ServiceLockTest`), el índice único como respaldo del nombre y el código repetido
(409 `DOCUMENT_CODE_CONFLICT`) (`ServiceStoreTest`).

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-02-servicios.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

#### Configuración de la clínica y logo (2.5)

**Volver a sembrar los roles.** La 2.5 suma cuatro permisos (`system-settings.show`, `system-settings.upload-logo`,
`system-settings.show-logo` y `system-settings.delete-logo`; 123 → 127), y el recepcionista y el veterinario reciben `show` y
`show-logo`. Si la base no se recrea, correr `php artisan db:seed --class=RolesAndPermissionsSeeder` (no toca los datos) antes de la
colección: sin eso, la lectura de la configuración y la descarga del logo responden 403 `FORBIDDEN`. No hay migraciones.

**Necesita el `DemoSeeder`.** Usa `admin-a@example.com` (`{{token_admin}}`), `recepcion-a@example.com` (`{{token}}`) y
`vet-a@example.com` (`{{token_vet}}`).

**Necesita internet.** El correo de contacto de la corrida es `@mailinator.com` y su validación consulta el registro MX
(`EMAIL_DNS_CHECK`, como la de los clientes): sin conexión, la edición responde 422 en `settings.contact_email`.

**Los archivos.** Sube tres archivos de [`postman/archivos`](postman/archivos), con la ruta relativa a la raíz del repositorio:
`logo-clinica.png` y `logo-clinica.webp` (el mismo logo de 160 × 80 en PNG y en WEBP) y `no-es-imagen.jpg` (un texto, el de la
colección de la foto). La carpeta de trabajo de Postman tiene que ser la del repositorio, como en la colección de la foto (FASE 1,
arriba); Newman se corre desde esa carpeta.

**24 requests y 67 pruebas.**

**Deja la clínica como estaba.** El pedido 02 guarda los datos y las claves de la clínica A y el 22 los restaura al final; el logo
que sube lo borra (pedido 19). Si la clínica A ya tiene logo (subido a mano), la colección se detiene en el pedido 02 sin tocar
nada: borrarlo antes con `DELETE system-settings/logo`. Se corre más de una vez sin recrear la base: no tiene rango de corrida
propio; el número de la corrida va solo en los valores que escribe (el nombre, el correo de contacto), que el pedido 22 vuelve a
dejar como estaban.

**Lo que no está en la colección.** El logo de más de 2 MB y el que PHP corta (413 `PAYLOAD_TOO_LARGE`), una imagen de más de 25
megapíxeles, el EXIF quitado, una ruta del logo fuera de la carpeta de la clínica (404 al verlo; borrarlo limpia la referencia) y el
logo de otra clínica (`ClinicLogoTest`); una clave desconocida cargada en la base, la marca del `DemoSeeder`, una clave en otras
mayúsculas y la auditoría (`SystemSettingsTest` y `ClinicLogoTest`); la concurrencia y el orden de los bloqueos (los dos); y la
limpieza de archivos huérfanos (`PruneOrphanFilesTest`; a mano, con `php artisan files:prune-orphans --dry-run`,
[`development/setup.md`](../development/setup.md)).

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-02-configuracion.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

#### Auditoría (2.6)

**Necesita el `DemoSeeder`.** Usa `admin-a@example.com` (`{{token_admin}}`) y `recepcion-a@example.com` (`{{token}}`). No hay que
migrar ni volver a sembrar los roles: la 2.6 no tiene migraciones ni permisos nuevos (`audit-logs.index` y `audit-logs.show` ya
existían y solo los tiene el administrador). No sube archivos y no necesita internet.

**16 requests y 47 pruebas.**

**Genera sus propios registros.** Edita las notas de un cliente de la clínica A y las vuelve a dejar como estaban (dos registros
`updated`), y filtra desde el comienzo de la corrida (unos segundos antes, `{{from_z}}`), así que no depende de los registros
anteriores. `{{from_local}}` y `{{today_local}}` son el mismo momento y el mismo día en la hora de Asunción, calculados con UTC-3
(Paraguay no tiene horario de verano desde el 15/10/2024). La fecha sola del pedido 07 es el día de hoy en Asunción: corrida justo
antes de la medianoche de Asunción, puede quedar fuera un registro.

**Se corre más de una vez sin recrear la base.** No deja cambios en los datos; los registros de auditoría quedan, porque son
inmutables.

**La zona horaria del servidor.** El filtro con la fecha sin zona y la fecha sola dependen de que PHP tenga la base de zonas 2025a o
posterior ([`development/setup.md`](../development/setup.md#zona-horaria-de-paraguay-base-de-zonas-de-php)): con una más vieja, de
marzo a octubre la hora de Asunción queda corrida una hora y esos pedidos fallan.

**Lo que no está en la colección.** Un registro de otra clínica o un `LOGIN_FAILED` sin clínica (404), `search` literal, el usuario
eliminado, `from` con fracción de segundo, las horas y los desplazamientos fuera de rango, la paginación y el veterinario (403)
(`AuditLogIndexTest` y `AuditLogShowTest`); los registros viejos con la ruta de la foto o del logo (`AuditLogShowTest`); y las fechas
con hora del cast `AppDateTime` (`AppDateTimeTest`). La regresión de `deceased_at` (deuda 13) se valida con la colección de
pacientes de la FASE 1 (`syncvet-fase-01-pacientes`).

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-02-auditoria.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

## FASE 3 — Agenda y turnos

Se valida con una colección por entrega, con las mismas reglas que las anteriores: la colección es la fuente de verdad de los pasos, y
la preparación, el Environment **SyncVet local** y la forma de correrla son los de la [FASE 1](#fase-1--clientes-y-pacientes). La
entrega 3.0 no tiene endpoints: se validó con la colección de auditoría como regresión.

| Colección | Entrega | Qué valida |
|---|---|---|
| [`postman/syncvet-fase-03-agendas.postman_collection.json`](postman/syncvet-fase-03-agendas.postman_collection.json) | 3.1 (R-050) | Alta del usuario de la corrida con el rol `veterinario`, su ficha y su servicio; agenda sin veterinario ni servicio (422); alta de una agenda con veterinario y horario y de otra con solo el servicio; nombre repetido en la sucursal con otras mayúsculas y sin acento, tramos superpuestos el mismo día y un tramo fuera de la grilla de 5 minutos (422); reemplazo del horario (los tramos que se tocan pasan); un bloqueo del lunes que viene en UTC y uno con el fin antes del inicio (422); el detalle con el bloqueo próximo; el recepcionista que lista agendas y bloqueos y no crea ni edita agendas ni crea bloqueos (403); los pendientes de la FASE 2: la baja del veterinario con una agenda (409 `VETERINARIAN_IN_USE`), la baja del servicio usado por una agenda (409 `SERVICE_IN_USE`) y su cambio de nombre (422 `SERVICE_NAME_NOT_EDITABLE`); inactivar una agenda, el listado sin ella y con `include_inactive=1`; la baja del bloqueo y de las agendas sin turnos (404 después); y la limpieza |
| [`postman/syncvet-fase-03-turnos.postman_collection.json`](postman/syncvet-fase-03-turnos.postman_collection.json) | 3.2a (R-051) y 3.2b (R-053) | Alta del usuario de la corrida con el rol `veterinario`, su ficha, su servicio y una agenda con horario (el lunes en dos franjas pegadas) y un bloqueo; dos clientes y un paciente; turnos con paciente (el cliente es su dueño), con un cliente sin paciente y con un contacto; el mismo horario (409 `APPOINTMENT_OVERLAP`); un sobreturno con el recepcionista (201, `is_overbooked`); fuera del horario (422 `APPOINTMENT_OUTSIDE_HOURS`) y en el bloqueo (422 `APPOINTMENT_IN_BLOCK`); el paciente de otro cliente (422); mover con la misma duración y a un horario ocupado (409); el listado del lunes y el detalle; sin baja (405); el cambio de dueño del paciente y la edición de su turno con el formulario completo (200, queda el dueño actual); un turno que cruza dos franjas pegadas (201); la baja del cliente con un turno (409 `CLIENT_IN_USE`); los pendientes de la 3.1: mover una agenda con turnos (422 `SCHEDULE_NOT_EDITABLE`) y su baja (409 `SCHEDULE_IN_USE`); una agenda inactiva no recibe turnos (422); los estados (3.2b), en una agenda del día de la corrida: confirmar y confirmar otra vez (422 `APPOINTMENT_INVALID_TRANSITION`), ausente antes de la hora (422) y a la hora, otro turno que toma el horario liberado, deshacer el ausente sin `overbook` (409 `APPOINTMENT_OVERLAP`) y con `overbook: true` (200, sobreturno), fichar sin paciente (422) y con paciente, atendido por otra ficha (403 `APPOINTMENT_NOT_YOURS`) y por la suya, fichar un atendido (422), cancelar sin motivo (422) y con motivo, mover un cancelado (422 `APPOINTMENT_NOT_EDITABLE`); y la limpieza |
| [`postman/syncvet-fase-03-series.postman_collection.json`](postman/syncvet-fase-03-series.postman_collection.json) | 3.3 (R-054) | Series de turnos: semanal de 4, cada 2 semanas con fecha de fin, una con una fecha en un bloqueo (409 `APPOINTMENT_SERIES_CONFLICT` con la lista), más de 52 (422), mover un turno de la serie y cancelar uno, este y los siguientes y toda la serie (`meta.cancelled_count`); y la limpieza |
| [`postman/syncvet-fase-03-disponibilidad.postman_collection.json`](postman/syncvet-fase-03-disponibilidad.postman_collection.json) | 3.4 (R-055) | La disponibilidad de una agenda con franjas pegadas, un bloqueo, un turno y el mismo veterinario ocupado en otra agenda; un servicio de 60 minutos; `duration_minutes`, que manda sobre el servicio, y uno inválido (422); dar un horario ofrecido (201) y que deje de figurar; una semana y más de 7 días (422); la cola del día del veterinario (su ficha y las agendas sin veterinario de su sucursal), con su propio `veterinarian_id`, con `all=1`, de la recepción (toda la sucursal) y sus filtros de agenda, veterinario y fecha; el veterinario que no da turnos (403); un cancelado que sale de la cola; una agenda inactiva (422); y la limpieza |

#### Agendas (3.1)

**Volver a sembrar los roles.** La 3.1 suma los permisos `schedule-blocks.index`, `schedule-blocks.store` y `schedule-blocks.destroy`
(`schedule-blocks.index` también al recepcionista y al veterinario). Si la base no se recrea, correr
`php artisan db:seed --class=RolesAndPermissionsSeeder` (no toca los datos) antes de la colección: sin eso, "17 El recepcionista
lista los bloqueos" recibe 403. No hay que migrar: la 3.1 no tiene migraciones.

**Necesita internet.** El email del usuario de la corrida es `@mailinator.com` y la validación del email consulta su registro MX
(`EMAIL_DNS_CHECK`); la contraseña se revisa en Have I Been Pwned.

**Necesita el `DemoSeeder`.** Usa `admin-a@example.com` (`{{token_admin}}`) y `recepcion-a@example.com` (`{{token}}`), y la sucursal
por defecto de la clínica A.

**35 requests y 68 pruebas.**

**Se corre más de una vez sin recrear la base.** El primer pedido genera un número de corrida de seis dígitos al azar, que va en el
email y la contraseña del usuario de la corrida y en los nombres de su servicio y de sus agendas. **Cada corrida deja un usuario
inactivo, una ficha eliminada y un servicio eliminado:** al final elimina el bloqueo, las dos agendas, el servicio y la ficha, e
inactiva al usuario, que no se puede eliminar porque la ficha eliminada cuenta como uso (409 `USER_IN_USE`).

**Lo que no está en la colección.** Lo que necesita turnos, porque la API todavía no los crea: mover una agenda con turnos de sucursal
o de veterinario (422 `SCHEDULE_NOT_EDITABLE`) y la baja de una agenda con un turno (409 `SCHEDULE_IN_USE`); los cubren
`ScheduleUpdateTest` y `ScheduleDeleteTest`, y van en la colección de la 3.2a (anotado en el
[documento de la FASE 3](../fases/fase-03-agenda-y-turnos.md#32a-turnos-únicos)). También `affected_appointments` con turnos
(`ScheduleBlockTest`), el orden de los bloqueos y el reintento de la edición (`ScheduleLockTest`), y una agenda o un bloqueo de otra
clínica (404, `RouteBindingTest`).

**Con Newman.**

```bash
npx newman run docs/testing/postman/syncvet-fase-03-agendas.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

#### Turnos (3.2a y 3.2b)

**Migrar y volver a sembrar los roles.** La 3.2a suma una migración (los datos de contacto y el sobreturno de `appointments`) y el
permiso `appointments.overbook` (del administrador y la recepción); la 3.2b, otra (`attended_at` y `no_show_at`) y los permisos
`appointments.attend` (del administrador y el veterinario) y `appointments.no-show` (del administrador y la recepción). Si la base no
se recrea, correr `php artisan migrate` y `php artisan db:seed --class=RolesAndPermissionsSeeder` (no toca los datos) antes de la
colección: sin las migraciones el alta de un turno o sus estados fallan, y sin los roles "16 Sobreturno con el recepcionista a las
10:00", el ausente y el atendido reciben 403.

**Necesita internet.** El email del usuario de la corrida es `@mailinator.com` y la validación del email consulta su registro MX
(`EMAIL_DNS_CHECK`); la contraseña se revisa en Have I Been Pwned.

**Necesita el `DemoSeeder`.** Usa `admin-a@example.com` (`{{token_admin}}`), `recepcion-a@example.com` (`{{token_reception}}`) y
`vet-a@example.com` (`{{token_vet_demo}}`, otra ficha, para el 403 `APPOINTMENT_NOT_YOURS`), y la sucursal por defecto y la especie
Canino de la clínica A. Los turnos los da el recepcionista; el atendido, el veterinario de la corrida.

**58 requests y 107 pruebas.**

**Se corre más de una vez sin recrear la base.** El primer pedido genera un número de corrida (va en los documentos ficticios de los
dos clientes, en el email y la contraseña del usuario de la corrida y en los nombres) y calcula el lunes que viene, el día de los
turnos. La agenda de la corrida está abierta todos los días de 08:00 a 20:00, y el lunes en dos franjas pegadas (08:00–14:00 y
14:00–20:00), con un bloqueo de 15:00 a 16:00. **Cada corrida deja en la base sus turnos, sus dos clientes y su paciente** (que
termina siendo del cliente B, por el cambio de dueño de "24a"): un turno no se elimina (se cancela, en la 3.2b) y es uso del cliente y
del paciente. Para los estados (3.2b), cada corrida crea además una **agenda del día**, abierta todos los días de 00:00 a 23:55 con el
mismo veterinario, y dos turnos de hoy a las 00:00 (con paciente) y a las 00:30 (con un contacto), más el que toma el horario del
ausente: así la colección corre a cualquier hora (el turno de las 00:00 ya empezó, para el ausente, y es de hoy, para fichar). Al
final elimina el bloqueo e inactiva las dos agendas (con turnos no se eliminan), el servicio, la ficha del veterinario y el usuario.

**Filtro por fechas del listado.** `GET appointments` filtra por el inicio del turno (`starts_at`). Sin `from` ni `to`, trae los de
hoy. **Solo `from` (o solo `to`) es solo ese día**, no "desde ese día": `from=2026-11-16` trae los turnos del lunes 16, de 00:00 a
23:59:59 de Asunción. Para un rango, mandar los dos (hasta 31 días).

**Lo que no está en la colección.** Editar un turno fichado o atendido (422 `APPOINTMENT_NOT_EDITABLE`; la colección mueve un
cancelado): lo cubre `AppointmentUpdateTest`. Fichar otro día y deshacer el ausente otro día o cerca de la medianoche (necesitan
mover el reloj: `AppointmentCheckInTest` y `UndoNoShowTest`); el veterinario sin ficha o con la ficha eliminada
(`AppointmentCheckInTest` y `AppointmentAttendTest`); un rol sin `appointments.overbook` (`UndoNoShowTest`). Dos altas o dos
transiciones simultáneas, los reintentos y el 409 `CONFLICT` (`AppointmentConcurrencyTest` y `AppointmentTransitionConcurrencyTest`,
solo MySQL, `AppointmentLockTest` y `AppointmentTransitionLockTest`); un turno de otra clínica (404) y una agenda, un paciente, un
cliente o un servicio de otra clínica (422) (`RouteBindingTest`).

**Con Newman.** Con el servidor en el puerto 8001:

```bash
npx newman run docs/testing/postman/syncvet-fase-03-turnos.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

Corrida del 2026-11-10 contra la base de desarrollo, dos veces seguidas sin recrearla: 58 pedidos y 107 aserciones, sin fallas.
Validación del usuario del 2026-11-11, con las correcciones de la 3.3 (otra PC, Collection Runner, una iteración): 107 de 107, 0 errores (R-054).
Hasta la revisión de la 3.3 fallaba con Newman (en la app de Postman, no): 13 scripts declaraban `const data`, y `data` ya existe
como variable global en el entorno donde Newman corre los scripts (los datos de la iteración), así que la declaración chocaba. Ahora
se llama `body`. **En un script nuevo, no declarar `data`** (ni otra variable global de Newman: `responseCode`, `responseTime`,
`responseHeaders`, `iteration`); las demás colecciones no lo hacen.

#### Turnos recurrentes (3.3)

Colección aparte, [`postman/syncvet-fase-03-series.postman_collection.json`](postman/syncvet-fase-03-series.postman_collection.json),
sin migraciones ni permisos nuevos (la serie va en `POST appointments` y el alcance en `POST appointments/{appointment}/cancel`). Cada
corrida crea su veterinario, su servicio y su agenda (todos los días de 08:00 a 20:00) con un bloqueo el tercer lunes de 12:00 a
13:00, así que se corre más de una vez sin recrear la base; los turnos de la corrida quedan cancelados. Con el recepcionista: una
serie semanal de 4 (201, `meta.series_id`, la misma hora de reloj cada lunes), una cada 2 semanas con fecha de fin (la fecha de fin
entra: 4 turnos), una con una fecha en el bloqueo (409 `APPOINTMENT_SERIES_CONFLICT` con `errors.conflicts` y, en el listado, ningún
turno suyo), más de 52 (422 en `recurrence.count`), mover un turno de la serie (conserva su `series_id`), cancelar uno (`scope:
single`), este y los siguientes (`following`: el movido, que quedó antes, no cambia) y toda la serie (`series`), con
`meta.cancelled_count`, y un alcance inválido (422). Al final, la limpieza.

**Lo que no está en la colección.** Una fecha ocupada y otra fuera del horario en la misma serie, el sobreturno en una serie, la
serie que cruza el 15/10 y los fichados, ausentes, atendidos o ya empezados que la cancelación con alcance deja como están: los
cubren `tests/Feature/Appointments/Series` (`AppointmentSeriesConflictTest`, `AppointmentSeriesStoreTest` y
`AppointmentSeriesCancelTest`). Dos series simultáneas que comparten una fecha (`AppointmentSeriesConcurrencyTest`, solo MySQL), un
error o un *deadlock* a mitad de la serie (`AppointmentSeriesLockTest` y `AppointmentSeriesRetryTest`, este solo MySQL), dos
cancelaciones simultáneas de la misma serie (`AppointmentSeriesCancelConcurrencyTest`, solo MySQL), el tope de un año con `count`,
los turnos movidos al mismo instante y una serie de otra clínica (404): `AppointmentSeriesStoreTest` y `AppointmentSeriesCancelTest`.

**Con Newman.** Con el servidor en el puerto 8001:

```bash
npx newman run docs/testing/postman/syncvet-fase-03-series.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

Corrida del 2026-11-10 contra la base de desarrollo, dos veces seguidas sin recrearla: 25 pedidos y 42 aserciones, sin fallas.
Validación del usuario del 2026-11-11 (otra PC, Collection Runner, una iteración): 42 de 42, 0 errores (R-054).

#### Disponibilidad y cola del día (3.4)

Colección aparte, [`postman/syncvet-fase-03-disponibilidad.postman_collection.json`](postman/syncvet-fase-03-disponibilidad.postman_collection.json).

**Volver a sembrar los roles.** La 3.4 suma los permisos `schedules.availability` y `appointments.queue`, de los tres roles. Si la
base no se recrea, correr `php artisan db:seed --class=RolesAndPermissionsSeeder` (no toca los datos) antes de la colección: sin eso,
la disponibilidad y la cola reciben 403. No hay que migrar: la 3.4 no tiene migraciones.

**Necesita internet y el `DemoSeeder`.** El email del veterinario de la corrida es `@mailinator.com` (la validación consulta su MX);
usa `admin-a@example.com`, `recepcion-a@example.com` y la sucursal por defecto de la clínica A.

**50 requests y 81 pruebas.**

**Se corre más de una vez sin recrear la base.** El primer pedido genera un número de corrida y calcula hoy y el lunes que viene en
Asunción. Cada corrida crea su veterinario (con ficha; su sucursal actual es la por defecto), un servicio de 30 minutos y otro de 60,
y tres agendas: A (el veterinario, todos los días de 08:00 a 12:00 y de 12:00 a 13:00, franjas pegadas), B (el mismo veterinario, de
08:00 a 13:00) y C (solo el servicio). El lunes: un bloqueo de A de 08:00 a 09:00, un turno en A a las 11:00 y otro en B a las 10:00;
la disponibilidad de A ofrece 09:00, 09:30, 10:30, 11:30, 12:00 y 12:30 (las 10:00 no: el veterinario está en B), y con `duration_minutes=90` (que manda sobre el servicio de 60), solo 11:30. Hoy: un turno en A
a las 08:00 y otro en C a las 08:30 (el alta acepta horas ya pasadas de hoy, así que corre a cualquier hora). La cola por defecto del
veterinario trae los dos (su ficha y las agendas sin veterinario de su sucursal) y ninguno de otro veterinario; con su propio
`veterinarian_id`, solo el de A. Las colas de la corrida no llegan a 200 turnos: `meta.truncated` es `false`. La cola del
recepcionista se compara por inclusión (puede tener turnos de la demo o de corridas anteriores del mismo día). Al final cancela los
turnos de la corrida, elimina el bloqueo e inactiva las agendas, los servicios, la ficha y el usuario.

**Lo que no está en la colección.** Los horarios de hoy que ya empezaron y la medianoche (necesitan mover el reloj), un sobreturno y
un cancelado o un ausente en la disponibilidad, un día anterior a hoy, el veterinario o el servicio inactivos, una agenda o un
servicio de otra clínica y la garantía "todo lo ofrecido se da, todo candidato no ofrecido se rechaza": `ScheduleAvailabilityTest`. El
usuario sin sucursal actual (422 `APPOINTMENT_QUEUE_NO_BRANCH`), el veterinario sin ficha o con la ficha eliminada, otra clínica,
más de 200 turnos (`meta.truncated: true`), dos turnos a la misma hora y el conteo de consultas (sin N+1): `AppointmentQueueTest`.
El conteo de consultas de la disponibilidad y la agenda de otra sucursal: `ScheduleAvailabilityTest`. Un rol sin los permisos nuevos (403): `RoleAccessTest`.

**Con Newman.** Con el servidor en el puerto 8001:

```bash
npx newman run docs/testing/postman/syncvet-fase-03-disponibilidad.postman_collection.json --env-var "demo_password=<DEMO_PASSWORD>"
```

Corrida del 2026-11-11 contra la base de desarrollo, dos veces seguidas sin recrearla: 47 pedidos y 72 aserciones, sin fallas. Después
de las correcciones de la revisión, el mismo día: 50 pedidos y 81 aserciones, dos veces seguidas, sin fallas.
Validación del usuario del 2026-11-11 (Collection Runner, una iteración, después de las correcciones): 81 de 81, 0 errores (R-055).
