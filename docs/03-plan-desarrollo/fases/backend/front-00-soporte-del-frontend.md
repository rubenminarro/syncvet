# Entrega de soporte del frontend (B1 a B3)

Entrega del backend fuera de las fases del MVP, pedida por la [F0 del frontend](../frontend/f0-base.md) antes de su entrega 0.2
(R-058). Estado: **CERRADA** (R-059): implementada, revisada, corregida y validada por el usuario el 2026-11-12 ([resultado](#resultado)).
Diseño aprobado el 2026-11-12 (R-058), en `docs/frontend-analisis`, con las [preguntas](#preguntas) respondidas. Rama:
`feature/front-00-me-sucursales`, sin integrar a `main` todavía: se integra al cerrar la F0 del frontend (R-059).

## Objetivo

Que la API le dé al frontend lo que necesita para la sesión y que su contrato sea parejo antes de que lo consuma alguien:

| # | Cambio | Por qué |
|---|---|---|
| B1 | `me` trae las sucursales activas asignadas al usuario | El selector de sucursal actual necesita la lista, y `GET branches` exige `branches.index` y lista todas las de la clínica |
| B2 | CORS acotado por una variable de entorno | Hoy, sin `config/cors.php`, rige el valor por defecto de Laravel: cualquier origen. En desarrollo el proxy de Vite no usa CORS; en otros entornos solo los orígenes configurados |
| B3 | `meta.pagination` en `snake_case` (`per_page`, `current_page`, `last_page`) | Es la única parte de la API en `camelCase`; el frontend todavía no existe, así que el cambio no rompe a nadie |

## B1. Sucursales del usuario en `me`

**Contrato.** `MeResource` suma `branches`: las sucursales **activas** asignadas al usuario (`branch_user`), ordenadas por `code`,
cada una con `id`, `code` y `name` (lo mismo que `current_branch`). Sale en todas las respuestas que usan `MeResource`: `GET me`,
`POST login` (`data.user`) y `PATCH me/current-branch`.

```json
"current_branch": { "id": 1, "code": "SUC-01", "name": "Casa central" },
"branches": [
  { "id": 1, "code": "SUC-01", "name": "Casa central" },
  { "id": 2, "code": "SUC-02", "name": "Sucursal Norte" }
]
```

**Reglas.**

* Son las mismas que acepta `PATCH me/current-branch` (`AuthService::setCurrentBranch`: activa y asignada al usuario): toda sucursal
  de la lista se puede elegir, y ninguna otra.
* El administrador también ve solo las suyas, porque `me/current-branch` le exige la asignación igual que a los demás (pregunta 1).
* Sin sucursales asignadas activas, `branches` es `[]`.
* Se carga en `AuthService::profile` con la relación filtrada (una consulta más), dentro de `CurrentClinic::run`, como las demás.

**Tests** (`tests/Feature/Auth/MeTest.php` y `CurrentBranchTest.php`).

| Caso | Esperado |
|---|---|
| Usuario con dos sucursales activas y una inactiva asignadas, y una activa no asignada | `branches` trae solo las dos activas asignadas, por `code` |
| Usuario sin sucursales | `branches` = `[]` |
| La misma forma en `POST login` y en `PATCH me/current-branch` | `data.user.branches` y `data.branches` |
| Cada sucursal de `branches` se acepta en `PATCH me/current-branch` | 200 |
| Sucursal de otra clínica asignada a mano en `branch_user` (fallo cerrado) | No aparece |
| Cantidad de consultas de `GET me` | Una más que antes, sin N+1 |

**Mutaciones.** Sin el filtro de activas; sin el filtro de asignación (todas las de la clínica); otro orden.

## B2. CORS por variable de entorno

**Cambio.** Publicar `config/cors.php` (`php artisan config:publish cors`) con:

| Clave | Valor |
|---|---|
| `paths` | `['api/*']` (sin `sanctum/csrf-cookie`: la API usa tokens Bearer; las cookies quedan para la FASE 10, R-058) |
| `allowed_origins` | La lista de `CORS_ALLOWED_ORIGINS`, separada por comas, sin espacios ni vacíos. Sin la variable o vacía: ningún origen |
| `allowed_methods`, `allowed_headers` | `['*']` |
| `exposed_headers` | `['Retry-After']`: el frontend muestra la cuenta regresiva del 429 aunque esté en otro origen |
| `supports_credentials` | `false` |
| `max_age` | `600` |

`.env.example` con `CORS_ALLOWED_ORIGINS=http://localhost:5173` (Vite en desarrollo, por si se lo usa sin el proxy). La variable,
documentada en [`backend/README.md`](../../../../backend/README.md).

**Tests** (`tests/Feature/Security/CorsTest.php`, nuevo).

| Caso | Esperado |
|---|---|
| Pedido con un `Origin` de la lista | `Access-Control-Allow-Origin` con ese origen |
| `Origin` fuera de la lista | Sin `Access-Control-Allow-Origin` |
| Preflight (`OPTIONS` con `Access-Control-Request-Method`) desde un origen permitido | 204 con los métodos permitidos |
| Variable vacía | Ningún origen permitido |
| Lista con espacios (`"http://a.test, http://b.test"`) | Los dos orígenes, sin espacios |
| 429 desde un origen permitido | `Access-Control-Expose-Headers` incluye `Retry-After` |

**Mutaciones.** `allowed_origins` en `['*']`; sin `Retry-After` en `exposed_headers`.

## B3. Paginación en `snake_case`

**Revisión de la convención.** Se buscaron claves en `camelCase` en todo lo que arma respuestas (`app/Http`, `app/Services`,
`app/Traits`, `app/Exceptions`, `app/Support`, `bootstrap/app.php`) y en los parámetros que lee la API: **solo la paginación rompe la
convención**. El resto ya es `snake_case` (`error_code`, `expires_at`, `has_logo`, `current_branch`, `meta.truncated`,
`meta.cancelled_count`, `errors.conflicts`, `per_page`, `include_inactive`, …).

**Cambio.**

```json
"meta": { "pagination": { "total": 42, "per_page": 15, "current_page": 1, "last_page": 3 } }
```

* Un solo lugar arma el bloque: `ApiResponse::paginationMeta(LengthAwarePaginator $paginator)`, que reemplaza el bloque repetido en
  los listados de los 11 controllers (`Appointment`, `AuditLog`, `Branch`, `BranchWarehouse`, `Client`, `Patient`, `ScheduleBlock`,
  `Schedule`, `Service`, `User` y `Veterinarian`).
* Los tests que leen la paginación (`AuditLogIndexTest`, `BranchIndexTest`, `ClientSearchTest`, `ListFiltersTest`,
  `PatientSearchTest`, `ScheduleIndexTest`, `ServiceIndexTest`, `UserIndexTest` y `VeterinarianIndexTest`) pasan a las claves nuevas.
* Las colecciones de Postman que la leen: `syncvet-fase-02-sucursales` y `syncvet-fase-02-usuarios`.
* `backend/CLAUDE.md` §4 (contrato de respuesta).

**Protección nueva** (`tests/Unit/SnakeCaseKeysTest.php`, como `NoDatabaseNowTest`): recorre `app/` y `bootstrap/app.php` y falla si
encuentra una clave literal de arreglo en `camelCase` (`'fooBar' =>`), para que la convención no vuelva a romperse.

**Tests.** Cada listado responde las cuatro claves nuevas y ninguna de las viejas (un caso por controller, con un *data provider*);
el test de la protección, con un archivo de ejemplo que la rompe.

**Mutaciones.** Una clave vieja que vuelve en un controller; la protección sin mirar `app/Http`.

## Qué no cambia

Rutas (78), permisos (135), migraciones (36), `error_code` y códigos HTTP. El único cambio de forma es B3, a propósito y sin
consumidores todavía (el frontend empieza después; las colecciones se actualizan en la misma entrega).

## Postman

* `syncvet-fase-02-usuarios`: `me` y `me/current-branch` con `branches` (las activas asignadas, por `code`); la paginación con las
  claves nuevas.
* `syncvet-fase-02-sucursales`: la paginación con las claves nuevas; después de inactivar una sucursal, `me` ya no la trae.
* La [guía](../../../05-pruebas/guia-postman.md) (filas de `me` y una sección de esta entrega).
* CORS no se prueba con Postman (no es un navegador): lo cubren los tests.

## Documentación

`backend/CLAUDE.md` (§1 si cambia algo del estado, §4 el contrato con `branches` en `me` y la paginación, §3 la convención
`snake_case` con su test), `backend/README.md` (`CORS_ALLOWED_ORIGINS`), la guía de Postman, este documento (Resultado), el roadmap y
el handoff, y una sección `R-NNN` en el registro.

## Preguntas

Respondidas por el usuario el 2026-11-12 (R-058).

| # | Pregunta | Respuesta |
|---|---|---|
| 1 | ¿El administrador ve en `branches` todas las sucursales activas de la clínica, o solo las asignadas? | Solo las asignadas, también el administrador |
| 2 | ¿La protección de `snake_case` (`SnakeCaseKeysTest`) entra en esta entrega? | Sí |

## Pendiente para el cierre

Nada.

## Resultado

Cerrada el 2026-11-12 (R-059). Commits en `feature/front-00-me-sucursales`: `cec996c` (ajuste de R-058), `9495067` (implementación,
WIP), `3615e60` (el `*` de CORS, WIP), `bdaa78f` (correcciones de la revisión y colección de sucursales repetible, WIP) y el cierre,
solo de documentación. Sin rutas, permisos ni migraciones nuevas (78, 135 y 36).

### Qué se hizo

| # | Cambio | Código | Tests |
|---|---|---|---|
| B1 | `me` (también `data.user` del login y `PATCH me/current-branch`) trae `branches`: las sucursales activas asignadas al usuario, por `code`, con `id`, `code` y `name`; exactamente las que acepta `me/current-branch`, también para el administrador | `Branch::selectableBy($user)` (la regla en un solo lugar, con `branches.is_active` calificada), `App\Rules\SelectableBranch` (el Request), `AuthService::setCurrentBranch` y `AuthService::profile` (una consulta), `MeResource` | `MeTest` (activas y asignadas, por `code` y no por nombre ni id, sin sucursales, el administrador, otra clínica asignada a mano, el login y una sola consulta) y `CurrentBranchTest` (cada sucursal de `me.branches` se acepta y ninguna otra, con el recepcionista y con el administrador, con una inactiva asignada y una de otra clínica; el Request rechaza antes del Service) |
| B2 | CORS por `CORS_ALLOWED_ORIGINS`: solo `api/*`, orígenes exactos `http(s)://host[:puerto]` normalizados (minúsculas, sin la barra final); lo demás, todo `*` incluido, se descarta con un aviso en el log como máximo una vez por hora; sin credenciales, `Retry-After` expuesto, `max_age` 600 | `config/cors.php`, `App\Support\CorsOrigins`, `AppServiceProvider` (`CORS_WARNING_KEY`, `Cache::add`); `.env.example` con `http://localhost:5173` | `CorsTest` (24 casos: origen permitido y no permitido, preflight 204 de una ruta con token, sin la variable o vacía, lista con espacios, 11 orígenes descartados, ningún comodín llega a php-cors, normalización, el 429 del login con `Retry-After` expuesto, el aviso una vez por hora, sin descartados y sin caché) |
| B3 | `meta.pagination` en `snake_case` (`total`, `per_page`, `current_page`, `last_page`) en los 11 listados, armado solo por `paginationMeta()` | `App\Traits\ApiResponse::paginationMeta()` y los 11 controllers | `PaginationMetaTest` (un caso por controller), los 9 tests que leían la paginación y `SnakeCaseKeysTest` (claves en arreglos, también anidadas, asignadas por índice, en `compact()` y en `Arr::set`, `Arr::add`, `data_set` y `data_fill`; sin falsos positivos con valores, brazos de `match` ni el contexto del log; el escaneo cubre todo `app/` y `bootstrap/app.php`) |

### Desvíos aceptados

El usuario aceptó los desvíos 1 a 5 el 2026-11-12 y los de la revisión al validar.

1. `paginationMeta()` es un método `protected` del trait `ApiResponse`, llamado con `$this->paginationMeta($paginator)`: llamar a
   un método estático directamente sobre un trait está obsoleto desde PHP 8.1.
2. El caso por controller de la paginación va en un test nuevo, `PaginationMetaTest`.
3. El archivo de ejemplo de `SnakeCaseKeysTest` se escribe en un archivo temporal desde el test, y un caso exige que el escaneo cubra
   el código que arma las respuestas.
4. La colección de sucursales le asigna la sucursal de la colección a `admin-a` para probar `me.branches` y al final le devuelve sus
   sucursales; la de usuarios suma el pedido 30b.
5. El contrato se documentó en `backend/CLAUDE.md` §4 (el pedido decía §5), con la convención en §3 y el perfil en §6.5.
6. (Revisión) La regla del Request es una clase, `App\Rules\SelectableBranch`, no un *closure*: `ValidationTranslationsTest` exige
   que cada regla esté clasificada.
7. (Revisión) La clave propia de `config/cors.php` es `discarded_origins` (la lista de lo descartado, para el aviso).

### Decisiones del usuario

* **El `*` de CORS** (2026-11-12): se descarta, con un aviso en el log. La revisión (H1) lo extendió a todo origen que no sea exacto:
  php-cors toma `*` como "cualquier origen" y un `*` adentro (`https://*`, `*.dominio`) como un patrón.
* **Sin merge a `main` todavía**: la rama se integra al cerrar la F0 del frontend, y la F0.1 sale de esta rama
  (`feature/front-0-1-esqueleto`).

### Revisión y correcciones

Revisión del 2026-11-12, en la misma sesión, como un revisor que no la escribió. Todas las mutaciones de la tabla fallan con los tests
nuevos.

| # | Hallazgo | Corrección | Mutación que ahora falla |
|---|---|---|---|
| H1 | `https://*` pasaba el descarte del `*` y abría CORS a cualquier origen https, sin aviso | `CorsOrigins`: solo orígenes exactos; lo demás se descarta | `*` aceptado dentro del host |
| H2 | `SnakeCaseKeysTest` no veía claves asignadas por índice (`QueueAppointmentResource` arma claves así) | El escáner revisa `$x['clave'] = …`, `compact()`, `Arr::set`, `Arr::add`, `data_set` y `data_fill` | `$data['checkedInAt']`; el escáner sin las asignaciones |
| H3 | Ningún test fallaba con el orden por nombre | Test con código, nombre e id en órdenes distintos | `orderBy('name')` y `orderBy('id')` |
| H4 | Sin el caso del administrador con una sucursal inactiva asignada | El test de equivalencia corre también con el administrador | El administrador ve sus inactivas |
| H5 | El escáner marcaba los brazos de `match` y el contexto del log | El escáner sigue los contextos (arreglo, `match`, log, atributo) | El escáner sin distinguir el `match`; sin ignorar el log |
| H6 | Un origen con barra final o en mayúsculas se aceptaba y nunca coincidía | Normalización en minúsculas y sin la barra final | Sin minúsculas; sin quitar la barra |
| H7 | El aviso del `*` salía en cada pedido | Una vez por hora con `Cache::add`; si el caché falla, avisa igual y el arranque sigue | Sin el límite por hora |
| H8 | La regla "activa y asignada" estaba en tres lugares, con `is_active` sin calificar | `Branch::selectableBy` en un solo lugar | `me` con todas las asignadas; la regla del Request sin la asignación |

### La colección de sucursales

En la primera validación del usuario, el alta (pedido 04) respondió 422. La colección del repositorio no lo reproducía con Newman (con
las variables vacías ni con los valores de la corrida anterior). Sí lo reproduce la versión anterior a B3, que lee
`meta.pagination.lastPage`: sin esa clave, el pedido 02 nunca elige los establecimientos y el 04 recibe 422 en `establishment` ("El
código de establecimiento es obligatorio."; con el valor que el Collection Runner conserva de la corrida anterior, "Ya hay una
sucursal con ese código de establecimiento."). Causa probable: el Runner tenía importada la versión anterior de la colección de
sucursales (la de usuarios estaba al día). No fueron los establecimientos agotados ni el cambio de `admin-a`.

De fondo, la colección no era repetible: cada corrida dejaba una sucursal inactiva con su código de establecimiento (hay 998). Ahora
usa siempre `SUC-PM` (alta la primera vez, reactivación con `PATCH` en las siguientes), rota su establecimiento a uno libre (pedido 14),
borra al empezar los valores de la corrida anterior, arma el identificador de la corrida con la hora y falla en el pedido 02 si la
paginación no trae `last_page`. Las sucursales `SUC-1xxxxx` de las corridas anteriores quedan en la base.

### Validación

* **Usuario** (2026-11-12, Collection Runner, una iteración, después de las correcciones de la revisión): sucursales, 123 de 123, dos
  corridas seguidas, 0 errores; usuarios, 136 de 136, 0 errores.
* **Suite en serie** sobre el código de `bdaa78f`: 1445 casos; 14183 aserciones en SQLite (14 omitidos, los de solo MySQL) y 14257 en
  MySQL, sin omitidos. `composer test:fast`: 1445 casos en verde.
* **Newman**, todas las colecciones, dos veces seguidas contra la base de desarrollo, sin fallas: clientes 48 aserciones, especies 93,
  foto 60, pacientes 61, auditoría 47, configuración 67, servicios 64, sucursales 123 (44 pedidos), usuarios 136 (54 pedidos),
  veterinarios 75, agendas 68, disponibilidad 81, series 42 y turnos 107.
