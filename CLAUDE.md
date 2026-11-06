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
* **Base de datos:** MySQL 8. Esquema del MVP completo (32 migraciones). Una base compartida por todas las clínicas, con
  `clinic_id` en las tablas de negocio, scope global y fallo cerrado (D1, registros R-002, R-004 y R-007).
* **Plan:** 11 fases (`docs/roadmap/README.md`). La FASE 0 está completada: entregas 0.1 (R-006), 0.2 (R-007), 0.3 (R-008),
  0.4 (R-012) y 0.5 (R-013 y R-014). La FASE 1 (clientes y pacientes) está en desarrollo: diseño aprobado (R-016) y entregas 1.1
  (traducciones, voseo y `PUT`/`PATCH`, R-017), 1.2 (clientes, R-018 y R-019), 1.3 (pacientes, R-020 a R-022) y 1.4
  (especies y razas, R-023 a R-025) cerradas, las tres últimas validadas por el usuario con Postman; falta la 1.5 (foto del
  paciente).
* **Tests:** suite de PHPUnit en SQLite y en MySQL (`syncvet_test_mysql`), con el acceso por rol de cada ruta
  (`tests/Feature/Access`), los mensajes en español y con voseo (`tests/Feature/Localization`, `VoseoTest`) y cada regla de
  clientes, de pacientes y del catálogo (`tests/Feature/Clients`, `tests/Feature/Patients`, `tests/Feature/Catalog`).
  Validación manual de clientes, pacientes, especies y razas con las colecciones de Postman de `docs/testing/postman`, que
  se pueden correr más de una vez sin recrear la base.
* **Módulos con API:** autenticación (`login`, `logout` y `me`, tokens de 12 horas); clientes, pacientes, y especies y razas
  (alta, edición, inactivación y baja del catálogo propio de la clínica).
* **Comandos:** `php artisan clinic:create` (alta de una clínica con su administrador; no hay alta por la API) y el
  `DemoSeeder` (dos clínicas de demostración, solo en `local` y `testing`).
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

En fases nuevas: primero diseño funcional y de datos (con las preguntas de negocio, en `docs/fases/`), después código. Ninguna
entrega empieza sin autorización del usuario y ninguna fase se declara cerrada sin evidencia (código + tests).

### Ritmo de las entregas

Para que una entrega no tarde más de lo necesario:

* En la implementación, mutaciones solo de las protecciones críticas de la entrega (alrededor de 5); la revisión hace las suyas.
* En cada mutación se corren solo los tests relacionados; la suite completa, al final.
* MySQL (`syncvet_test_mysql`) una vez por entrega, antes del cierre, salvo que la entrega toque migraciones, concurrencia,
  búsquedas de texto o autenticación: entonces también durante el trabajo.
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
  con `sharedLock` sobre la especie y la raza que usa, antes de la serie).
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

* Zona horaria `America/Asuncion`; la API envía las fechas con hora en UTC (`Z`).
* Tests: la suite completa corre en SQLite durante el trabajo, y en MySQL (`syncvet_test_mysql`) una vez por entrega, antes del
  cierre, y también durante el trabajo si se tocan migraciones, búsquedas de texto, concurrencia o autenticación (§2, Ritmo de
  las entregas): `DB_CONNECTION=mysql DB_DATABASE=syncvet_test_mysql php artisan test`.

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
| 415 | `UNSUPPORTED_MEDIA_TYPE` | Tipo de contenido no soportado |
| 422 | `VALIDATION_ERROR` | Falla un Form Request (`errors` trae los campos) |
| 429 | `TOO_MANY_REQUESTS` | Límite de pedidos (conserva `Retry-After`) |
| 503 | `SERVICE_UNAVAILABLE` | Servicio no disponible |
| Otro 4xx | `HTTP_ERROR` | Error HTTP sin código propio; conserva su estado |
| 500 | `INTERNAL_SERVER_ERROR` | Error inesperado, sin traza |

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
  muestra. La columna y el cast del modelo quedan. `photo_path` sigue en la respuesta hasta la entrega 1.5.
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
  `America/Asuncion` (`PatientService`; deuda 13).
* `deceased_at` no es anterior a `birth_date` (la que llega en el pedido o la que el paciente ya tiene), comparando el día en
  la hora de Asunción; si lo es, 422 en `deceased_at` (R-021).
* No se elimina con consultas, aplicaciones o turnos (`PATIENT_IN_USE`, 409): se registra el fallecimiento.
* `microchip` único en la clínica, de hasta 30 caracteres. La baja agrega `//deleted_<id>_<timestamp>` entero:
  `patients.microchip` admite 60 caracteres (30 del chip y 21 más el id del sufijo, con ids de hasta 9 dígitos).

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

### 6.4 Pendientes de diseño (reglas a definir antes de programar)

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
| 13 | Fechas con hora de la API: solo `deceased_at` se convierte de UTC a `America/Asuncion`; llevarlo a un lugar común antes de la FASE 3 |
