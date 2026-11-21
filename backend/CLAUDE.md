# CLAUDE.md — SyncVet (Backend)

Instrucciones permanentes para trabajar en el backend sin romper lo existente. El proceso y las reglas comunes a todo el repositorio
(metodología, voseo en los informes, documentación por cambio, Git) están en el [`CLAUDE.md`](../CLAUDE.md) de la raíz: este archivo
no las repite. Los comandos de esta guía se corren desde `backend/`, y las rutas de código (`app/…`, `tests/…`) son relativas a
`backend/`.

| Documento | Para qué |
|---|---|
| `backend/CLAUDE.md` (este) | Reglas operativas, convenciones y reglas de negocio vigentes del backend |
| [`CLAUDE.md`](../CLAUDE.md) de la raíz | Proceso y reglas comunes |
| [`docs/README.md`](../docs/README.md) | Índice, fuente de verdad de cada tema y qué actualizar en cada cambio |
| [`docs/03-plan-desarrollo/handoff.md`](../docs/03-plan-desarrollo/handoff.md) | Punto de continuidad y próximas tareas |
| [`docs/03-plan-desarrollo/README.md`](../docs/03-plan-desarrollo/README.md) | Estado por fase; el diseño de cada fase está en `docs/03-plan-desarrollo/fases/backend/` |
| [`docs/03-plan-desarrollo/prompts/README.md`](../docs/03-plan-desarrollo/prompts/README.md) | Los pasos de cada fase y su prompt |
| [`docs/03-plan-desarrollo/deuda-tecnica.md`](../docs/03-plan-desarrollo/deuda-tecnica.md) | Deuda técnica y discrepancias |
| [`docs/05-pruebas/guia-postman.md`](../docs/05-pruebas/guia-postman.md) | Pruebas manuales de la API, por fase |

Si una regla de este archivo contradice al código, **el código manda**: registrar la discrepancia y avisar, en lugar de
"corregir" por cuenta propia.

---

## 1. Estado actual

* **Stack:** Laravel 13 (PHP ^8.3), Sanctum (tokens Bearer), spatie/laravel-permission (guard `api`); MySQL 8, una base compartida por todas las clínicas, con `clinic_id`, scope global y fallo cerrado (D1: R-002, R-004 y R-007).
* **Fases:** FASE 0 a FASE 3 completadas e integradas a `main` (R-014, R-028, R-044 y R-056), con front-00 a front-02 (R-059, R-065 y R-078); front-03, demo-02 y front-04 (R-088, R-090 y R-095), integradas a `main` con la F3 del frontend (R-098, merge `5f180f3`). FASE 4 (historia clínica), EN DESARROLLO en `feature/back-4-3-servicios`: diseño aprobado (R-099); la 4.1 (la consulta, R-100), la 4.2 (cierre, enmiendas y anulación, R-101) y la 4.3 (los servicios usados, R-102), cerradas. Sigue la 4.4 (las aplicaciones).
* **Hoy:** 88 rutas, 136 permisos y 38 migraciones; la suite del cierre de la 4.3 (R-102), 1675 casos (16460 aserciones en SQLite, 18 omitidos de MySQL).
* **Dónde seguir:** el punto de continuidad y las próximas tareas, en el [handoff](../docs/03-plan-desarrollo/handoff.md); cada módulo, en [`modulos.md`](../docs/03-plan-desarrollo/modulos.md); los comandos (`clinic:create`, `DemoSeeder`, `demo:agenda`, `files:prune-orphans`), en [`setup.md`](../docs/04-desarrollo/setup.md); `postman:cleanup` (la limpieza de Newman, solo local), en la [guía de Postman](../docs/05-pruebas/guia-postman.md).
* **Detalle hasta R-090** (las carpetas de tests, los módulos con API y los comandos, como estaban en esta sección): [`historial-handoff.md`](../docs/03-plan-desarrollo/historial-handoff.md#estado-del-backend-backendclaudemd-1-hasta-r-090).

---

## 2. Metodología de trabajo en el backend

La metodología, el ritmo común de las entregas, la documentación por cambio y Git están en el [`CLAUDE.md`](../CLAUDE.md) de la raíz,
§2. En el backend, el paso 1 (inspeccionar) cubre el Service, el Request, el Resource, el Enum, la excepción, las rutas, los permisos
y los tests; el paso 2 parte de la sección 6; el diseño de cada fase va en `docs/03-plan-desarrollo/fases/backend/`.

### Ritmo de las entregas (backend)

* Manda el [Presupuesto por entrega](../CLAUDE.md#presupuesto-por-entrega) del `CLAUDE.md` de la raíz.
* MySQL (`syncvet_test_mysql`): en una entrega, solo si toca migraciones, concurrencia o búsqueda de texto, y solo los tests
  afectados (`--filter`, con `DB_CONNECTION=mysql DB_DATABASE=syncvet_test_mysql`). La suite completa en MySQL (`composer
  test:mysql`, de 5 a 6 min y medio), al cerrar la fase.
* **Nunca `php artisan migrate` ni otro comando de base "para los tests"** (`migrate --env=testing`, `db:seed`, `migrate:fresh`):
  las suites preparan su propia base (`composer test`, SQLite en `database/testing.sqlite`, por `phpunit.xml`; `composer test:mysql`,
  `syncvet_test_mysql`), y sin `.env.testing` un comando de Artisan usa `.env`, es decir la base local `syncvet` (lo que pasó en la
  4.2). La base local se migra solo a propósito, para la API, Newman o la demo.
* Los tests corren siempre sin Xdebug (Laragon lo carga en modo `debug`, y así la suite tarda casi 3 veces más): con los scripts
  de composer o con `XDEBUG_MODE=off` (`docs/04-desarrollo/testing.md`, Velocidad). Durante el trabajo, solo los tests de lo tocado
  (`XDEBUG_MODE=off php artisan test --filter <test>`). Una sola corrida completa por entrega, en la revisión (R-100): `composer test`
  (SQLite en serie, alrededor de 3 min 30 s), con la salida a un archivo y en verde en una sola corrida; de ahí salen las cifras de
  aserciones del registro. `composer test:fast` (SQLite en paralelo, alrededor de 1 min 20 s), para una comprobación rápida.
* En cada cierre de fase, `composer audit` tiene que quedar sin avisos. Un aviso se corrige con
  `composer update <paquete> --with-dependencies` dentro de las restricciones de `composer.json`. Si la corrección pide cambiar una
  restricción o subir de versión mayor, se consulta al usuario. Lo actualizado queda en el registro de decisiones (R-046).

---

## 3. Convenciones obligatorias

* La lógica de negocio va en **Services**; los Controllers solo reciben, autorizan, llaman al Service y devuelven un
  Resource con `ApiResponse`.
* Estados y tipos formales siempre con **Enum** (`ConsultationStatus::CLOSED`, nunca `'closed'`). En la base son
  `string(20)`, no columnas `ENUM` de MySQL. Comparar enums con enums.
* Errores de negocio: excepción de dominio (`ClientException`, `PatientException`…) con `error_code` y `422` (o `409` en
  conflictos). Nunca `500` ni trazas.
* Violaciones de índice único (`UniqueConstraintViolationException`): se identifican por el **nombre del índice** (MySQL,
  `services_clinic_id_name_unique`) o por `tabla.columna` (SQLite, `UNIQUE constraint failed: services.clinic_id, services.name`),
  nunca buscando el nombre de la columna en el mensaje: el mensaje trae el SQL, que nombra todas las columnas. Una violación que no
  es la esperada sigue su camino; la del código llega a `DocumentSequenceService` (409 `DOCUMENT_CODE_CONFLICT`). Ejemplos:
  `ServiceCatalogService::isNameViolation()` y `VeterinarianService::isLicenseViolation()` (R-039), `ClientService::UNIQUE_INDEXES`
  (R-067) y `PatientService::MICROCHIP_INDEXES` (deuda 22, entrega 1.3a del frontend).
* Mensajes al usuario en español con **voseo** ("Ingresá", "No tenés permisos"); código, clases, tablas, columnas y
  `error_code` en inglés. `VoseoTest` rechaza las palabras de tú y de usted, los imperativos de tú y de usted de sus verbos
  (un verbo nuevo en un mensaje se agrega a su lista) y los mensajes en inglés. Los mensajes de validación sin mensaje propio
  salen de `lang/es/validation.php`, con las mismas claves y los mismos marcadores que el del framework; todo campo de un
  Form Request lleva su nombre en `attributes` (`ValidationTranslationsTest`).
* Toda validación de un pedido va en un **Form Request**, también la de los filtros de un listado; un Controller no llama a
  `validate()` (`ValidationTranslationsTest`). Los listados validan además los filtros comunes de `ListFilters`: `search`
  texto de hasta 100, `per_page` de 1 a 100 (10 por defecto) y `page` desde 1; un filtro mal formado responde 422, nunca 500.
  La única excepción es `GET appointments`, con `per_page` de 1 a 500 (front-03, §6.10).
  `search` es literal: se busca con `whereContains`/`orWhereContains` (`App\Support\SearchTerm`), que escapan `%` y `_` y dejan
  la barra invertida como un carácter más, nunca con `like` y el texto sin escapar (R-021).
* Las migraciones no llaman a Services ni Models: solo estructura (y filas por defecto con `DB::table`).
* **Fechas con hora** (deuda 13, R-042 y R-043): todo atributo con hora de un modelo usa el cast `App\Casts\AppDateTime`, nunca
  `datetime` (`DateTimeCastCoverageTest` lo exige en todo atributo asignable, también en las subcarpetas de `app/Models`; salvo
  `created_at`, `updated_at` y `deleted_at`, que llena Eloquent, y las horas del día, `time`). Al guardar, un valor con zona (`Z`
  o un desplazamiento) se convierte a la hora de `America/Asuncion` y uno sin zona se toma como hora de Asunción; la base guarda la
  hora de Asunción y la API responde en UTC con `Z` (`toISOString()`). Los filtros y las validaciones con hora usan
  `App\Support\ApiDateTime` (`toLocal`, `startOf`/`endOf` para una fecha sola, `ceilSecond`/`floorSecond` contra columnas sin
  fracción), nunca `Date::parse` sin zona. La conversión usa la zona `America/Asuncion` de PHP, nunca un `-03:00` fijo: Paraguay es
  UTC-3 todo el año desde el 15/10/2024, pero las fechas anteriores tenían horario de verano. PHP necesita la base de zonas 2025a o
  posterior (o la extensión `timezonedb`; `docs/04-desarrollo/setup.md`); `AsuncionTimezoneTest` falla con una base vieja.
  **Zona de la sesión de MySQL** (deuda 16, R-047 y R-049, entrega 3.0): la conexión `mysql` (y la `mariadb`) fija
  `'timezone' => env('DB_TIMEZONE') ?: '-03:00'` (con `?:`, para que una variable vacía no mande `SET time_zone=''` y rompa toda
  conexión; `DatabaseTimezoneConfigTest`), y Laravel la aplica al conectar y al reconectar; así las columnas `timestamp` no
  dependen de la zona del servidor (`MySqlSessionTimezoneTest`, solo en MySQL). Es la única excepción al "nunca un `-03:00` fijo":
  en la sesión de MySQL el desplazamiento solo define cómo se guarda un `timestamp` por dentro, y la ida y la vuelta por PHP son
  exactas aunque una fecha vieja haya tenido otro desplazamiento; no se usa `America/Asuncion` porque MySQL de Laragon no tiene
  cargadas las zonas con nombre. En una fecha anterior al 15/10/2024 en que Asunción estaba en UTC-4 (el 01/07/2023, por ejemplo),
  el instante dentro de MySQL queda corrido una hora (`UNIX_TIMESTAMP()` da una hora menos): esas fechas no se comparan con la hora
  de MySQL ni se convierten en SQL. Las conversiones siguen en PHP, con `America/Asuncion`. Las horas de negocio las pone PHP
  (`now()` de Laravel, `AppDateTime` o el valor del pedido): en código nuevo, nunca el reloj de MySQL (`NOW()`, `CURRENT_TIMESTAMP`,
  `CURDATE()`, `CURRENT_DATE`, `CURTIME()`, `SYSDATE()`, `LOCALTIME`, `UTC_TIMESTAMP()`, `UNIX_TIMESTAMP()` sin argumentos y las
  demás) ni `useCurrent()` (`NoDatabaseNowTest`, en `app/`, `routes/` y las migraciones, los seeders y las factories, con las dos
  columnas de hoy permitidas por archivo y columna: `inventory_movements.created_at` y `failed_jobs.failed_at`), y las columnas
  nuevas de hora de negocio son `datetime`, no `timestamp` (el tope de 2038 de `timestamp` es la deuda 17).
* Nombres de índices y claves foráneas: hasta 64 caracteres. Si el automático es largo, darle uno propio.
* Dinero en `decimal(14,2)`, cantidades en `decimal(12,3)`, costos en `decimal(12,4)`. Cálculos con **BCMath** sobre
  strings (`App\Support\Decimal::round`), nunca `float`. Los importes en guaraníes (PYG) se redondean a **0 decimales**
  al calcular; la columna sigue en `decimal(14,2)` para admitir otra moneda.
* Claves foráneas `restrictOnDelete()` salvo hijos que viven con el padre (`cascadeOnDelete()`).
* Operaciones sobre varios registros en `DB::transaction`; concurrencia con `lockForUpdate`. **Orden de bloqueo** (para no
  generar *deadlocks*, siempre en este sentido): agenda → turno; consulta → ítem → `product_stocks` → `product_lots`;
  timbrado → factura; factura → pago; caja → pago; documento → serie de códigos (la serie, siempre última); clínica →
  catálogo (`CatalogLock`: la fila de la clínica antes de leer los nombres de especies y razas); especie → raza → serie (la
  baja de una especie o una raza, con `lockForUpdate` antes de revisar si está en uso; el alta y la edición de un paciente,
  con `sharedLock` sobre la especie y la raza que usa, antes de la serie); clínica → sucursal → depósito o usuario (el alta y
  la edición de sucursales y depósitos: `CatalogLock`, después la sucursal con `lockForUpdate`, después el depósito o los
  usuarios que la tienen como actual; también `PATCH me/current-branch`: `CatalogLock`, después la sucursal elegida y después el
  usuario, los dos con `lockForUpdate`); clínica → usuario (el alta, la edición, la inactivación y la baja de usuarios:
  `CatalogLock`, después el usuario con `lockForUpdate` antes de contar los administradores y, en la baja, de revisar el uso;
  quitar el rol `veterinario`, también antes de buscar su ficha); clínica → usuario → veterinario → serie (el alta y la
  restauración de un veterinario: `CatalogLock`, después el usuario y su ficha, también eliminada, con `lockForUpdate`, y la
  serie `VET-` al final, solo en un alta nueva); veterinario (la baja: la ficha con `lockForUpdate` antes de revisar el uso;
  las fases 3 y 4 tomarán un `sharedLock` sobre el veterinario al usarlo); clínica → servicio → serie (el alta y el cambio de
  nombre de un servicio: `CatalogLock` antes de leer los nombres; la edición y la baja: el servicio con `lockForUpdate` antes de
  revisar el uso; la serie `SRV-` al final; las fases 3 y 4 tomarán un `sharedLock` sobre el servicio al usarlo); clínica →
  configuración (editar los datos y las claves, y subir o borrar el logo: `CatalogLock` antes de leer las claves o `logo_path`).
  **FASE 3**, un solo orden para toda la fase: **clínica → veterinario → servicio → agenda → paciente → cliente → turno**; ninguna
  operación bloquea un veterinario o un servicio después de una agenda, ni una agenda después de un turno, ni un paciente después de
  un cliente; varias filas del mismo tipo, por id ascendente. Agendas (3.1, §6.10): el alta, `CatalogLock` → veterinario y servicio (`sharedLock`); la edición, `CatalogLock` (si
  llegan el nombre o la sucursal) → veterinarios (`sharedLock`: el que la agenda tiene, leído sin bloquearla, si llegan la sucursal
  o el veterinario, y el nuevo) → servicio (`sharedLock`) → agenda (`lockForUpdate`), y con la agenda bloqueada verifica que su
  veterinario no cambió en el medio (si cambió, repite: hasta 3 intentos, después 409 `CONFLICT`); el horario, la inactivación, la
  baja y los bloqueos, la agenda con `lockForUpdate` antes de revisar los turnos o escribir. Turnos (3.2a, §6.10; sin `CatalogLock`):
  el alta lee la agenda sin bloqueo y después toma su veterinario (`lockForUpdate`; serializa sus turnos entre todas sus agendas) →
  el servicio (`sharedLock`) → la agenda (`lockForUpdate`) → el paciente y el cliente (`sharedLock`), y con eso bloqueado lee el
  horario, los bloqueos y la superposición; mover (cambia `schedule_id`, `starts_at` o `ends_at`), el veterinario de la agenda de
  destino → el servicio → las dos agendas por id → el paciente y el cliente → el turno (`lockForUpdate`); una edición que no mueve,
  solo el servicio, el paciente y el cliente que llegan y el turno. Con las filas bloqueadas se verifica que no cambiaron el
  veterinario o el servicio de la agenda, ni la agenda, el paciente, el cliente o el estado del turno: si cambiaron, se repite. Un
  *deadlock* de MySQL (40001 o 1213; `DetectsConcurrencyErrors`) también se repite, hasta 3 intentos en total
  (`AppointmentService::ATTEMPTS`), y después 409 `CONFLICT`; una espera vencida (1205) no se repite: 409 `CONFLICT` enseguida. Nunca
  un 500. Las bajas del cliente y del paciente, su fila con `lockForUpdate` antes de revisar el uso (los turnos lo toman con
  `sharedLock`). El cambio de dueño del paciente (`change-owner`), en una transacción: el paciente (`lockForUpdate`) y después los dos
  clientes, el actual y el nuevo, por id (`lockForUpdate`). Estados del turno (3.2b, §6.10): cada transición, solo el turno
  (`lockForUpdate`) antes de leer su estado (liberan horario, nunca lo ocupan; no bloquean la agenda ni el veterinario); deshacer el
  ausente vuelve a ocupar el horario y bloquea como un alta: el veterinario de la agenda → la agenda → el turno (`lockForUpdate`), con
  la superposición después y los mismos reintentos (`AppointmentService::withRetries`, que usan todas las transiciones). Series
  (3.3, §6.10): el alta de una serie bloquea como el alta de un turno, una sola vez para todas las fechas, en una transacción, con los
  mismos reintentos; cancelar con `following` o `series`, todos los turnos de la serie (`lockForUpdate`, por id ascendente) antes de
  leer sus estados, con los mismos reintentos (con `single` o sin serie, solo el turno, como cada transición). **Reglas del
  horario** (3.4, R-055): el horario semanal (los tramos del mismo día que se tocan, como uno), los bloqueos y la superposición en la
  agenda y del veterinario en sus otras agendas viven en un solo lugar, `App\Services\ScheduleOccupancy` (`load()` lee una vez los
  tramos de los días de la ventana, los bloqueos y los turnos que ocupan el horario; `fit()` decide cada horario en memoria con la
  misma regla de superposición que el SQL). La usan el alta, la serie, mover, deshacer el ausente (solo los turnos,
  `loadAppointments()`) y la disponibilidad: nunca una copia de las reglas. El alta, la serie, mover y deshacer el ausente la cargan
  **después** de los bloqueos de su orden, con las filas ya bloqueadas (`AppointmentLockTest`, `AppointmentSeriesLockTest` y
  `AppointmentTransitionLockTest`). La disponibilidad y la cola del día son solo lectura y no bloquean nada: el alta vuelve a revisar.
* **Códigos internos** (`PAC-`, `CON-`, `SRV-`, `PRO-`, `VET-`): salen de `DocumentSequenceService::create()` (serie en
  `App\Enums\DocumentSeries`), nunca de `max(id) + 1`. Puede haber huecos: no son documentos fiscales.
* Documentos emitidos (factura emitida, pago, movimiento de inventario, documento del paciente, consulta cerrada) son
  **inmutables**: solo se anulan o se corrigen con otro registro. Una consulta cerrada no se edita: se corrige con enmiendas,
  registros aparte ligados a la consulta; lo escrito nunca cambia. (D4, R-099)
* Datos maestros con `SoftDeletes`; al eliminar se agrega `//deleted_<id>_<timestamp>` (`App\Support\DeletedSuffix`) a sus
  campos únicos para liberarlos. El id evita el choque de dos bajas del mismo valor en el mismo segundo; la columna deja lugar
  al sufijo entero.
  Un dato maestro ya usado no se elimina (`409 …_IN_USE`). Excepción: las agendas tienen baja física, solo sin turnos (§6.10).
* **Clínica:** todo modelo de negocio usa `BelongsToClinic`. Nunca filtrar por `clinic_id` a mano ni saltear el scope sin un
  motivo registrado. Toda regla `unique` o `exists` filtra por la clínica actual. Jobs y comandos fijan la clínica con
  `CurrentClinic::run()`. Sin clínica en contexto, la consulta falla (fallo cerrado). `clinic_id` es inmutable: cambiarlo al
  editar lanza `ClinicContextMissingException`.
* **Catálogos compartidos** (`species`, `breeds`): `clinic_id` nulo para los globales. Su scope muestra "globales + los de mi
  clínica", también con fallo cerrado. Solo se crean y editan los de la propia clínica, también en las escrituras masivas
  (`SharedCatalogBuilder`): las altas masivas (`insert`, `insertOrIgnore`, `insertGetId`, los `fillAndInsert…` y `upsert`) fijan
  la clínica en contexto en cada fila y rechazan otra o un global; sin filas (`insert([])`), no hacen nada, como en Laravel;
  `upsert` no actualiza `clinic_id` ni usa el id; `insertUsing` falla siempre. Los globales salen del seeder.
* **Usuarios:** `User` no tiene el scope de clínica, porque el login y Sanctum lo buscan antes de conocerla. Toda consulta de
  usuarios fuera del login filtra por `clinic_id` de forma explícita, y el *route binding* de un usuario de otra clínica
  responde 404. Los usuarios se eliminan siempre modelo por modelo (`$user->delete()`), nunca con un borrado masivo
  (`User::where(...)->delete()`): el borrado masivo no dispara el evento `deleted` y sus tokens seguirían vigentes.
* **Usuarios inactivos** (`users.is_active`, 2.2b): inactivar revoca todos sus tokens (evento `updated` de `User`, como el
  `deleted`; reactivar no los devuelve), así que `is_active` se cambia modelo por modelo, nunca con una actualización masiva. Un
  inactivo no entra: con la contraseña correcta, 403 `USER_INACTIVE` (después de la clínica suspendida, sin contar como intento
  fallido); con una incorrecta, el 422 `INVALID_CREDENTIALS` de siempre, para no revelar que la cuenta existe. Un token vigente de
  un inactivo recibe 403 `USER_INACTIVE` en toda ruta con `clinic` (`SetCurrentClinic`), también `me`, `me/password` y
  `me/current-branch`; `logout` sigue funcionando. Los conteos de administradores usan `User::active()`.
* **Contraseñas:** la regla vive en `App\Support\UserPassword` (`Password::defaults()`, R-009): al menos 12 caracteres, con
  mayúsculas, minúsculas, números y símbolos, y no filtrada (Have I Been Pwned). Toda función propia que reciba una contraseña
  lleva `#[\SensitiveParameter]`; la aplicación oculta los argumentos de las trazas (R-010). Los tests no salen a la red: Have I
  Been Pwned se simula en `TestCase`.
* **Datos de ejemplo:** documentos ficticios. RUC `999999xx` con su dígito verificador y cédulas `99xxxxxx` (R-011); la
  `ClinicFactory`, RUC de 9 dígitos, que hoy no se emiten (R-013). Nunca un número de 7 dígitos (el rango de las cédulas
  reales). `FictitiousDocumentsTest` revisa toda secuencia de 7 a 9 dígitos de los tests, factories, seeders, fixtures, la
  guía de Postman con sus colecciones (`docs/05-pruebas`) y el frontend (`frontend/src` y `frontend/e2e`, `*.ts` y `*.tsx`;
  R-067); los RUC reales, solo en `tests/Unit/RucCheckDigitTest` y en su espejo del frontend (`ruc.test.ts`), en su lista
  permitida. Un documento con el dígito verificador mal se arma en el código (`wrongRuc()`), no se escribe literal.
* Claves de la API en **`snake_case`**: datos, `meta` y `errors` (entrega front-00, B3). `SnakeCaseKeysTest` revisa `app/` y
  `bootstrap/app.php` y falla con una clave literal en `camelCase` en un arreglo (`'fooBar' =>`, también anidada), asignada por
  índice (`$data['fooBar'] = …`), en `compact()` o como clave de `Arr::set`, `Arr::add`, `data_set` o `data_fill`. No cuentan los
  brazos de un `match` ni los arreglos que se pasan al log (revisión, H2 y H5).
* Rutas anidadas con `scopeBindings()`. Actualizaciones parciales con `PATCH`. `apiResource` también registra `PUT`, con la
  misma semántica parcial: no reemplaza el recurso ni borra los campos que no se envían (`HttpVerbsTest`).
* Resources: `XResource` para listados, `ShowXResource` para detalle.
* Usuario que crea, emite o anula: columnas `created_by`, `issued_by`, `cancelled_by` + `cancelled_at` y
  `cancellation_reason`.

### Permisos

* Cada Controller implementa `HasMiddleware` con `permission:<recurso>.<acción>` por método.
* El permiso de cada ruta es su nombre, `recurso.acción` (en las anidadas, los dos últimos tramos: `clients.patients.store` →
  `patients.store`). Cada ruta de la API tiene un nombre propio. Una excepción va con su motivo en
  `ROUTES_WITH_ANOTHER_PERMISSION`, y una ruta sin permiso, en `ROUTES_WITHOUT_PERMISSION` (`tests/Concerns/InteractsWithApiRoutes`).
* El permiso debe existir exactamente en `RolesAndPermissionsSeeder::PERMISSIONS`. El rol `administrador` pasa todo por
  `Gate::before`: probar siempre con un rol propio.
* Roles de la clínica en `RolesAndPermissionsSeeder::clinicRoles()`: `recepcionista` y `veterinario`.

### Entorno

* Zona horaria `America/Asuncion` (UTC-3 todo el año desde el 15/10/2024); la API envía las fechas con hora en UTC (`Z`). PHP con la
  base de zonas 2025a o posterior (§3, Fechas con hora).
* Tests: en SQLite en cada entrega; en MySQL (`syncvet_test_mysql`), solo los tests afectados cuando la entrega toca migraciones,
  concurrencia o búsqueda de texto, y la suite completa (`composer test:mysql`) al cerrar la fase (§2, Ritmo de las entregas, y
  el Presupuesto por entrega del `CLAUDE.md` de la raíz). La de SQLite y la de MySQL, nunca a la vez: comparten los discos falsos de
  `Storage::fake` (`docs/04-desarrollo/testing.md`). Los procesos de una misma suite con `--parallel` sí se pueden correr juntos, porque
  cada uno tiene su base y sus discos. Sin red: `Http::preventStrayRequests()` y Have I Been Pwned simulado en `TestCase`, y
  `EMAIL_DNS_CHECK=false` (la rama con DNS, en `EmailDnsCheckTest`). Un test que compara una hora calculada congela el reloj
  (`freezeTime()`).

---

## 4. Arquitectura

```text
Controller (HasMiddleware) → Form Request → Service → Model/Eloquent → API Resource → ApiResponse
```

| Carpeta | Contenido |
|---|---|
| `app/Http/Controllers/Api` | Controllers |
| `app/Http/Requests` | Validación (mensajes en español; `XMessages` compartidos entre alta y edición) |
| `app/Services` | Reglas de negocio, `DocumentSequenceService`, `AuditLogger` |
| `app/Models` | Modelos Eloquent |
| `app/Http/Resources` | Contrato JSON |
| `app/Enums` | Estados y tipos (`label()` en español, `values()`) |
| `app/Exceptions` | `AppException` y una subclase por módulo |
| `app/Support/Decimal.php` | Redondeo BCMath |
| `app/Traits/ApiResponse.php` | `successResponse` / `errorResponse` |
| `app/Traits/Auditable.php` | Registro automático de cambios en `audit_logs` |
| `bootstrap/app.php` | Mapeo de excepciones a JSON |

### Contrato de respuesta

El formato de las respuestas, `meta.pagination`, `me`, CORS, las descargas y los `error_code` genéricos y los comunes a
varios módulos, en [`docs/01-arquitectura/contrato-api.md`](../docs/01-arquitectura/contrato-api.md).

---

## 5. Roles

| Rol | Función |
|---|---|
| `administrador` | Todo (`Gate::before`) |
| `recepcionista` | Agenda, clientes, pacientes, caja, facturación, recordatorios |
| `veterinario` | Cola de atención, historia clínica, aplicaciones, recetas y documentos |

---

## 6. Reglas de negocio vigentes

Una línea por módulo; las reglas, en `docs/02-requisitos/reglas/`, un archivo por módulo. **Antes de tocar un módulo, leer su
archivo.** Las referencias `§6.N` de la documentación llevan a estos archivos.

* [6.1 Clientes](../docs/02-requisitos/reglas/clientes.md): persona o empresa, nombres, documento por su número base y RUC, baja y uso, búsqueda y altas simultáneas.
* [6.2 Pacientes](../docs/02-requisitos/reglas/pacientes.md): código `PAC-`, especie y raza, cambio de dueño, fallecimiento, baja y uso, microchip, foto e imágenes privadas.
* [6.3 Especies y razas](../docs/02-requisitos/reglas/especies-y-razas.md): catálogo global y de la clínica, nombre único, inactivación y baja.
* [6.4 Sucursales y depósitos](../docs/02-requisitos/reglas/sucursales-y-depositos.md): sin baja, código, establecimiento SIFEN, la sucursal y el depósito por defecto.
* [6.5 Usuarios](../docs/02-requisitos/reglas/usuarios.md): alta, email, contraseña, inactivar, baja, perfil, roles, sucursales, último administrador y concurrencia.
* [6.6 Veterinarios](../docs/02-requisitos/reglas/veterinarios.md): una ficha por usuario, código `VET-`, restauración, matrícula, sucursales, baja y el rol.
* [6.7 Servicios](../docs/02-requisitos/reglas/servicios.md): código `SRV-`, nombre fijo una vez usado, precio en PYG, uso, baja y concurrencia.
* [6.8 Configuración de la clínica, logo y archivos huérfanos](../docs/02-requisitos/reglas/configuracion.md): `system-settings`, sus claves, el logo y `files:prune-orphans`.
* [6.9 Auditoría](../docs/02-requisitos/reglas/auditoria.md): consulta, clínica, listado, filtros y detalle sin datos sensibles.
* [6.10 Agendas, horario semanal y bloqueos](../docs/02-requisitos/reglas/agendas.md): sucursal y veterinario, horario, inactivación, baja física, bloqueos, listados y concurrencia.
* [6.10 (continuación) Turnos](../docs/02-requisitos/reglas/turnos.md): alta, edición y superposición, estados del turno, series, disponibilidad y cola del día.
* [6.11 Pendientes de diseño](../docs/02-requisitos/reglas/pendientes-de-diseno.md): consultas, stock, facturación y caja.
* [6.12 La consulta](../docs/02-requisitos/reglas/consultas.md): abrir desde el turno o sin turno, el veterinario, editar, límites, listado, la vista de la recepción (`consultations.show-clinical`) y concurrencia.

---

## 7. Deuda técnica abierta

Resumen; el detalle está en [`docs/03-plan-desarrollo/deuda-tecnica.md`](../docs/03-plan-desarrollo/deuda-tecnica.md).

| # | Deuda |
|---|---|
| 2 | Modelos generados desde el esquema: revisar relaciones y casts al usarlos |
| 3 | Códigos SIFEN de formas de pago y unidades de medida sin verificar |
| 5 | Sin claves foráneas compuestas (`clinic_id` + `id`); hoy lo cubren la validación y los tests de aislamiento |
| 6 | Límite del login con caché `database`: en una ráfaga simultánea pasan de 0 a 4 intentos de más; se resuelve con Redis en producción |
| 15 | Foto con un `photo_path` fuera de su carpeta: borrarla responde 404 y deja la referencia (el logo ya la limpia) |
| 17 | Tope de 2038 de las columnas `timestamp`; prioridad baja (las columnas nuevas de hora de negocio van en `datetime`, R-047) |
| 21 | Tests: `countQueries` copiado en tres tests (turnos, agenda y pacientes); prioridad baja: unificarlo cuando se toquen esos tests |
| 25 | Bloqueos: sin una consulta previa (`dry_run` o similar) de los turnos que quedarían adentro antes de crear el bloqueo; mejora no urgente (revisión de la 3.1b del frontend) |
| 26 | Disponibilidad: no excluye el propio turno al reprogramar (su horario y los que se pisan con él no figuran entre los libres; mover sí lo excluye); mejora no urgente (revisión de la 3.4 del frontend) |
| 29 | Bloqueos: la API no los da por sucursal, y la cola del frontend los pide de a una agenda (6 y 7 pedidos por carga con la demo); una entrega de soporte aditiva (cierre de la F3 del frontend) |
