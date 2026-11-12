# Entrega de soporte del frontend (B1 a B3)

Entrega del backend fuera de las fases del MVP, pedida por la [F0 del frontend](../frontend/f0-base.md) antes de su entrega 0.2
(R-058). Estado: **DISEÑO APROBADO** por el usuario el 2026-11-12 (R-058), en `docs/frontend-analisis`, con las [preguntas](#preguntas)
respondidas. Rama de implementación: `feature/front-00-me-sucursales`, desde `main`.

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

Implementación del 2026-11-12 (commit WIP en `feature/front-00-me-sucursales`), sin validar todavía por el usuario.

**Desvíos del diseño.**

1. `paginationMeta()` es un método `protected` (no estático) del trait `App\Traits\ApiResponse`, y los controllers lo llaman con
   `$this->paginationMeta($paginator)`: llamar a un método estático directamente sobre un trait (`ApiResponse::paginationMeta`) está
   obsoleto desde PHP 8.1. Sigue siendo el único lugar que arma el bloque.
2. El caso por controller de la paginación va en un test nuevo, `tests/Feature/PaginationMetaTest.php` (11 casos con un *data
   provider*), además de los 9 tests que pasaron a las claves nuevas.
3. `SnakeCaseKeysTest`: el "archivo de ejemplo que la rompe" se escribe en un archivo temporal desde el propio test (como el código de
   ejemplo de `NoDatabaseNowTest`), para que no quede un `.php` con claves en `camelCase` dentro del repositorio. Suma un caso que
   exige que el escaneo cubra `app/Http`, `app/Traits`, `app/Exceptions`, `app/Services` y `app/Support` (sin él, la mutación "la
   protección sin mirar `app/Http`" pasaba).
4. Postman, sucursales: para ver `me.branches` antes y después de inactivar la sucursal nueva, la colección le asigna esa sucursal a
   `admin-a` (pedidos 23b a 23e y 24a) y al final le devuelve sus sucursales de antes (33b). Usuarios: un pedido nuevo, 30b (la otra
   sucursal de `me.branches` también se acepta como actual).
5. El pedido mencionó `backend/CLAUDE.md` §5 para el contrato; el contrato está en §4 (Contrato de respuesta), y ahí se documentó,
   junto con la convención `snake_case` en §3 y el perfil en §6.5.

**Desvíos aceptados.** El usuario aceptó los desvíos 1 a 5 el 2026-11-12.

**Decidido: el `*` de CORS** (usuario, 2026-11-12). Laravel (php-cors) toma un `*` de `allowed_origins` como "cualquier origen":
`config/cors.php` lo descarta de `CORS_ALLOWED_ORIGINS` (los demás orígenes siguen) y marca `wildcard_discarded`, una clave propia
que php-cors no lee; con ella, `AppServiceProvider::boot` deja un aviso en el log. El aviso sale en cada arranque de la aplicación
(en cada pedido), mientras la variable siga con el `*`. Tests: `CorsTest` (el descarte, con y sin otros orígenes, y el aviso, con y
sin `*`); las mutaciones "sin descartar" y "sin aviso" fallan.

**R-058.** Vuelven pnpm como alternativa descartada del entorno y "sin Tailwind" en la elección de la interfaz.

**Notas.**

* `.env` no se versiona: en cada PC que use el frontend sin el proxy de Vite hay que agregar `CORS_ALLOWED_ORIGINS` (está en
  `.env.example`).
* Suite al terminar: 1425 casos (30 más que el cierre de la FASE 3); SQLite en serie, 14110 aserciones (14 omitidos, los de solo
  MySQL); MySQL, 14184.
* Falta para el cierre: el resultado de esta entrega en este documento, la sección `R-NNN` del registro, el roadmap, el handoff,
  `backend/CLAUDE.md` §1 (cifras de la suite) y la validación del usuario con Postman.

### Validación del usuario y revisión (2026-11-12)

**Falla de la colección de sucursales.** En la validación del usuario, el alta (pedido 04) respondió 422. En la base de
desarrollo esa corrida (09:08) dejó solo los tres logins en la auditoría. Newman con la colección del repositorio no la reprodujo
(con las variables vacías ni desde un export con los valores de la corrida anterior). Sí la reproduce la versión anterior a B3,
que lee `meta.pagination.lastPage`: sin esa clave, el pedido 02 nunca elige los establecimientos y el 04 recibe
`{"errors": {"establishment": ["El código de establecimiento es obligatorio."]}}`; con el valor que el Collection Runner conserva de
la corrida anterior, el mismo 422 dice "Ya hay una sucursal con ese código de establecimiento". Causa probable: el Runner tenía
importada la versión anterior de la colección de sucursales (la de usuarios sí estaba al día: sus 136 aserciones son las de
la versión nueva). No fueron los establecimientos agotados (la clínica A tenía 6 sucursales) ni el cambio de `admin-a` del desvío 4. Además,
de fondo, la colección no era repetible: cada corrida dejaba una sucursal inactiva con su establecimiento (hay 998).

**Corrección de la colección.** Usa siempre la misma sucursal, `SUC-PM`: la primera corrida la da de alta y las siguientes la
reactivan (el pedido 04 pasa a `PATCH`); el pedido 14 le pasa un establecimiento libre y deja libre el anterior, así que no consume
ninguno. El pedido 02 falla si la paginación no trae `last_page`; el 01a borra los valores de la corrida anterior y arma el
identificador de la corrida con la hora (`Date.now()` en base 36), así que los códigos de los depósitos no se repiten; el 23c le
quita `SUC-PM` a `admin-a` por si una corrida anterior se cortó. 44 pedidos y 123 aserciones; corrida tres veces seguidas (la
primera crea, las otras reactivan) y desde un export con variables persistentes, sin fallas.

**Hallazgos de la revisión, corregidos.**

| # | Corrección | Tests | Mutación que ahora falla |
|---|---|---|---|
| H1 | `App\Support\CorsOrigins`: solo orígenes exactos `http(s)://host[:puerto]`; todo lo demás, también cualquier `*`, se descarta (`discarded_origins`) | `CorsTest`: 11 orígenes descartados (`https://*`, `*.dominio`, `https://*.dominio`, `https://app*.dominio`, `http://localhost:*`, sin esquema, otro esquema, con ruta, consulta o usuario) y ningún comodín llega a php-cors | Aceptar `*` dentro del host |
| H6 | El origen se normaliza: minúsculas y sin la barra final (también IPv6 entre corchetes) | `test_the_origins_are_normalized` | Sin minúsculas; sin quitar la barra |
| H7 | El aviso de descartados sale como máximo una vez por hora (`Cache::add`, `AppServiceProvider::CORS_WARNING_KEY`); si el caché falla, avisa igual y el arranque no se rompe | Dos arranques en la misma hora, un aviso; a los 61 minutos, otro; sin descartados, ninguno; sin caché, avisa | Sin el límite por hora |
| H2 | `SnakeCaseKeysTest` también revisa `$x['clave'] = …` (anidado y con `??=`, `.=`…), `compact()` y la clave de `Arr::set`, `Arr::add`, `data_set` y `data_fill` | El archivo de ejemplo; `$data['checkedInAt']` en `QueueAppointmentResource` lo hace fallar | Escáner sin las asignaciones |
| H5 | El escáner sigue los contextos (arreglo, `match`, llamada al log, atributo): no marca los brazos de un `match` ni los arreglos pasados a `Log::…`, `logger()` o `->warning(…)` | `test_values_match_arms_and_log_context_do_not_count` (también valores con mayúsculas) | Escáner que no distingue el `match`; que no ignora el log |
| H3 | — (el código ya ordenaba por `code`) | `MeTest::test_me_branches_are_ordered_by_code_not_by_name_or_id` (código, nombre e id en órdenes distintos) | `orderBy('name')`; `orderBy('id')` |
| H4 | — | El test de equivalencia de `CurrentBranchTest` corre también con el administrador, con una sucursal inactiva asignada y una de otra clínica | El administrador ve sus inactivas |
| H8 | `Branch::selectableBy($user)` (columna `branches.is_active` calificada): la usan `App\Rules\SelectableBranch` (el Request), `AuthService::setCurrentBranch` y `AuthService::profile` (`setRelation`, una consulta) | `test_the_request_rejects_a_branch_that_is_not_selectable_before_the_service` (el Service simulado no se llama) | `me` con todas las asignadas; la regla del Request sin la asignación |

**Desvíos de esta ronda.**

1. La regla del Request es una clase, `App\Rules\SelectableBranch`, no un *closure*: `ValidationTranslationsTest` exige que cada
   regla esté clasificada (se agregó a su lista, con su mensaje propio).
2. `wildcard_discarded` pasó a `discarded_origins` (la lista de lo descartado, para el aviso).
3. La colección de sucursales ya no da de alta una sucursal nueva en cada corrida (ver arriba). Las sucursales `SUC-1xxxxx` que
   dejaron las corridas anteriores siguen en la base.

**Cifras.** Suite: 1445 casos; SQLite en serie, 14183 aserciones (14 omitidos, los de solo MySQL); MySQL, 14257. Newman, todas las
colecciones dos veces seguidas contra la base de desarrollo, sin fallas (detalle en la guía de Postman).

## Resultado

Sin implementar.
