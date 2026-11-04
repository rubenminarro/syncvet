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
* **Base de datos:** MySQL 8. Esquema del MVP completo (27 migraciones). Una base compartida por todas las clínicas, con
  `clinic_id` en las tablas de negocio (D1, registros R-002 y R-004). Se aplica en la FASE 0: hasta entonces el esquema no tiene la columna.
* **Plan:** 11 fases (`docs/roadmap/README.md`); ninguna empezó.
* **Módulos con API:** clientes, pacientes y especies (ejemplo de referencia de la arquitectura).
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
  `error_code` en inglés. Los mensajes del paquete base todavía usan tú: pasan a voseo en la FASE 1.
* Las migraciones no llaman a Services ni Models: solo estructura (y filas por defecto con `DB::table`).
* Nombres de índices y claves foráneas: hasta 64 caracteres. Si el automático es largo, darle uno propio.
* Dinero en `decimal(14,2)`, cantidades en `decimal(12,3)`, costos en `decimal(12,4)`. Cálculos con **BCMath** sobre
  strings (`App\Support\Decimal::round`), nunca `float`. Los importes en guaraníes (PYG) se redondean a **0 decimales**
  al calcular; la columna sigue en `decimal(14,2)` para admitir otra moneda.
* Claves foráneas `restrictOnDelete()` salvo hijos que viven con el padre (`cascadeOnDelete()`).
* Operaciones sobre varios registros en `DB::transaction`; concurrencia con `lockForUpdate`. **Orden de bloqueo** (para no
  generar *deadlocks*, siempre en este sentido): agenda → turno; consulta → ítem → `product_stocks` → `product_lots`;
  timbrado → factura; factura → pago; caja → pago; documento → serie de códigos (la serie, siempre última).
* **Códigos internos** (`PAC-`, `CON-`, `SRV-`, `PRO-`, `VET-`): salen de `DocumentSequenceService::create()` (serie en
  `App\Enums\DocumentSeries`), nunca de `max(id) + 1`. Puede haber huecos: no son documentos fiscales.
* Documentos emitidos (factura emitida, pago, movimiento de inventario, documento del paciente, consulta cerrada) son
  **inmutables**: solo se anulan o se corrigen con otro registro.
* Datos maestros con `SoftDeletes`; al eliminar se agrega `//deleted_<timestamp>` a sus campos únicos para liberarlos.
  Un dato maestro ya usado no se elimina (`409 …_IN_USE`).
* **Clínica:** todo modelo de negocio usa `BelongsToClinic`. Nunca filtrar por `clinic_id` a mano ni saltear el scope sin un
  motivo registrado. Toda regla `unique` o `exists` filtra por la clínica actual. Jobs y comandos fijan la clínica con
  `CurrentClinic::run()`. Sin clínica en contexto, la consulta falla (fallo cerrado).
* **Catálogos compartidos** (`species`, `breeds`): `clinic_id` nulo para los globales. Su scope muestra "globales + los de mi
  clínica", también con fallo cerrado. Solo se crean y editan los de la propia clínica; los globales salen del seeder.
* **Usuarios:** `User` no tiene el scope de clínica, porque el login y Sanctum lo buscan antes de conocerla. Toda consulta de
  usuarios fuera del login filtra por `clinic_id` de forma explícita, y el *route binding* de un usuario de otra clínica
  responde 404.
* Rutas anidadas con `scopeBindings()`. Actualizaciones parciales con `PATCH`.
* Resources: `XResource` para listados, `ShowXResource` para detalle.
* Usuario que crea, emite o anula: columnas `created_by`, `issued_by`, `cancelled_by` + `cancelled_at` y
  `cancellation_reason`.

### Permisos

* Cada Controller implementa `HasMiddleware` con `permission:<recurso>.<acción>` por método.
* El permiso debe existir exactamente en `RolesAndPermissionsSeeder::PERMISSIONS`. El rol `administrador` pasa todo por
  `Gate::before`: probar siempre con un rol propio.
* Roles de la clínica en `RolesAndPermissionsSeeder::clinicRoles()`: `recepcionista` y `veterinario`.

### Entorno

* Zona horaria `America/Asuncion`; la API envía las fechas con hora en UTC (`Z`).
* Tests con SQLite; probar cada migración nueva también en MySQL.

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
  persona, al revés. El tipo cambia solo si el cliente no tiene facturas (`CLIENT_KIND_NOT_EDITABLE`, 422).
* Nombre para mostrar: siempre `Client::display_name`, nunca concatenando nombre y apellido.
* `document_number` y `email` únicos y obligatorios. Teléfono `+?[1-9]\d{7,14}`.
* No se elimina con pacientes, consultas o facturas (`CLIENT_IN_USE`, 409).
* La búsqueda (`search`) también encuentra al dueño por el nombre o el código de su mascota.

### 6.2 Pacientes

* Código `PAC-…`. Alta en `clients/{client}/patients` (el dueño sale de la URL).
* La raza debe ser de la especie (`PATIENT_BREED_SPECIES_MISMATCH`, 422).
* El dueño se cambia solo con `POST patients/{patient}/change-owner` (`client_id` es `prohibited` en el PATCH); el mismo
  dueño → `PATIENT_SAME_OWNER` (422). Las consultas guardan el dueño de ese momento (`consultations.client_id`).
* Paciente fallecido (`deceased_at`): solo se editan la fecha de fallecimiento y las notas (`PATIENT_DECEASED`, 422). El
  listado los oculta salvo `include_deceased=1`.
* No se elimina con consultas, aplicaciones o turnos (`PATIENT_IN_USE`, 409): se registra el fallecimiento.
* `microchip` único; al eliminar se libera con el sufijo.

### 6.3 Pendientes de diseño (reglas a definir antes de programar)

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
| 1 | Clientes y pacientes sin tests de PHPUnit |
| 2 | Modelos generados desde el esquema: revisar relaciones y casts al usarlos |
| 3 | Códigos SIFEN de formas de pago y unidades de medida sin verificar |
