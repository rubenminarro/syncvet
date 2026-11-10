# CLAUDE.md — SyncVet (Backend)

Instrucciones permanentes para trabajar en este repositorio sin romper lo existente.

| Documento | Para qué |
|---|---|
| `CLAUDE.md` (este) | Reglas operativas, convenciones y reglas de negocio vigentes |
| [`docs/README.md`](docs/README.md) | Índice, fuente de verdad de cada tema y qué actualizar en cada cambio |
| [`docs/handoff.md`](docs/handoff.md) | Punto de continuidad y próximas tareas |
| [`docs/roadmap/README.md`](docs/roadmap/README.md) | Estado por fase; el diseño de cada fase está en `docs/fases/` |
| [`docs/prompts/README.md`](docs/prompts/README.md) | Los pasos de cada fase y su prompt |
| [`docs/roadmap/deuda-tecnica.md`](docs/roadmap/deuda-tecnica.md) | Deuda técnica y discrepancias |
| [`docs/testing/guia-postman.md`](docs/testing/guia-postman.md) | Pruebas manuales de la API, por fase |

Si una regla de este archivo contradice al código, **el código manda**: registrar la discrepancia y avisar, en lugar de
"corregir" por cuenta propia.

---

## 1. Estado actual

* **Backend/API:** Laravel 13 (PHP ^8.3), Sanctum (tokens Bearer), spatie/laravel-permission (guard `api`).
* **Base de datos:** MySQL 8. Esquema del MVP completo (34 migraciones: las 32 del MVP, `users.is_active`, de la 2.2b, y
  `veterinarians.license_number` a 60 caracteres, de la 2.3). Una
  base compartida por todas las clínicas, con `clinic_id` en las tablas de negocio, scope global y fallo cerrado (D1, registros
  R-002, R-004 y R-007).
* **Plan:** 11 fases (`docs/roadmap/README.md`). La FASE 0 está completada: entregas 0.1 (R-006), 0.2 (R-007), 0.3 (R-008),
  0.4 (R-012) y 0.5 (R-013 y R-014). La FASE 1 (clientes y pacientes) está completada (R-028): entregas 1.1 (traducciones,
  voseo y `PUT`/`PATCH`, R-017), 1.2 (clientes, R-018 y R-019), 1.3 (pacientes, R-020 a R-022), 1.4 (especies y razas, R-023
  a R-025) y 1.5 (foto del paciente, R-026 a R-028), las cuatro últimas validadas por el usuario con Postman. La FASE 2
  (datos maestros) está completada (R-044) e integrada a `main`: diseño aprobado (R-029); entregas 2.1, sucursales y depósitos
  (R-030 y R-031), 2.2a, usuarios (R-032 y R-033), 2.2b, usuarios inactivos, baja, sucursal actual y contraseña propia (R-034 y
  R-035), 2.3, veterinarios (R-036 y R-037), 2.4, servicios (R-038 y R-039), 2.5, configuración de la clínica, logo y archivos
  huérfanos (deuda 14 resuelta; R-040 y R-041), y 2.6, consulta de la auditoría y fechas con hora (deuda 13 resuelta; R-042 y
  R-043), todas validadas por el usuario con Postman. Sigue el análisis de la FASE 3 (agenda y turnos), empezando por la deuda 16.
* **Tests:** suite de PHPUnit en SQLite y en MySQL (`syncvet_test_mysql`), con el acceso por rol de cada ruta
  (`tests/Feature/Access`), los mensajes en español y con voseo (`tests/Feature/Localization`, `VoseoTest`) y cada regla de
  clientes, de pacientes, del catálogo, de sucursales y depósitos, de usuarios, de veterinarios, de servicios, de la configuración
  y de la auditoría (`tests/Feature/Clients`, `tests/Feature/Patients`, `tests/Feature/Catalog`, `tests/Feature/Branches`,
  `tests/Feature/Users`, `tests/Feature/Veterinarians`, `tests/Feature/Services`, `tests/Feature/Settings`, `tests/Feature/Audit`),
  y las fechas con hora (`AppDateTimeTest`, `DateTimeCastCoverageTest` y `AsuncionTimezoneTest`). Se corre sin Xdebug, con
  `composer test`, `composer test:mysql` y `composer test:fast` (en paralelo, con paratest; R-045).
  Validación manual de clientes, pacientes, especies y razas, la foto del paciente, sucursales y depósitos, usuarios, veterinarios,
  servicios, la configuración con el logo y la auditoría, con las colecciones de Postman de `docs/testing/postman`, que se pueden
  correr más de una vez sin recrear la base.
* **Módulos con API:** autenticación (`login`, `logout` y `me`, tokens de 12 horas, y el perfil: sucursal actual y contraseña propia); clientes, pacientes (con su foto: subir, ver y
  borrar), especies y razas (alta, edición, inactivación y baja del catálogo propio de la clínica), sucursales y sus depósitos
  (alta, edición e inactivación, sin baja), y usuarios con sus roles y sucursales (alta, edición, detalle, listado, inactivación y baja sin uso), el
  listado de roles, los veterinarios (código `VET-`, alta o restauración, edición, inactivación y baja sin uso) y el catálogo de
  servicios (código `SRV-`, alta, edición, inactivación y baja sin uso; nombre fijo una vez usado), la configuración de la
  clínica (sus datos, las claves de `system_settings` y el logo: subir, ver y borrar) y la consulta de la auditoría (listado con
  filtros y detalle, solo el administrador).
* **Comandos:** `php artisan clinic:create` (alta de una clínica con su administrador; no hay alta por la API), el
  `DemoSeeder` (dos clínicas de demostración, solo en `local` y `testing`) y `php artisan files:prune-orphans` (fotos y logos
  huérfanos de más de 24 horas, con `--dry-run`; programado a diario a las 03:00, en producción necesita el cron del
  *scheduler*, FASE 10).
* **Pendientes con esquema listo:** agenda y turnos, historia clínica, aplicaciones, documentos, recordatorios, inventario,
  caja, facturación SIFEN y pagos.
* **Frontend:** repositorio aparte, React 19 + TypeScript + Vite + Tabler.

---

## 2. Metodología de trabajo

1. Inspeccionar el código existente relacionado (Service, Request, Resource, Enum, excepción, rutas, permisos, tests).
2. Identificar las reglas de negocio y los módulos afectados (sección 6).
3. Proponer el cambio mínimo y explicar su motivo. Las decisiones de negocio se consultan, no se suponen.
4. Implementar solo el alcance autorizado.
5. Ejecutar los tests pertinentes y la suite completa antes de dar algo por terminado.
6. Verificar que no cambien contratos, permisos, `error_code` ni códigos HTTP existentes.
7. Informar archivos modificados, tests ejecutados y resultados.

Los informes, resúmenes y mensajes al usuario van siempre en español (voseo), aunque el código y los comentarios técnicos estén en
inglés.

En fases nuevas: primero diseño funcional y de datos (con las preguntas de negocio, en `docs/fases/`), después código. Ninguna
entrega empieza sin autorización del usuario y ninguna fase se declara cerrada sin evidencia (código + tests).

### Ritmo de las entregas

Para que una entrega no tarde más de lo necesario:

* En la implementación, mutaciones solo de las protecciones críticas de la entrega (alrededor de 5); la revisión hace las suyas.
* En cada mutación se corren solo los tests relacionados; la suite completa, al final.
* MySQL (`syncvet_test_mysql`) una vez por entrega, antes del cierre, salvo que la entrega toque migraciones, concurrencia,
  búsquedas de texto o autenticación: entonces también durante el trabajo.
* Los tests corren siempre sin Xdebug (Laragon lo carga en modo `debug`, y así la suite tarda casi 3 veces más): con los scripts
  de composer o con `XDEBUG_MODE=off` (`development/testing.md`, Velocidad). Durante el trabajo, `composer test:fast` (SQLite en
  paralelo, alrededor de 1 min 20 s), y los tests sueltos, con `XDEBUG_MODE=off php vendor/bin/phpunit <archivo>`. Para el cierre,
  `composer test` (SQLite en serie, alrededor de 3 min 20 s) y `composer test:mysql` (de 5 a 6 min y medio): las cifras de
  aserciones del registro salen de la suite en serie.
* En cada cierre de fase, `composer audit` tiene que quedar sin avisos. Un aviso se corrige con
  `composer update <paquete> --with-dependencies` dentro de las restricciones de `composer.json`. Si la corrección pide cambiar una
  restricción o subir de versión mayor, se consulta al usuario. Lo actualizado queda en el registro de decisiones (R-046).
* Si una entrega junta temas distintos, proponer dividirla en el diseño.

### Documentación por cambio

Cada entrega actualiza, en la misma rama, lo que indica la tabla **Mantenimiento** de [`docs/README.md`](docs/README.md). La skill
`/cerrar-entrega` lo hace sobre una entrega ya validada; `/validar-docs` audita enlaces y cifras; `/retomar` informa el estado al
empezar una sesión.

### Git

* Nunca trabajar ni commitear en `main`. Una rama por tarea: `feature/…`, `fix/…`, `docs/…`, `test/…`.
* Commit y push solo cuando el usuario lo pide. Mensajes en español, describiendo el porqué.

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
  `ServiceCatalogService::isNameViolation()` y `VeterinarianService::isLicenseViolation()` (R-039).
* Mensajes al usuario en español con **voseo** ("Ingresá", "No tenés permisos"); código, clases, tablas, columnas y
  `error_code` en inglés. `VoseoTest` rechaza las palabras de tú y de usted, los imperativos de tú y de usted de sus verbos
  (un verbo nuevo en un mensaje se agrega a su lista) y los mensajes en inglés. Los mensajes de validación sin mensaje propio
  salen de `lang/es/validation.php`, con las mismas claves y los mismos marcadores que el del framework; todo campo de un
  Form Request lleva su nombre en `attributes` (`ValidationTranslationsTest`).
* Toda validación de un pedido va en un **Form Request**, también la de los filtros de un listado; un Controller no llama a
  `validate()` (`ValidationTranslationsTest`). Los listados validan además los filtros comunes de `ListFilters`: `search`
  texto de hasta 100, `per_page` de 1 a 100 (10 por defecto) y `page` desde 1; un filtro mal formado responde 422, nunca 500.
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
  posterior (o la extensión `timezonedb`; `development/setup.md`); `AsuncionTimezoneTest` falla con una base vieja.
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
* **Códigos internos** (`PAC-`, `CON-`, `SRV-`, `PRO-`, `VET-`): salen de `DocumentSequenceService::create()` (serie en
  `App\Enums\DocumentSeries`), nunca de `max(id) + 1`. Puede haber huecos: no son documentos fiscales.
* Documentos emitidos (factura emitida, pago, movimiento de inventario, documento del paciente, consulta cerrada) son
  **inmutables**: solo se anulan o se corrigen con otro registro.
* Datos maestros con `SoftDeletes`; al eliminar se agrega `//deleted_<id>_<timestamp>` (`App\Support\DeletedSuffix`) a sus
  campos únicos para liberarlos. El id evita el choque de dos bajas del mismo valor en el mismo segundo; la columna deja lugar
  al sufijo entero.
  Un dato maestro ya usado no se elimina (`409 …_IN_USE`).
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
  reales). `FictitiousDocumentsTest` revisa toda secuencia de 7 a 9 dígitos de los tests, factories, seeders, fixtures y la
  guía de Postman; los RUC reales, solo en `tests/Unit/RucCheckDigitTest`, en su lista permitida.
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
* Tests: la suite completa corre en SQLite durante el trabajo, y en MySQL (`syncvet_test_mysql`) una vez por entrega, antes del
  cierre, y también durante el trabajo si se tocan migraciones, búsquedas de texto, concurrencia o autenticación (§2, Ritmo de
  las entregas): `composer test:mysql`. La de SQLite y la de MySQL, nunca a la vez: comparten los discos falsos de
  `Storage::fake` (`development/testing.md`). Los procesos de una misma suite con `--parallel` sí se pueden correr juntos, porque
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

```json
{ "success": true, "status": 200, "message": "...", "data": {}, "errors": null, "meta": null, "timestamp": "..." }
{ "success": false, "status": 422, "message": "...", "data": null, "errors": null, "error_code": "..." }
```

Listados: `meta.pagination` (`total`, `perPage`, `currentPage`, `lastPage`).

Las únicas respuestas exitosas sin `ApiResponse` son las descargas de un archivo (`GET patients/{patient}/photo` y
`GET system-settings/logo`, las dos con `PrivateImageStore`): el cuerpo es el archivo, con `Content-Type` según el tipo guardado,
`X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'; sandbox` y `Cache-Control: private, no-store`. Sus
errores sí usan el contrato.

`error_code` genéricos (`bootstrap/app.php`; los de negocio salen de cada excepción de dominio):

| HTTP | `error_code` | Cuándo |
|---|---|---|
| 400 | `BAD_REQUEST` | Solicitud mal formada |
| 401 | `UNAUTHENTICATED` | Sin token, o vencido o revocado |
| 403 | `FORBIDDEN` | Falta el permiso de Spatie, o la autorización (`Gate`, `abort(403)`) la niega |
| 404 | `RESOURCE_NOT_FOUND` | Ruta o modelo inexistente |
| 405 | `METHOD_NOT_ALLOWED` | Método HTTP no admitido en la ruta |
| 406 | `NOT_ACCEPTABLE` | Formato de respuesta no aceptable |
| 409 | `CONFLICT` | `abort(409)` u otro conflicto HTTP sin excepción de dominio |
| 413 | `PAYLOAD_TOO_LARGE` | El pedido supera `post_max_size` de PHP (`development/setup.md`); la foto de más de 5 MB y el logo de más de 2 MB responden lo mismo, con `errors.photo` o `errors.logo` |
| 415 | `UNSUPPORTED_MEDIA_TYPE` | Tipo de contenido no soportado |
| 422 | `VALIDATION_ERROR` | Falla un Form Request (`errors` trae los campos) |
| 429 | `TOO_MANY_REQUESTS` | Límite de pedidos (conserva `Retry-After`) |
| 503 | `SERVICE_UNAVAILABLE` | Servicio no disponible |
| Otro 4xx | `HTTP_ERROR` | Error HTTP sin código propio; conserva su estado |
| 500 | `INTERNAL_SERVER_ERROR` | Error inesperado, sin traza |

`error_code` de autenticación, usuarios, veterinarios, servicios y la configuración que no son de un solo módulo (2.2a, 2.2b, 2.3,
2.4 y 2.5):

| HTTP | `error_code` | Excepción | Cuándo |
|---|---|---|---|
| 403 | `USER_INACTIVE` | `AuthException` | Login de un usuario inactivo con la contraseña correcta, o su token vigente en una ruta con `clinic` (§3) |
| 409 | `USER_SELF_ACTION` | `UserException` | El administrador se inactiva o se da de baja a sí mismo |
| 409 | `USER_IN_USE` | `UserException` | Baja de un usuario con registros a su nombre (`UserService::USAGE`): se inactiva |
| 409 | `USER_LAST_ADMIN` | `UserException` | La clínica se queda sin un administrador activo (§6.5) |
| 409 | `USER_IS_VETERINARIAN` | `UserException` | Se le quita el rol `veterinario` a un usuario con una ficha de veterinario no eliminada (§6.6) |
| 409 | `VETERINARIAN_IN_USE` | `VeterinarianException` | Baja de un veterinario con registros (`VeterinarianService::USAGE`): se inactiva (§6.6) |
| 422 | `SERVICE_NAME_NOT_EDITABLE` | `ServiceException` | Cambio de nombre de un servicio usado (`ServiceCatalogService::USAGE`): se crea otro y se inactiva este (§6.7) |
| 409 | `SERVICE_IN_USE` | `ServiceException` | Baja de un servicio usado (`ServiceCatalogService::USAGE`): se inactiva (§6.7) |
| 404 | `CLINIC_LOGO_NOT_FOUND` | `ClinicException` | Ver o borrar el logo de una clínica sin logo; verlo con un `logo_path` fuera de la carpeta de la clínica o sin archivo (§6.8) |

---

## 5. Roles

| Rol | Función |
|---|---|
| `administrador` | Todo (`Gate::before`) |
| `recepcionista` | Agenda, clientes, pacientes, caja, facturación, recordatorios |
| `veterinario` | Cola de atención, historia clínica, aplicaciones, recetas y documentos |

---

## 6. Reglas de negocio vigentes

### 6.1 Clientes

* Persona o empresa (`ClientKind`). Una empresa lleva `business_name` y no `first_name`/`last_name` (`prohibited`); una
  persona, al revés. El tipo cambia solo si el cliente no tiene facturas (`CLIENT_KIND_NOT_EDITABLE`, 422); al cambiarlo,
  los campos del tipo nuevo son obligatorios y los del anterior se vacían.
* Nombre para mostrar: siempre `Client::display_name`, nunca concatenando nombre y apellido.
* `first_name` y `last_name` de 2 a 100, con la regla de nombres de personas que comparten clientes y usuarios
  (`App\Support\PersonName`, R-033): letras (también `ñ`, vocales con tilde y `ü`) separadas por espacios, o por un apóstrofo o un
  guion entre letras (D'Alessandro, Ana-María); el apóstrofo y el guion, nunca al principio, al final ni repetidos.
* `document_number` y `email` únicos en la clínica y obligatorios; el email, en minúsculas. Teléfono `+?[1-9]\d{7,14}`.
* El documento es único por su **número base** (`clients.document_base`, `App\Support\DocumentBase`, R-018): los dígitos
  antes del guion, sin ceros adelante. En Paraguay el RUC de una persona es su cédula más el dígito verificador, así que
  `99999903`, `099999903` y `99999903-6` son el mismo documento (`UniqueClientDocument`, 422 con el mensaje de documento
  en uso). Un documento con letras es su propia base, en mayúsculas. El modelo completa la base al guardar; la baja le
  agrega el mismo sufijo.
* La edición valida como el alta: un campo obligatorio enviado vacío responde solo "es obligatorio", y los campos del otro
  tipo admiten `null` (el frontend manda todos) y rechazan cualquier valor.
* Un documento con la forma de un RUC (`NNNNNNN-D`) debe tener su dígito verificador correcto (`App\Rules\RucCheckDigit`,
  con el cálculo de `ClinicService`; R-015). Una cédula sin guion o un documento con letras no se valida.
* La baja agrega `//deleted_<id>_<timestamp>` entero al documento y al email: `clients.document_number` admite 50
  caracteres (20 del documento y 21 más el id del sufijo, con ids de hasta 9 dígitos).
* No se elimina con pacientes, consultas o facturas (`CLIENT_IN_USE`, 409).
* La búsqueda (`search`) también encuentra al dueño por el nombre o el código de su mascota.

### 6.2 Pacientes

* Código `PAC-…`. Alta en `clients/{client}/patients` (el dueño sale de la URL; un `client_id` en el cuerpo se ignora). El
  código lo asigna la serie: `code` en el alta o en la edición → 422 (`prohibited`).
* `custom_fields` no forma parte de la API (R-015): los Requests no lo aceptan (si llega, se ignora) y la respuesta no lo
  muestra. La columna y el cast del modelo quedan.
* La raza debe ser de la especie (`PATIENT_BREED_SPECIES_MISMATCH`, 422).
* Especie y raza: globales o de la clínica, activas y no eliminadas. Al editar se acepta también la que el paciente ya tiene
  aunque se haya inactivado (el formulario la reenvía); otra inactiva → 422. El alta y la edición las vuelven a buscar con un
  bloqueo compartido (`sharedLock`) dentro de su transacción: una baja simultánea de la especie o la raza responde 422 en
  `species_id` o `breed_id` (§6.3).
* El dueño se cambia solo con `POST patients/{patient}/change-owner` (`client_id` es `prohibited` en el PATCH); el mismo
  dueño → `PATIENT_SAME_OWNER` (422). Las consultas guardan el dueño de ese momento (`consultations.client_id`). El dueño
  de un paciente fallecido también se cambia (corrige errores de carga).
* Paciente fallecido (`deceased_at`): solo cambian la fecha de fallecimiento y las notas (`PATIENT_DECEASED`, 422). Los
  demás campos pueden llegar con el valor que ya tienen (el frontend manda el formulario completo): se rechaza solo si
  cambian, comparados con los casts del modelo (R-021). Los dos listados, `patients` y `clients/{client}/patients`, los
  ocultan salvo `include_deceased=1`. `deceased_at` llega en UTC (o con su desplazamiento) y se guarda en la hora de
  `America/Asuncion` (el cast `AppDateTime`, §3; antes, `PatientService`), y sale en UTC con `Z`.
* `deceased_at` no es anterior a `birth_date` (la que llega en el pedido o la que el paciente ya tiene), comparando el día en
  la hora de Asunción; si lo es, 422 en `deceased_at` (R-021).
* No se elimina con consultas, aplicaciones o turnos (`PATIENT_IN_USE`, 409): se registra el fallecimiento.
* `microchip` único en la clínica, de hasta 30 caracteres. La baja agrega `//deleted_<id>_<timestamp>` entero:
  `patients.microchip` admite 60 caracteres (30 del chip y 21 más el id del sufijo, con ids de hasta 9 dígitos).
* **Foto** (`patients/{patient}/photo`: subir con `POST`, ver con `GET`, borrar con `DELETE`; permisos `patients.upload-photo`,
  `patients.show-photo` y `patients.delete-photo`). La suben y la borran el recepcionista y el veterinario; la ven los tres roles.
  JPG, PNG o WEBP de hasta 5 MB, por el contenido del archivo, no por la extensión (`UploadPatientPhotoRequest`); otro tipo →
  422; más grande → 413 `PAYLOAD_TOO_LARGE`. La imagen se vuelve a codificar con GD en su mismo formato (`ImageReencoder`,
  R-027): se pierden los metadatos (EXIF, también el GPS) y lo que venga pegado después de la imagen, y la orientación del EXIF
  se aplica a los píxeles. Si GD no la decodifica → 422; de más de 25 megapíxeles → 422, antes de decodificar. GD con JPEG, PNG y
  WEBP es obligatoria (`development/setup.md`). Disco propio `patient_photos` (`storage/app/private/patient-photos`; ningún
  disco tiene `serve`), en `clinics/{clinic_id}/patients/{patient_id}/{uuid}.{ext}` con la extensión del tipo detectado, sin
  redimensionar (`PatientPhotoService`). `photo_path` no es asignable en masa: solo lo cambia el Service. Ver, borrar o
  reemplazar usan `photo_path` solo si es un archivo de la carpeta del paciente; si no, 404 `PATIENT_PHOTO_NOT_FOUND` (al
  reemplazar, el archivo ajeno no se borra) y un aviso en el log (deuda 15: borrarla no limpia esa referencia). Reemplazar o
  borrar lee `photo_path` con la fila del paciente bloqueada y borra el archivo anterior después del commit (si el borrado falla,
  aviso en el log, y lo levanta `files:prune-orphans`, §6.8); si la transacción falla, se borra el nuevo. Sin foto, o con
  `photo_path` y sin archivo (aviso en el log) → 404 `PATIENT_PHOTO_NOT_FOUND`. Un paciente fallecido también sube y borra la
  foto (no es un dato clínico). La baja del paciente deja el archivo. La respuesta muestra `has_photo` (`photo_path !== null`),
  nunca `photo_path`.
* **Imágenes privadas compartidas** (2.5): lo común de la foto y del logo de la clínica (§6.8) vive en `App\Support\PrivateImageStore`
  (guardar, reemplazar después del commit, validar la ruta contra la carpeta del dueño y descargar con sus cabeceras) y en
  `App\Http\Requests\PrivateImageRequest` (tipo por el contenido, 413, megapíxeles y la imagen vuelta a codificar). La foto mantiene
  sus respuestas, códigos, mensajes y rutas.

### 6.3 Especies y razas

* Catálogo compartido (§3): las globales (`clinic_id` nulo) y las propias de la clínica. Lo administran el administrador y el
  `veterinario`; el `recepcionista` solo lo lista. Razas anidadas en `species/{species}/breeds`, con los permisos de especie
  (`species.index`, `species.store`, `species.update` y `species.destroy`; R-015).
* Las globales no se editan, no se inactivan ni se eliminan desde una clínica (`CATALOG_GLOBAL_NOT_EDITABLE`, 403, antes de
  validar el cuerpo: lo revisa el `authorize()` del Form Request, y otra vez el Service). Una clínica no oculta una global para sí.
* Nombre único sin distinguir mayúsculas ni acentos ni espacios (`App\Support\CatalogName`): sin los de los extremos y con los
  internos repetidos juntados en uno, también al guardar ("Pastor  Alemán" se guarda y se compara como "Pastor Alemán").
  Comparado en la aplicación para que SQLite y MySQL den lo mismo: una especie, frente a las globales y a las propias no
  eliminadas; una raza, lo mismo **dentro de su especie**. Repetido → 422 `VALIDATION_ERROR` en `name`. El alta y el cambio de
  nombre bloquean antes la fila de la clínica (`CatalogLock`). Hasta 60 caracteres la especie y 80 la raza, con al menos una
  letra (`CatalogMessages::NAME_REGEX`: "--" o "12" → 422).
* `is_active = false` la oculta de los listados (salvo `include_inactive=1`) y de las altas de pacientes; los pacientes que ya
  la usan la siguen mostrando (§6.2). Se inactiva aunque esté en uso. Una raza propia puede ir en una especie global.
* La raza no cambia de especie (`species_id` `prohibited` al editar; en el alta sale de la URL y uno en el cuerpo se ignora). Una
  raza de otra especie en la URL → 404 (`scopeBindings()`).
* Baja (`SoftDeletes`) solo si ningún paciente, tampoco uno eliminado, la usa: `SPECIES_IN_USE` o `BREED_IN_USE` (409). El
  uso se revisa dentro de la transacción de la baja, con la especie y sus razas (o la raza) ya bloqueadas (`lockForUpdate`;
  el paciente toma un `sharedLock`, §6.2 y §3): un alta simultánea de un paciente no deja una especie o raza eliminada en uso.
  La baja de una especie se lleva sus razas (las propias de la clínica) en la misma transacción. Agrega
  `//deleted_<id>_<timestamp>` entero al nombre: las columnas admiten 90 y 110 caracteres.

### 6.4 Sucursales y depósitos

* Sin baja: solo se inactivan (`is_active`). Lo administra el administrador; el `recepcionista` y el `veterinario` solo listan
  sucursales (`branches.index`). Depósitos anidados en `branches/{branch}/warehouses` (listado y alta; la sucursal sale de la URL
  y un `branch_id` en el cuerpo se ignora) y editados en `warehouses/{warehouse}` (`branch_id` `prohibited`).
* Código (`code`) que escribe el administrador: obligatorio, hasta 20, `^[A-Z0-9-]+$`, se guarda en mayúsculas, único en la
  clínica (también el de un depósito) y editable. Nombre de 2 a 100 con al menos una letra (`CatalogMessages::NAME_REGEX`),
  único sin mayúsculas, acentos ni espacios repetidos (`CatalogName`): la sucursal en la clínica, el depósito dentro de su
  sucursal. Los únicos los revisa el Service con la clínica bloqueada (`CatalogLock`): repetido → 422 `VALIDATION_ERROR`.
* `establishment` (SIFEN): tres dígitos de `001` a `999`, único en la clínica. Cambia solo si la sucursal no tiene facturas
  (`BRANCH_ESTABLISHMENT_NOT_EDITABLE`, 422); el mismo valor no es un cambio. `address` hasta 255 y `phone` con la regla de
  clientes, opcionales.
* Siempre una sucursal por defecto en la clínica y un depósito por defecto en cada sucursal. `is_default: true` en otra (activa)
  la pasa a ser la por defecto y la anterior deja de serlo, en la misma transacción. `is_default: false` en la por defecto → 422
  en `is_default` (en otra no cambia nada: el frontend manda el formulario completo). Una inactiva no es la por defecto (422). La
  por defecto no se inactiva: `BRANCH_DEFAULT_NOT_DEACTIVATABLE` o `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE` (409). Si la clínica no
  tiene sucursal por defecto (o la sucursal, depósito por defecto), la que se crea pasa a serlo.
* El alta de una sucursal crea su depósito por defecto en la misma transacción (`warehouse_code` obligatorio, `warehouse_name`
  "Principal" si no llega); al usuario que la crea no se le asigna. Una sucursal inactiva no suma depósitos: 422 en `branch`
  ("La sucursal está inactiva.").
* Inactivar una sucursal deja sin sucursal actual (`current_branch_id` nulo, usuario por usuario para la auditoría) a quienes la
  tenían como actual; su asignación (`branch_user`) y sus depósitos no cambian.
* Listados paginados con `ListFilters`: solo los activos, salvo `include_inactive=1`; `search` en código y nombre.

### 6.5 Usuarios

* Alta, edición, detalle y listado (`users`) y el listado de roles (`roles`, sin paginar): solo el administrador (P2; permisos
  `users.index`, `users.store`, `users.show`, `users.update`, `users.destroy` y `roles.index`). El recepcionista y el veterinario
  reciben 403. Un usuario de otra clínica → 404. `clinic_id` no se recibe: lo fija `UserService`.
* `first_name` y `last_name` de 2 a 100, con la regla de nombres de personas (`App\Support\PersonName`, §6.1); `name` lo arma el
  Service (`first_name last_name`).
* Email obligatorio, solo ASCII, en minúsculas, hasta 150 (deja lugar al sufijo de baja), con la validación de los clientes
  (`EMAIL_DNS_CHECK`), y **único en toda la base**, también entre usuarios eliminados (es el login, R-029): lo revisa el Service, no
  una regla `unique`; el índice único es el respaldo de dos altas simultáneas en clínicas distintas. Repetido → 422 "Ya hay un
  usuario con ese email."
* Contraseña con `UserPassword` (R-009): obligatoria en el alta, opcional al editar. Si llega al editar a otro usuario, se cambia y se
  revocan todos los tokens de ese usuario (aunque sea la misma); el token de quien hace el cambio sigue. La propia no se cambia por
  `users/{user}`: 422 en `password`, "Para cambiar tu contraseña usá tu perfil.", también vacía o nula (se cambia con
  `PATCH me/password`, que pide la actual y conserva el token en uso; R-033).
* Inactivar (`is_active` en `UpdateUserRequest`, 2.2b): revoca todos los tokens del usuario; reactivar no los devuelve (§3). El
  administrador no se inactiva ni se da de baja a sí mismo: 409 `USER_SELF_ACTION`, "No podés inactivarte ni darte de baja a vos
  mismo." (reenviar `is_active: true` a sí mismo no es inactivarse).
* Baja (`DELETE users/{user}`, P1): solo sin uso. Uso: la ficha de veterinario (también eliminada) o cualquier fila de una tabla
  de negocio que lo referencie (`UserService::USAGE`, todas las claves foráneas a `users` salvo `audit_logs` y `branch_user`; un
  test exige que la lista las cubra). Con uso → 409 `USER_IN_USE`, "El usuario tiene registros a su nombre y no se puede eliminar:
  inactivalo." Al eliminar: `//deleted_<id>_<timestamp>` en el email (queda libre para un alta nueva), tokens revocados y
  `branch_user` conservado; la auditoría guarda `updated` (el email) y `deleted`.
* Perfil (2.2b; cualquier usuario autenticado y activo, sin permiso: `ROUTES_WITHOUT_PERMISSION`):
  * `PATCH me/current-branch` con `branch_id`: una sucursal activa de la clínica asignada al usuario (si no, 422 en `branch_id`); el
    Service la vuelve a revisar con la clínica bloqueada (§3). Responde `MeResource`.
  * `PATCH me/password` con `current_password` (incorrecta → 422 en `current_password`) y `password` (`UserPassword`, distinta de la
    actual). Conserva el token del pedido y revoca los demás del usuario. Límite: 5 intentos por minuto por usuario, contados antes
    de verificar la actual (el sexto, 429 `AUTH_TOO_MANY_ATTEMPTS` con `Retry-After`, aunque sea correcta); los errores de
    validación no cuentan y un cambio correcto limpia el contador (R-034).
* `roles`: 1 o más de `administrador`, `recepcionista` y `veterinario` (`App\Enums\UserRole`, P3), sin repetidos; `syncRoles`, y el
  cambio queda en la auditoría como `ROLES_SYNCED`. La respuesta los muestra como `{name, label}`, igual que `GET roles`.
* `branch_ids`: 1 o más sucursales activas de la clínica, sin repetidas; al editar se acepta también una inactiva que el usuario ya
  tiene. El Service las vuelve a leer con la clínica bloqueada (una inactivada entre la validación y el guardado → 422). El cambio
  queda en la auditoría como `updated` con `branch_ids` antes y después.
* Sucursal actual (`current_branch_id`): en el alta, la por defecto si está en `branch_ids`; si no, la primera activa de la lista;
  sin ninguna activa, ninguna. Al editar `branch_ids`, si el usuario no tiene sucursal actual o la actual sale de la lista, se elige
  con la misma regla (R-033).
* Último administrador (P4): si un administrador activo deja de serlo (pierde el rol, se inactiva o se da de baja) y no queda otro
  administrador activo de la clínica → 409 `USER_LAST_ADMIN`, "La clínica tiene que tener al menos un administrador activo.",
  también cuando se quita el rol a sí mismo. Cuentan solo los administradores activos (`is_active`) y no eliminados de la clínica
  (R-034); quitarle el rol a un administrador inactivo no cambia nada (200).
* Listado paginado con `ListFilters`: los usuarios activos de la clínica (`inClinic()`), sin eliminados, salvo
  `include_inactive=1`; `search` en nombre, apellido y email; `role` y `branch_id` (una sucursal de la clínica, activa o no); orden por apellido, nombre e id.
* Concurrencia: el alta, la edición, la inactivación y la baja bloquean la clínica (`CatalogLock`) y, salvo el alta, después el
  usuario (`lockForUpdate`), antes de contar los administradores; la baja revisa el uso dentro de su transacción (§3).

### 6.6 Veterinarios

* ABM en `veterinarians` (`apiResource`; permisos `veterinarians.index`, `.store`, `.show`, `.update` y `.destroy`). Lo administra el
  administrador; el `recepcionista` y el `veterinario` solo listan (`veterinarians.index`): el detalle, con la comisión y las notas,
  es solo del administrador, y el listado (`VeterinarianResource`) no muestra `commission_percentage` ni `notes` (R-037).
* Una ficha por usuario (`veterinarians.user_id` es único en toda la base, también entre eliminadas). `user_id` obligatorio en el
  alta: un usuario activo y no eliminado de la clínica (si no, 422 "El usuario no existe o está inactivo."), con el rol
  `veterinario` (422 "El usuario no tiene el rol veterinario.") y sin una ficha no eliminada (422 "Ese usuario ya es
  veterinario."). El Service lo vuelve a revisar con el usuario bloqueado (§3). `user_id` es `prohibited` al editar.
* Código `VET-` de la serie (`DocumentSeries::VETERINARIAN`); `code` en el alta o en la edición → 422 (`prohibited`).
* **Restauración** (P6): si el usuario tiene una ficha eliminada, el alta la restaura (misma fila y mismo código, sin usar la serie)
  con los datos del pedido; los que no llegan vuelven a su valor por defecto, como en un alta. Responde 201, igual que un alta, y la
  auditoría guarda `updated` (si cambian datos) y `restored`.
* `license_number` opcional, hasta 30, única en la clínica: se pasa a mayúsculas y sin espacios en los extremos **antes de validar**
  (vacía, nula), así que `mv-1001` choca con `MV-1001` en SQLite y en MySQL (R-037). Repetida → 422 "Ya hay un veterinario con esa
  matrícula." (el índice único es el respaldo de dos pedidos simultáneos: el mismo 422). `specialty` hasta 100;
  `commission_percentage` de 0 a 100 con hasta 2 decimales, 0 por defecto, sin cálculos (P7); `notes` hasta 1000; `status`
  `active` o `inactive` (`VeterinarianStatus`), `active` por defecto.
* Sucursales (P5): las de su usuario (`branch_user`); la respuesta las muestra y el listado filtra por `branch_id` (una sucursal de
  la clínica, activa o no).
* Se inactiva (`status: inactive`) aunque esté en uso. Un usuario inactivo no cambia su ficha; la respuesta muestra
  `user.is_active` (las fases 3 y 4 no lo asignan).
* Baja (`SoftDeletes`) solo sin uso: una fila de `schedules`, `appointments`, `consultations`, `consultation_items`,
  `patient_applications` o `patient_documents` (`VeterinarianService::USAGE`, con `DB::table`; un test exige que cubra toda clave
  foránea a `veterinarians`). Con uso → 409 `VETERINARIAN_IN_USE`, "El veterinario tiene registros y no se puede eliminar:
  inactivalo." Al eliminar, `//deleted_<id>_<timestamp>` entero en la matrícula: la columna admite 60. La ficha eliminada cuenta
  como uso de su usuario (§6.5, `USER_IN_USE`).
* Quitarle el rol `veterinario` (`users/{user}`) a un usuario con una ficha no eliminada → 409 `USER_IS_VETERINARIAN`, "El usuario
  tiene una ficha de veterinario: eliminala antes de quitarle el rol."; con la ficha eliminada, se permite. `ShowUserResource`
  muestra `veterinarian` (`id`, `code`), o `null` sin ficha o con la ficha eliminada.
* Listado paginado con `ListFilters`: solo los activos; `status` filtra por un estado y manda; `include_inactive=1` sin `status`
  trae activos e inactivos (nunca los eliminados); `search` en el código, el nombre y el apellido del usuario y la matrícula; orden
  por código.

### 6.7 Servicios

* Catálogo en `services` (`apiResource`; permisos `services.index`, `.store`, `.show`, `.update` y `.destroy`), con
  `ServiceCatalogService`. Lo administra el administrador; el `recepcionista` y el `veterinario` listan y ven el detalle
  (`services.index` y `services.show`) y reciben 403 al escribir. Un servicio de otra clínica → 404.
* Código `SRV-` de la serie (`DocumentSeries::SERVICE`); `code` en el alta o en la edición → 422 (`prohibited`).
* Alta: `name`, `category` (`ServiceCategory`) y `standard_price` obligatorios (el precio, para que no quede en 0 por olvido);
  `duration_minutes` (30), `tax_rate` (10) y `status` (`active`) toman su valor por defecto si no llegan (R-038). Edición con
  semántica parcial.
* Nombre de 2 a 120, con al menos una letra, sin `//` (el comienzo del sufijo de baja) y solo letras, números, espacios y
  `. , - ' ( ) / + %` (`ServiceMessages::NAME_REGEX`, propio de servicios: "Castración macho/hembra", "Vacuna 5+1"; R-039).
  `CatalogMessages::NAME_REGEX`, el de sucursales, especies y razas, no cambia. Se guarda limpio (`CatalogName::clean`) y es único
  en la clínica sin mayúsculas, acentos ni espacios repetidos (`CatalogName`), frente a los no eliminados; repetido → 422
  "Ya hay un servicio con ese nombre." (también si lo frena el índice `(clinic_id, name)`, el respaldo de dos pedidos simultáneos).
* **Un servicio usado no cambia de nombre:** 422 `SERVICE_NAME_NOT_EDITABLE`, "El servicio ya se usó y no puede cambiar de nombre:
  creá otro e inactivá este." Cuenta como cambio solo lo que cambia el nombre ya limpio: reenviar el mismo (con espacios de más)
  pasa; cambiar solo las mayúsculas responde 422. El precio, el IVA, la duración, la categoría, la descripción y el estado se editan
  siempre (lo usado guarda su precio).
* `standard_price`: entero de 0 a 999.999.999.999, sin decimales mientras la moneda sea PYG (pregunta 21; `App\Rules\WholeAmount`):
  un entero de JSON o un texto de solo dígitos; se rechazan los decimales (también `"150000.00"` y `150000.0`), los negativos, los
  exponentes, los separadores de miles y los floats de JSON. Se guarda con BCMath (sin float) y la respuesta lo muestra como texto
  con dos decimales (`"150000.00"`). `tax_rate` 0, 5 o 10 (`TaxRate`), como número en la respuesta. `duration_minutes` de 5 a 720 en
  múltiplos de 5 (pregunta 22). `description` hasta 1000. La respuesta muestra `category` y `status` como `{value, label}`.
* Uso (`ServiceCatalogService::USAGE`, con `DB::table`; un test exige que cubra toda clave foránea a `services`): una fila de
  `schedules`, `appointments` o `consultation_items`.
* Se inactiva (`status: inactive`) aunque esté usado. Baja (`SoftDeletes`) solo sin uso: con uso → 409 `SERVICE_IN_USE`, "El
  servicio ya se usó y no se puede eliminar: inactivalo." Al eliminar, `//deleted_<id>_<timestamp>` entero en el nombre (la columna
  admite 150) y el nombre queda libre.
* Listado paginado con `ListFilters`: solo los activos; `status` filtra por un estado y manda; `include_inactive=1` sin `status`
  trae activos e inactivos (nunca los eliminados); `category`; `search` literal en el código y el nombre; orden por nombre e id.
* Concurrencia (§3): el alta y el cambio de nombre bloquean la clínica (`CatalogLock`) antes de leer los nombres; la edición y la
  baja bloquean el servicio antes de revisar el uso; la serie va al final.

### 6.8 Configuración de la clínica, logo y archivos huérfanos

* `system-settings` (`GET`, y `PATCH`/`PUT` con semántica parcial; `SystemSettingsService`): la leen los tres roles
  (`system-settings.show`) y la edita el administrador (`system-settings.update`; los demás, 403). La respuesta
  (`SystemSettingsResource`) trae `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (cada clave editable, `null` sin
  valor) y `has_logo`.
* Datos de la clínica: `name` (2 a 100) y `legal_name` (2 a 150), editables; `ruc` y `ruc_dv` se muestran y no se editan (422,
  `prohibited`).
* Claves (`App\Enums\SystemSettingKey`, lista cerrada, cada una con su regla): `contact_email` (email en minúsculas, hasta 150),
  `contact_phone` (la regla de teléfonos de clientes), `website` (URL `https`, hasta 150) y `document_footer` (texto, hasta 500).
  `settings.<clave>: null` borra la fila; una clave sin valor no tiene fila. Una clave fuera de la lista → 422 en `settings.<clave>`,
  "La clave … no existe en la configuración.", sin cambios a medias. Dos claves son internas (no se editan ni salen en la respuesta):
  `logo_path` (la ruta del logo) y `demo_data` (la marca del `DemoSeeder`, R-010).
* Una fila con una clave que no está en el enum (cargada sin la API) se ignora al leer, con un aviso en el log, y no se borra: la
  lectura toma las filas sin el modelo, porque el cast de `key` lanzaría un `ValueError` (un 500; R-041). La clave se compara exacta
  al leer; al escribir, una fila con la clave en otras mayúsculas (MySQL no las distingue, tampoco su índice único) se toma y queda
  con la clave del enum.
* **Logo** (`system-settings/logo`: subir con `POST`, ver con `GET`, borrar con `DELETE`; permisos `system-settings.upload-logo`,
  `system-settings.show-logo` y `system-settings.delete-logo`): lo sube y lo borra el administrador; lo ven los tres roles. Las
  reglas de la foto del paciente (§6.2, `PrivateImageStore`), con su propio límite: JPG, PNG o WEBP de hasta 2 MB por el contenido
  (`UploadClinicLogoRequest`), vuelto a codificar sin EXIF y de hasta 25 megapíxeles; más grande → 413 `PAYLOAD_TOO_LARGE` con
  `errors.logo`. Disco propio `clinic_logos` (`storage/app/private/clinic-logos`, sin `serve`), en
  `clinics/{clinic_id}/logo/{uuid}.{ext}`. El anterior se borra después del commit; si la transacción falla, se borra el nuevo.
  Responde `SystemSettingsResource`.
* La ruta va en la clave interna `logo_path` y nunca sale de la API: `has_logo` (`logo_path !== null`, como `has_photo`) en la
  configuración y en `me` (`clinic.has_logo`). Tampoco queda en la auditoría: `SystemSetting::auditValues()` guarda `"(archivo)"`
  en lugar de la ruta en el alta, la modificación y la baja, y `auditContext()` suma siempre la clave (R-041).
* Ver el logo usa `logo_path` solo si es un archivo de la carpeta de la clínica; si no, o sin archivo, 404
  `CLINIC_LOGO_NOT_FOUND` ("La clínica no tiene logo.") y un aviso en el log. Borrarlo sin logo → 404; con un `logo_path` fuera de
  la carpeta, limpia la referencia (200) sin tocar ningún archivo (R-041; la foto no lo hace, deuda 15). Al reemplazar, el
  archivo ajeno no se borra.
* Concurrencia (§3): editar la configuración y subir o borrar el logo bloquean la clínica (`CatalogLock`) antes de leer las claves
  o `logo_path`.
* **Archivos huérfanos** (`php artisan files:prune-orphans`, con `--dry-run`; `OrphanFileService`; programado a diario a las 03:00
  en `routes/console.php`, y en producción necesita el cron del *scheduler*, FASE 10): por cada clínica (`CurrentClinic::run`)
  borra los archivos de `clinics/{id}/patients/` (disco `patient_photos`) y de `clinics/{id}/logo/` (disco `clinic_logos`) que no
  sean el `photo_path` de un paciente de la clínica (también eliminado) ni su `logo_path`, y que tengan más de 24 horas. Nunca
  borra fuera de esas carpetas: las rutas de la base solo sirven para conservar. Cada borrado queda en el log. Una clínica que no
  se puede revisar (un enlace simbólico, un error del disco) queda en el log, no se borra nada en ella y se sigue con las demás.
  Termina con 1 si una clínica falló o un borrado no se pudo hacer. `--dry-run` informa lo mismo que borraría, sin borrar.

### 6.9 Auditoría

* Consulta en `audit-logs` (`GET` listado y `GET audit-logs/{auditLog}` detalle; permisos `audit-logs.index` y `audit-logs.show`):
  solo el administrador (P14); el recepcionista y el veterinario reciben 403. Sin rutas de escritura (405): los registros son
  inmutables (`AUDIT_LOG_IMMUTABLE` en el modelo).
* Clínica: `AuditLog` tiene el `ClinicScope`; un registro de otra clínica o sin clínica (`LOGIN_FAILED` con un email desconocido,
  `clinic_id` nulo) nunca aparece en el listado y en el detalle responde 404.
* Listado (`AuditLogResource`): `id`, `event` (`{value, label}`), `auditable_type` (el tipo corto, `"patient"`), `auditable_id`,
  `user` (`id`, `name`; también eliminado; `null` sin usuario), `ip_address` y `created_at` (UTC, `Z`); sin `old_values` ni
  `new_values`. Por `created_at` e `id` descendentes, paginado con `ListFilters`.
* Filtros (`IndexAuditLogRequest`): `from` y `to` (una fecha sola, de 00:00 a 23:59:59 de Asunción, o una fecha con hora con o sin
  zona, por `ApiDateTime::FILTER_FORMAT`: horas de 00 a 23 y desplazamientos de hasta ±14:00; otro formato, también "tomorrow", →
  422 "Ingresá una fecha (2026-11-09) o una fecha con hora (2026-11-09T13:00:00Z)."; `from` con fracción de segundo se redondea
  hacia arriba y `to` hacia abajo; `to` anterior a `from` → 422 en `to`), `user_id` (un usuario de la clínica, también eliminado;
  si no, 422), `event` (`AuditEvent`), `auditable_type` (lista cerrada `AuditLog::AUDITABLE_TYPES`, que `AuditableTypesTest`
  compara con los modelos con `Auditable`), `auditable_id` (exige el tipo: 422 en `auditable_type`) y `search` literal en el nombre
  o el email del usuario.
* Detalle (`ShowAuditLogResource`): suma `old_values`, `new_values` y `user_agent`. Nunca muestra contraseñas ni tokens
  (`AuditLogger` guarda `password`, `remember_token` y `token` como `********`) ni rutas de archivos: la foto del paciente se audita
  como `"(archivo)"` (`Patient::auditValues()`), como el logo (R-041); en los registros anteriores, el detalle cambia por
  `"(archivo)"` el `photo_path` (sin quitar la clave) y cualquier valor con la forma `clinics/{id}/logo/…`, también anidado.

### 6.10 Pendientes de diseño (reglas a definir antes de programar)

* **Turnos:** sin superposición en la misma agenda (validar con `lockForUpdate` sobre la agenda); transiciones de
  `AppointmentStatus`; un turno fichado abre la consulta.
* **Consultas:** qué se edita después de cerrar; quién puede reabrir.
* **Stock:** el consumo en consulta descuenta del depósito de la sucursal; lote obligatorio si `tracks_lots`.
* **Facturación:** emisión con timbrado (`invoice_stamps.next_number` con `lockForUpdate`), envío a SIFEN por cola,
  cancelación como evento SIFEN.
* **Caja:** una caja abierta por usuario; el pago en efectivo exige caja abierta.

---

## 7. Deuda técnica abierta

Resumen; el detalle está en [`docs/roadmap/deuda-tecnica.md`](docs/roadmap/deuda-tecnica.md).

| # | Deuda |
|---|---|
| 2 | Modelos generados desde el esquema: revisar relaciones y casts al usarlos |
| 3 | Códigos SIFEN de formas de pago y unidades de medida sin verificar |
| 5 | Sin claves foráneas compuestas (`clinic_id` + `id`); hoy lo cubren la validación y los tests de aislamiento |
| 6 | Límite del login con caché `database`: en una ráfaga simultánea pasan de 0 a 4 intentos de más; se resuelve con Redis en producción |
| 15 | Foto con un `photo_path` fuera de su carpeta: borrarla responde 404 y deja la referencia (el logo ya la limpia) |
| 16 | Columnas `timestamp` de MySQL según la zona del servidor (`config/database.php` no fija `timezone`); `useCurrent()` de `inventory_movements` depende de eso. Analizar antes del diseño de la FASE 3 |
