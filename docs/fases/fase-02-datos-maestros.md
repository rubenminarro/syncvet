# FASE 2 — Datos maestros

Etapa: Base. Estado: **EN DESARROLLO**: diseño aprobado el 2026-11-06, con las preguntas 16 a 31 según su propuesta (R-029); entregas 2.1 (R-030 y R-031), 2.2a (R-032 y R-033), 2.2b (R-034 y R-035), 2.3 (R-036 y R-037), 2.4 (R-038 y R-039) y 2.5 (R-040 y R-041) cerradas; sigue la 2.6. El estado vigente de
cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Todo lo que las demás fases necesitan configurar: sucursales y depósitos, usuarios con sus roles y sucursales, veterinarios,
catálogo de servicios, datos y logo de la clínica, y la consulta de la auditoría. Además deja resueltas las deudas 13 (fechas con
hora, antes de la FASE 3) y 14 (archivos huérfanos).

## Entregas

El alcance inicial tenía seis entregas con los usuarios primero. El diseño pone primero las sucursales, porque los usuarios se
asignan a sucursales, y divide los usuarios en dos (R-029). Cada entrega recorre los pasos 4 a 7 y deja la suite en verde.

| Entrega | Contenido | Antes era | Estado |
|---|---|---|---|
| 2.1 | Sucursales y depósitos: ABM sin baja, sucursal y depósito por defecto, `establishment` | 2.2 | COMPLETADO (R-030, R-031) |
| 2.2a | Usuarios: alta, edición, detalle y listado; roles y sucursales; último administrador | 2.1 | COMPLETADO (R-032, R-033) |
| 2.2b | Usuarios: inactivos (migración, login, tokens), baja sin uso, sucursal actual y contraseña propia | 2.1 | COMPLETADO (R-034, R-035) |
| 2.3 | Veterinarios (`VET-`): uno por usuario, restauración, inactivación en uso | 2.3 | COMPLETADO (R-036, R-037) |
| 2.4 | Catálogo de servicios (`SRV-`): nombre fijo una vez usado, baja sin uso | 2.4 | COMPLETADO (R-038, R-039) |
| 2.5 | Datos y configuración de la clínica (`system_settings`), logo y limpieza de archivos huérfanos (deuda 14) | 2.5 | COMPLETADO (R-040, R-041) |
| 2.6 | Consulta de auditoría y fechas con hora en un lugar común (deuda 13) | 2.6 | PENDIENTE |

## Preguntas de negocio

Las tres del alcance inicial (aquí P5, P8 y P11) y las que sumó el análisis, respondidas por el usuario el 2026-11-06 (R-029).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| P1 | Usuarios inactivos: ¿`SoftDeletes` o un estado? (pregunta 5 de la FASE 0) | `users.is_active` por migración. Inactivo con la contraseña correcta → 403 "usuario inactivo"; con una incorrecta, el error genérico. Al inactivarlo se revocan sus tokens. Baja con sufijo `//deleted_` solo sin uso; con uso, 409 | 2026-11-06 |
| P2 | ¿Quién gestiona los usuarios? | Solo el administrador | 2026-11-06 |
| P3 | ¿Un usuario tiene varios roles? | Sí | 2026-11-06 |
| P4 | ¿Un administrador puede inactivarse o darse de baja, o dejar la clínica sin administradores? | No: 409 | 2026-11-06 |
| P5 | ¿Un veterinario trabaja en varias sucursales? | Sí: en las sucursales de su usuario (`branch_user`), sin una tabla aparte | 2026-11-06 |
| P6 | ¿El veterinario necesita un usuario? ¿Cómo se da de baja? | Un usuario con el rol `veterinario`. En uso se inactiva; sin uso se da de baja, y un alta del mismo usuario restaura el anterior | 2026-11-06 |
| P7 | ¿Se usa la comisión? | Se guarda; no se calcula hasta los reportes | 2026-11-06 |
| P8 | ¿Hay precios distintos por sucursal? | No: un precio único por servicio; la factura guardará su precio | 2026-11-06 |
| P9 | ¿`establishment` se edita? ¿Se eliminan sucursales? | Se edita solo sin facturas (preparado; hoy siempre). Sucursales y depósitos solo se inactivan; los por defecto, no | 2026-11-06 |
| P10 | ¿Varios depósitos por sucursal? | Sí, uno por defecto | 2026-11-06 |
| P11 | ¿Qué datos de la clínica salen en recetas y facturas? | Razón social, nombre de fantasía y RUC de la clínica; dirección y teléfono de cada sucursal. Timbrado, actividad económica y punto de expedición, en la fase de SIFEN | 2026-11-06 |
| P12 | ¿Cómo se muestra el logo antes del login? | No se muestra: va en `me` y en la configuración. Sin slug en `clinics` | 2026-11-06 |
| P13 | ¿Qué guarda `system_settings`? | Una lista cerrada de claves (Enum), con validación por clave | 2026-11-06 |
| P14 | ¿Quién ve la auditoría y qué muestra? | Solo el administrador; filtros por fecha, usuario, evento y registro; sin valores sensibles; por clínica; sin los `LOGIN_FAILED` sin clínica | 2026-11-06 |
| P15 | ¿Qué pasa con la foto de un paciente dado de baja? | Se conserva. La limpieza solo borra archivos que no sean el `photo_path` ni el logo de nadie | 2026-11-06 |
| — | Email del usuario | Único en toda la base (es el login): una persona en dos clínicas necesita dos emails en el MVP | 2026-11-06 |

Las preguntas que deja el diseño están al final, en [Preguntas del diseño](#preguntas-del-diseño).

## Regla de oro aplicada

Análisis con [`docs/project/principios.md`](../project/principios.md).

| Pregunta | Sucursal / depósito | Usuario | Veterinario | Servicio | Configuración y logo |
|---|---|---|---|---|---|
| ¿Quién la crea? | Administrador; la primera, `clinic:create` | Administrador; el primero, `clinic:create` | Administrador | Administrador | Administrador |
| ¿Quién la modifica? | Administrador | Administrador; la sucursal actual y la contraseña propia, el mismo usuario | Administrador | Administrador | Administrador |
| ¿Quién la usa? | Usuarios (sucursal actual), agenda, consultas, caja, timbrado, facturas; depósitos: stock | Todo registro con `created_by`, `issued_by`, `cancelled_by`…; la caja (`user_id`) | Agenda, consultas, ítems, aplicaciones y documentos | Agenda, turnos, ítems de consulta y, después, facturas | Recetas, documentos y comprobantes no fiscales (FASE 6); el frontend |
| Estados | Activa / inactiva; por defecto | Activo / inactivo / eliminado | Activo / inactivo / eliminado | Activo / inactivo / eliminado | — |
| ¿Qué depende de ella? | Depósitos, `branch_user`, `current_branch_id` y las tablas de las fases 3 a 9 | Veterinario, auditoría, autoría de documentos | Fases 3, 4 y 6 | Fases 3, 4 y 8 | — |
| Impacto económico | `establishment` numera las facturas (SIFEN): no cambia con facturas | Ninguno directo | Comisión (sin cálculo en el MVP) | Precio y tasa de IVA de lo que se factura | Ninguno |
| ¿Qué pasa si se cancela? | Se inactiva: sale de las asignaciones y de la sucursal actual | Se inactiva: no entra y pierde sus tokens | Se inactiva: no se asigna en las fases siguientes | Se inactiva: no se ofrece en altas nuevas | — |
| ¿Qué pasa si se elimina? | No se elimina | Solo sin uso; el email queda libre (sufijo) | Solo sin uso; un alta del mismo usuario lo restaura | Solo sin uso; el nombre queda libre (sufijo) | El logo se borra; la configuración no se elimina |
| Permisos | `branches.*`, `warehouses.*` (existen) | `users.*`, `roles.index` (existen) | `veterinarians.*` (existen) | `services.*` (existen) | `system-settings.update` (existe) y cuatro nuevos |
| Consulta, stock, facturación | La consulta y la factura guardan su sucursal; el stock vive en el depósito | La consulta guarda quién la cerró | La consulta guarda su veterinario | La consulta y la factura guardan su precio | La factura lee los datos de la clínica al emitirse |

## Decisiones comunes

| Tema | Decisión |
|---|---|
| Permisos | Los de la FASE 2 ya existen en `RolesAndPermissionsSeeder::PERMISSIONS`, salvo los cuatro de la configuración y el logo (2.5): **123 → 127**. Lo administra el administrador (`Gate::before`); los roles de la clínica suman solo lecturas (pregunta 19 y 2.5) |
| Rutas | Hoy 26. Suman 7 (2.1), 5 (2.2a), 3 (2.2b), 5 (2.3), 5 (2.4), 5 (2.5) y 2 (2.6): **58** al cerrar la fase (con `PATCH me/password`, pregunta 20) |
| Migraciones | Dos nuevas: `users.is_active` (2.2b) y `veterinarians.license_number` a 60 caracteres (2.3). **32 → 34** |
| Bajas | Sufijo `//deleted_<id>_<timestamp>` (`DeletedSuffix`) en los únicos de usuarios (email), veterinarios (matrícula) y servicios (nombre). El largo que se valida deja lugar al sufijo entero (30 caracteres con ids de hasta 9 dígitos) |
| Nombres únicos | Sucursal y servicio: sin mayúsculas, acentos ni espacios repetidos (`CatalogName`), comparados en la aplicación con la fila de la clínica bloqueada (`CatalogLock`), como especies y razas |
| Listados | Form Request con los filtros de `ListFilters` (`search` literal con `whereContains`, `per_page`, `page`) y los propios de cada recurso |
| Resources | `XResource` para listados y `ShowXResource` para el detalle; siempre con `ApiResponse` (salvo la descarga del logo) |
| Auditoría | Los modelos nuevos o tocados (`Branch`, `Warehouse`, `User`, `Veterinarian`, `Service`, `SystemSetting`, `Clinic`) usan `Auditable`. Los cambios de roles quedan como `ROLES_SYNCED` |
| Deuda 2 | Cada entrega revisa las relaciones y los casts del modelo que usa (`Branch`, `Warehouse`, `User`, `Veterinarian`, `Service`, `AuditLog`) y lo anota en su resultado |
| Deuda 5 | No se agregan claves foráneas compuestas: cada referencia nueva (`branch_user`, `warehouses.branch_id`, `veterinarians.user_id`, `audit_logs.user_id` en los filtros) se valida con `exists` por clínica y tiene su test de aislamiento (`tests/Feature/Tenancy`) |
| Orden de bloqueo | Se suman a `CLAUDE.md` §3: clínica → sucursal → depósito; clínica → usuario → veterinario → serie; clínica → servicio → serie; clínica → configuración |

## Diseño por entrega

### 2.1 Sucursales y depósitos

**Alcance.** ABM de sucursales y de sus depósitos, sin baja (P9). La sucursal `SUC-01` y el depósito `DEP-01` que crea
`clinic:create` son los por defecto.

**Migraciones:** ninguna (`branches`, `warehouses` y `branch_user` existen).

**Reglas.**

| Regla | Detalle |
|---|---|
| Código | Lo escribe el administrador (pregunta 16): obligatorio, hasta 20, `^[A-Z0-9-]+$` (se guarda en mayúsculas), único en la clínica, editable. Igual en depósitos |
| Nombre | Obligatorio, 2 a 100, único en la clínica con `CatalogName` (sucursales); en depósitos, único dentro de su sucursal |
| `establishment` | Tres dígitos (`^\d{3}$`, de `001` a `999`), único en la clínica. Se edita solo si la sucursal no tiene facturas (`invoices.branch_id`): si tiene, 422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`. En esta fase la API no crea facturas: se prueba con una fila insertada por el helper de tests (P9) |
| Dirección y teléfono | `address` hasta 255; `phone` con la regla de clientes (`+?[1-9]\d{7,14}`), opcionales (P11) |
| Sucursal por defecto | Siempre una por clínica. `is_default: true` en otra sucursal (activa) la pasa a ser la por defecto y la anterior deja de serlo, en la misma transacción. `is_default: false` se rechaza (422 en `is_default`: "Siempre tiene que haber una sucursal por defecto: marcá otra."). Una inactiva no puede ser la por defecto (422) |
| Inactivar | `is_active: false`. La por defecto no: 409 `BRANCH_DEFAULT_NOT_DEACTIVATABLE`. Una sucursal inactiva no se asigna a usuarios ni se elige como actual; los usuarios que la tenían como actual quedan sin sucursal actual (pregunta 18). Sus depósitos no cambian |
| Alta de sucursal | Crea en la misma transacción su depósito por defecto, con `warehouse_code` (obligatorio) y `warehouse_name` ("Principal" si no llega) (pregunta 17). Al administrador que la crea no se le asigna sola |
| Depósito | Alta anidada en la sucursal (la sucursal sale de la URL; `branch_id` en el cuerpo se ignora) y edición plana; `branch_id` `prohibited` al editar. Uno por defecto en cada sucursal, con las mismas reglas que la sucursal (cambiar, no quitar, no inactivar: 409 `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`) |
| Listados | Activas por defecto; `include_inactive=1` trae todas. `search` en código y nombre |

**Endpoints** (todos con `auth:sanctum` y `clinic`):

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `branches` | `branches.index` | `IndexBranchRequest`: `ListFilters`, `include_inactive` | 200, `BranchResource` paginado | 422 |
| POST | `branches` | `branches.store` | `StoreBranchRequest`: `code`, `name`, `establishment`, `address`, `phone`, `is_default`, `warehouse_code`, `warehouse_name` | 201, `ShowBranchResource` con sus depósitos | 422 |
| GET | `branches/{branch}` | `branches.show` | — | 200, `ShowBranchResource` | 404 |
| PATCH/PUT | `branches/{branch}` | `branches.update` | `UpdateBranchRequest` (`sometimes`) | 200 | 404; 409 `BRANCH_DEFAULT_NOT_DEACTIVATABLE`; 422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`; 422 |
| GET | `branches/{branch}/warehouses` | `branches.warehouses.index` → `warehouses.index` | `IndexWarehouseRequest` | 200, `WarehouseResource` | 404 |
| POST | `branches/{branch}/warehouses` | `branches.warehouses.store` → `warehouses.store` | `StoreWarehouseRequest`: `code`, `name`, `is_default` | 201 | 404; 422 |
| PATCH/PUT | `warehouses/{warehouse}` | `warehouses.update` | `UpdateWarehouseRequest`; `branch_id` `prohibited` | 200 | 404; 409 `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`; 422 |

Las rutas anidadas siguen la regla de los dos últimos tramos: no necesitan excepción en `ROUTES_WITH_ANOTHER_PERMISSION`.

**Errores** (`BranchException`, nueva, para sucursales y depósitos):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `BRANCH_DEFAULT_NOT_DEACTIVATABLE` | 409 | La sucursal por defecto no se puede inactivar: marcá otra como sucursal por defecto primero. |
| `BRANCH_ESTABLISHMENT_NOT_EDITABLE` | 422 | El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas. |
| `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE` | 409 | El depósito por defecto no se puede inactivar: marcá otro como depósito por defecto primero. |

**Concurrencia.** Alta y edición en `DB::transaction`: primero la fila de la clínica (`CatalogLock`, nombres y sucursal por
defecto), después la sucursal (`lockForUpdate`, depósito por defecto). Orden nuevo: clínica → sucursal → depósito.

**Tests** (`tests/Feature/Branches`, con un rol de test con `branches.*` y `warehouses.*`; el `recepcionista` para el 403 de escritura):

| Test | Qué verifica |
|---|---|
| `BranchStoreTest` | Alta con su depósito por defecto; código en mayúsculas y único; nombre repetido con otras mayúsculas o acentos → 422; `establishment` mal formado o repetido → 422; teléfono inválido → 422 |
| `BranchUpdateTest` | Cambio de la por defecto (la anterior deja de serlo); `is_default: false` → 422; inactivar la por defecto → 409; inactiva como por defecto → 422; `establishment` con facturas → 422 y sin facturas → 200; usuarios con la sucursal actual inactivada quedan sin sucursal actual |
| `WarehouseTest` | Alta anidada (un `branch_id` en el cuerpo se ignora), uno por defecto por sucursal, no se inactiva el por defecto, `branch_id` al editar → 422, depósito de otra clínica → 404 |
| `BranchIndexTest` | Activas por defecto, `include_inactive`, `search`, paginación; no trae las de otra clínica |
| Acceso y aislamiento | Las rutas nuevas en `tests/Feature/Access` (por rol) y una sucursal de otra clínica → 404 en cada ruta |

**Mutaciones críticas:** quitar el chequeo de la por defecto al inactivar; quitar el paso de `is_default` a la anterior; quitar el
chequeo de facturas de `establishment`; quitar el bloqueo de la clínica en el alta (test con `for update` en MySQL, como
`CatalogLockTest`); quitar el filtro de clínica del `exists` de la sucursal.

**Postman:** `syncvet-fase-02-sucursales` (alta con depósito, cambio de la por defecto, inactivar la por defecto → 409, depósitos), repetible con su rango de corrida.

### 2.2a Usuarios: ABM, roles y sucursales

**Alcance.** Alta, edición, detalle y listado de usuarios de la clínica, con sus roles y sucursales. Solo el administrador (P2).

**Migraciones:** ninguna.

**Reglas.**

| Regla | Detalle |
|---|---|
| Datos | `first_name` y `last_name` obligatorios (2 a 100); `name` lo arma el Service (`first_name last_name`), como `ClinicService` |
| Email | Obligatorio, formato email, solo ASCII (como el login), en minúsculas, hasta 150 (deja lugar al sufijo de baja en la columna de 255), **único en toda la base** contando eliminados (R-029): 422 "Ya hay un usuario con ese email." |
| Contraseña | Obligatoria en el alta, opcional al editar: `UserPassword` (R-009), con `#[\SensitiveParameter]`. Un cambio de contraseña por el administrador revoca los tokens del usuario (pregunta 20) |
| Roles | `roles`: lista de 1 o más de `administrador`, `recepcionista` y `veterinario` (P3), sin repetidos. Se sincronizan con `syncRoles` y quedan en la auditoría como `ROLES_SYNCED` |
| Sucursales | `branch_ids`: lista de 1 o más sucursales activas de la clínica (`exists` por clínica). Al editar se acepta también una que el usuario ya tiene aunque se haya inactivado |
| Sucursal actual | Al crear: la por defecto si está en `branch_ids`, si no la primera. Al editar, si la actual sale de `branch_ids`, pasa a ser la misma regla |
| Último administrador (P4) | Quitarle el rol `administrador` al último administrador activo de la clínica → 409 `USER_LAST_ADMIN` |
| `clinic_id` | No se recibe: lo fija el Service con la clínica en contexto |
| Listado | `search` en nombre, apellido y email; filtros `role` y `branch_id` |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `users` | `users.index` | `IndexUserRequest`: `ListFilters`, `role`, `branch_id` | 200, `UserResource` | 422 |
| POST | `users` | `users.store` | `StoreUserRequest`: `first_name`, `last_name`, `email`, `password`, `roles[]`, `branch_ids[]` | 201, `ShowUserResource` | 422 |
| GET | `users/{user}` | `users.show` | — | 200, `ShowUserResource` | 404 (también de otra clínica) |
| PATCH/PUT | `users/{user}` | `users.update` | `UpdateUserRequest` (`sometimes`) | 200 | 404; 409 `USER_LAST_ADMIN`; 422 |
| GET | `roles` | `roles.index` | — | 200, `RoleResource` (`name`, `label`), sin paginar | — |

`UserResource`: `id`, `first_name`, `last_name`, `name`, `email`, `roles`, `branches` (`id`, `code`, `name`), `current_branch_id`.
`ShowUserResource` suma `veterinarian` (desde la 2.3) y las fechas. Nunca `password` ni `remember_token`. Con la 2.2b suman
`is_active`.

**Errores** (`UserException`, nueva):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `USER_LAST_ADMIN` | 409 | La clínica tiene que tener al menos un administrador activo. |

**Concurrencia.** Alta y edición en `DB::transaction`; la edición que toca los roles bloquea la fila de la clínica antes de contar
los administradores activos (dos pedidos simultáneos no dejan la clínica sin administrador). Orden: clínica → usuario.

**Tests** (`tests/Feature/Users`, con el administrador; el `recepcionista` y el `veterinario` para el 403):

| Test | Qué verifica |
|---|---|
| `UserStoreTest` | Alta con roles y sucursales; email en minúsculas; email con acentos → 422; email de otra clínica o de un usuario eliminado → 422; contraseña débil o filtrada → 422; sucursal de otra clínica o inactiva → 422; rol inexistente → 422; sucursal actual según la regla; la contraseña no sale en la respuesta ni en la auditoría |
| `UserUpdateTest` | Cambio de roles (`ROLES_SYNCED`); quitar el rol al último administrador → 409; con otro administrador activo → 200; cambio de sucursales mueve la actual; cambio de contraseña revoca los tokens del usuario |
| `UserIndexTest` | `search`, `role`, `branch_id`, paginación; no trae usuarios de otra clínica |
| `RolesIndexTest` | Los tres roles con su nombre en español |
| Acceso y aislamiento | Usuario de otra clínica → 404 en cada ruta |

**Mutaciones críticas:** quitar el chequeo del último administrador; quitar el bloqueo de la clínica al contar administradores;
quitar `withTrashed()` del email único; quitar el filtro de clínica del `exists` de las sucursales; quitar la revocación de tokens al
cambiar la contraseña.

**Postman:** `syncvet-fase-02-usuarios` (con la 2.2b), repetible: emails únicos por corrida.

### 2.2b Usuarios: inactivos, baja, sucursal actual y contraseña propia

**Alcance.** P1 y P4, la sucursal actual y el cambio de la contraseña propia (pregunta 20).

**Migración nueva** `2026_11_xx_000000_add_is_active_to_users`: `is_active` boolean, por defecto `true`, después de `password`.
Probada en MySQL: subir, bajar y subir.

**Reglas.**

| Regla | Detalle |
|---|---|
| Inactivar | `is_active` en `UpdateUserRequest`. Al pasar a `false`, el modelo revoca todos los tokens del usuario (como al eliminarlo). Reactivar no devuelve tokens |
| Login de un inactivo | Contraseña correcta → 403 `USER_INACTIVE` "Tu usuario está inactivo: consultá con el administrador de la clínica.", `LOGIN_FAILED` con el motivo "usuario inactivo" y no cuenta como intento fallido (como la clínica suspendida). Contraseña incorrecta → 422 `INVALID_CREDENTIALS`, como siempre: no revela que la cuenta existe. Se revisa después de la clínica suspendida |
| Token de un inactivo | Defensa en profundidad: `SetCurrentClinic` responde 403 `USER_INACTIVE` si el usuario está inactivo (por ejemplo, un token creado justo antes de inactivarlo). `logout` sigue funcionando |
| A sí mismo (P4) | El administrador no se inactiva ni se da de baja a sí mismo: 409 `USER_SELF_ACTION` |
| Último administrador (P4) | Inactivar o dar de baja al último administrador activo → 409 `USER_LAST_ADMIN` |
| Baja | `DELETE users/{user}`, solo sin uso (P1). Uso: una ficha de veterinario (también eliminada) o cualquier referencia en `created_by`, `issued_by`, `cancelled_by`, `closed_by`, `uploaded_by` o `cash_registers.user_id` de las tablas de negocio (lista en `UserService::USAGE`, con un test que exige que cubra toda clave foránea a `users`, salvo `audit_logs` y `branch_user`). Con uso → 409 `USER_IN_USE`. Al eliminar: sufijo en el email, tokens revocados (evento `deleted`, ya existe) y `branch_user` se conserva |
| Sucursal actual | `PATCH me/current-branch` con `branch_id`: una sucursal activa asignada al usuario (si no, 422 en `branch_id`). Responde `MeResource` |
| Contraseña propia | `PATCH me/password` con `current_password` (correcta, si no 422 en `current_password`) y `password` (`UserPassword`, distinta de la actual). Revoca los demás tokens del usuario y conserva el actual |
| Listado | `UserResource` suma `is_active`; `IndexUserRequest` suma `include_inactive` (activos por defecto) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| DELETE | `users/{user}` | `users.destroy` | — | 200 | 404; 409 `USER_IN_USE`, `USER_SELF_ACTION`, `USER_LAST_ADMIN` |
| PATCH | `me/current-branch` | `auth.current-branch`, sin permiso (`ROUTES_WITHOUT_PERMISSION`: cualquier usuario autenticado elige su sucursal) | `UpdateCurrentBranchRequest`: `branch_id` | 200, `MeResource` | 401; 403 `USER_INACTIVE`; 422 |
| PATCH | `me/password` | `auth.password`, sin permiso (`ROUTES_WITHOUT_PERMISSION`) | `UpdateOwnPasswordRequest`: `current_password`, `password` | 200 | 401; 403; 422 |

Las dos rutas de `me` llevan `auth:sanctum` y `clinic`.

**Errores:**

| `error_code` | HTTP | Excepción | Mensaje |
|---|---|---|---|
| `USER_INACTIVE` | 403 | `AuthException` | Tu usuario está inactivo: consultá con el administrador de la clínica. |
| `USER_SELF_ACTION` | 409 | `UserException` | No podés inactivarte ni darte de baja a vos mismo. |
| `USER_IN_USE` | 409 | `UserException` | El usuario tiene registros a su nombre y no se puede eliminar: inactivalo. |
| `USER_LAST_ADMIN` | 409 | `UserException` | La clínica tiene que tener al menos un administrador activo. |

**Concurrencia.** Inactivar, quitar el rol y dar de baja bloquean la fila de la clínica antes de contar los administradores
activos, y después la del usuario. La baja revisa el uso dentro de su transacción.

**Tests** (`tests/Feature/Users` y `tests/Feature/Auth`):

| Test | Qué verifica |
|---|---|
| `UserDeactivateTest` | Inactivar revoca todos los tokens (el token viejo da 401); reactivar; a sí mismo → 409; al último administrador → 409; listado sin inactivos salvo `include_inactive=1` |
| `LoginInactiveUserTest` | Inactivo con la contraseña correcta → 403 `USER_INACTIVE`, sin token y con `LOGIN_FAILED`; con una incorrecta → 422 `INVALID_CREDENTIALS`; no cuenta para el límite de intentos |
| `InactiveUserTokenTest` | Un token vigente de un usuario inactivado sin revocar (fila editada a mano) → 403 en una ruta con `clinic`; `logout` funciona |
| `UserDeleteTest` | Sin uso → 200, email libre para un alta nueva; con ficha de veterinario o con una fila con `created_by` → 409; a sí mismo → 409; `UserService::USAGE` cubre toda clave foránea a `users` |
| `CurrentBranchTest` | Sucursal asignada y activa → 200 con `MeResource`; no asignada, inactiva o de otra clínica → 422 |
| `OwnPasswordTest` | Contraseña actual incorrecta → 422; nueva débil → 422; cambio correcto revoca los otros tokens y conserva el actual |

**Mutaciones críticas:** quitar la revocación de tokens al inactivar; quitar el chequeo de `is_active` del login (o ponerlo antes
de verificar la contraseña); quitar el chequeo de `SetCurrentClinic`; quitar el chequeo de uso de la baja; quitar el chequeo de
"a sí mismo".

**Postman:** la colección de usuarios suma inactivar, el login del inactivo (403 y 422), la sucursal actual y la contraseña propia.

### 2.3 Veterinarios

**Alcance.** ABM de veterinarios con código `VET-` (P5 a P7).

**Migración nueva** `2026_11_xx_000001_widen_veterinarians_license_number`: `license_number` de 30 a 60 caracteres, para el sufijo
de baja (pregunta 29). Se valida hasta 30.

**Reglas.**

| Regla | Detalle |
|---|---|
| Usuario | `user_id` obligatorio en el alta: un usuario activo de la clínica con el rol `veterinario` (P6). Si ya tiene una ficha no eliminada → 422 en `user_id` "Ese usuario ya es veterinario." `user_id` `prohibited` al editar |
| Restauración (P6) | Si el usuario tiene una ficha eliminada, el alta la restaura con los datos del pedido y conserva su código `VET-`: 201, igual que un alta |
| Código | `VET-…` de `DocumentSequenceService` (`DocumentSeries::VETERINARIAN`); `code` en el cuerpo → 422 (`prohibited`), como en pacientes |
| Datos | `license_number` hasta 30, opcional, única en la clínica; `specialty` hasta 100; `commission_percentage` de 0 a 100 con hasta 2 decimales, sin cálculos (P7); `notes` hasta 1000; `status` `active` o `inactive` (`VeterinarianStatus`) |
| Sucursales (P5) | Las de su usuario: la respuesta las muestra y el listado filtra por `branch_id` |
| Baja | Solo sin uso: `schedules`, `appointments`, `consultations`, `consultation_items`, `patient_applications` y `patient_documents` (también eliminados). Con uso → 409 `VETERINARIAN_IN_USE`. Al eliminar, sufijo en `license_number` |
| Rol del usuario | Quitarle el rol `veterinario` (2.2a) a un usuario con una ficha no eliminada → 409 `USER_IS_VETERINARIAN` |
| Usuario inactivo | La ficha no cambia; la respuesta muestra `user.is_active` (las fases 3 y 4 no lo asignan) |
| Listado | `search` en código, nombre del usuario y matrícula; filtros `status` y `branch_id`; activos por defecto |

**Endpoints** (`apiResource('veterinarians')`):

| Método | Ruta | Nombre y permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `veterinarians` | `veterinarians.index` | 200, `VeterinarianResource` | 422 |
| POST | `veterinarians` | `veterinarians.store` | 201, `ShowVeterinarianResource` | 422 |
| GET | `veterinarians/{veterinarian}` | `veterinarians.show` | 200 | 404 |
| PATCH/PUT | `veterinarians/{veterinarian}` | `veterinarians.update` | 200 | 404; 422 |
| DELETE | `veterinarians/{veterinarian}` | `veterinarians.destroy` | 200 | 404; 409 `VETERINARIAN_IN_USE` |

`MeResource` ya muestra `veterinarian` (`id`, `code`): sin cambios.

**Errores:**

| `error_code` | HTTP | Excepción | Mensaje |
|---|---|---|---|
| `VETERINARIAN_IN_USE` | 409 | `VeterinarianException` | El veterinario tiene registros y no se puede eliminar: inactivalo. |
| `USER_IS_VETERINARIAN` | 409 | `UserException` | El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol. |

**Concurrencia.** Alta y restauración bloquean la fila del usuario (`lockForUpdate`) antes de buscar su ficha (también eliminada);
la serie va última. Quitar el rol `veterinario` bloquea la misma fila. Orden: usuario → veterinario → serie. La baja revisa el uso
con la ficha bloqueada; las fases 3 y 4 tomarán un `sharedLock` sobre el veterinario al usarlo, como el paciente con la especie.

**Tests** (`tests/Feature/Veterinarians`):

| Test | Qué verifica |
|---|---|
| `VeterinarianStoreTest` | Alta con `VET-` correlativo; usuario sin el rol, inactivo, de otra clínica o que ya es veterinario → 422; `code` → 422; matrícula repetida → 422; comisión fuera de rango → 422 |
| `VeterinarianRestoreTest` | Alta de un usuario con ficha eliminada: misma fila y mismo código, datos nuevos, `RESTORED` en la auditoría |
| `VeterinarianDeleteTest` | Sin uso → 200 y matrícula libre; con un turno, consulta o aplicación (filas del helper) → 409 |
| `VeterinarianUpdateTest` | Inactivar en uso; `user_id` → 422; quitar el rol `veterinario` al usuario → 409 |
| `VeterinarianIndexTest` | `search`, `status`, `branch_id` por las sucursales del usuario; la recepción lo lista (permiso existente) |

**Mutaciones críticas:** quitar la restauración (crear siempre); quitar el chequeo del rol; quitar el chequeo de uso; quitar el
bloqueo del usuario (test con `for update` en MySQL); quitar el chequeo de `USER_IS_VETERINARIAN`.

**Postman:** `syncvet-fase-02-veterinarios` (crea su usuario veterinario en la corrida, alta, baja, restauración).

### 2.4 Servicios

**Alcance.** Catálogo de servicios con código `SRV-` (P8).

**Migraciones:** ninguna (`services.name` de 150 admite 120 más el sufijo).

**Reglas.**

| Regla | Detalle |
|---|---|
| Código | `SRV-…` de `DocumentSequenceService` (`DocumentSeries::SERVICE`); `code` → 422 (`prohibited`) |
| Nombre | Obligatorio, 2 a 120, único en la clínica con `CatalogName` frente a los no eliminados (el índice `(clinic_id, name)` lo refuerza). **Un servicio usado no cambia de nombre**: 422 `SERVICE_NAME_NOT_EDITABLE` (uso: `schedules`, `appointments` y `consultation_items`) |
| Precio (P8) | `standard_price` único, de 0 a 999.999.999.999, **sin decimales** mientras la moneda sea PYG (pregunta 21). Se edita siempre: lo usado guarda su precio |
| IVA | `tax_rate`: 0, 5 o 10 (`TaxRate`), 10 por defecto |
| Otros | `category` (`ServiceCategory`), `description` hasta 1000, `duration_minutes` de 5 a 720 en múltiplos de 5 (pregunta 22), `status` (`ServiceStatus`) |
| Baja | Solo sin uso (mismas tablas, también eliminados) → si no, 409 `SERVICE_IN_USE`. Sufijo en el nombre |
| Listado | `search` en código y nombre; filtros `category` y `status`; activos por defecto |

**Endpoints** (`apiResource('services')`): `services.index`, `services.store`, `services.show`, `services.update` y
`services.destroy`, con `ServiceResource` y `ShowServiceResource`. Errores: 404; 409 `SERVICE_IN_USE`; 422 `SERVICE_NAME_NOT_EDITABLE`;
422 `VALIDATION_ERROR`. La recepción y el veterinario listan y ven (permisos existentes).

**Errores** (`ServiceException`, existente):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `SERVICE_NAME_NOT_EDITABLE` | 422 | El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este. |
| `SERVICE_IN_USE` | 409 | El servicio ya se usó y no se puede eliminar: inactivalo. |

**Concurrencia.** Alta y cambio de nombre bloquean la clínica (`CatalogLock`); la edición y la baja bloquean el servicio antes de
revisar su uso; la serie va última. Las fases 3 y 4 tomarán un `sharedLock` sobre el servicio al usarlo.

**Tests** (`tests/Feature/Services`): alta con `SRV-`; nombre repetido con otras mayúsculas o acentos → 422; precio con decimales,
negativo o enorme → 422; duración fuera de rango → 422; cambio de nombre usado → 422 y sin uso → 200; cambio de precio usado → 200;
baja con uso → 409 y sin uso libera el nombre; filtros y paginación; 403 para la recepción al escribir.

**Mutaciones críticas:** quitar el chequeo del nombre usado; quitar el chequeo de uso de la baja; quitar el sufijo de baja; quitar
la regla de enteros del precio; quitar el bloqueo de la clínica en el alta.

**Postman:** `syncvet-fase-02-servicios`.

### 2.5 Configuración de la clínica, logo y archivos huérfanos (deuda 14)

**Alcance.** Datos de la clínica (P11), configuración en `system_settings` (P13), logo (P12) y el comando de limpieza (P15).

**Migraciones:** ninguna (`clinics` y `system_settings` existen). Modelo nuevo `SystemSetting` (`BelongsToClinic`, `Auditable`).

**Reglas.**

| Regla | Detalle |
|---|---|
| Datos de la clínica | `name` (nombre de fantasía, 2 a 100) y `legal_name` (razón social, 2 a 150), editables. El RUC se muestra pero no se edita por la API (pregunta 24) |
| Claves (P13) | Enum `SystemSettingKey` con su regla por clave (pregunta 23): `contact_email` (email, hasta 150), `contact_phone` (la regla de teléfonos), `website` (URL `https`, hasta 150) y `document_footer` (texto, hasta 500). Una clave fuera de la lista → 422. `null` borra el valor |
| Logo (P12) | JPG, PNG o WEBP de hasta 2 MB por el contenido, vuelto a codificar con `ImageReencoder` (sin EXIF), hasta 25 megapíxeles; más grande → 413 `PAYLOAD_TOO_LARGE` (pregunta 25). Disco propio `clinic_logos` (`storage/app/private/clinic-logos`, sin `serve`), en `clinics/{clinic_id}/logo/{uuid}.{ext}`. La ruta va en la clave interna `logo_path` (fuera de la lista editable) y nunca sale de la API: `has_logo` en la configuración y en `me` (`clinic.has_logo`) |
| Reemplazo y archivo ajeno | Las reglas de la foto del paciente (§6.2): se borra el anterior después del commit; si la transacción falla, el nuevo; una ruta fuera de la carpeta de la clínica o sin archivo → 404 `CLINIC_LOGO_NOT_FOUND` al verlo y un aviso en el log. Borrarlo con una ruta fuera de la carpeta limpia la referencia (200) sin tocar ningún archivo (desvío 3, decidido por el usuario en R-041) |
| Reutilización | Lo común con `PatientPhotoService` (guardar, reemplazar después del commit, validar la ruta, descargar con `nosniff`, CSP y `no-store`) pasa a una clase compartida (`PrivateImageStore`); los tests de la foto no cambian |
| Limpieza (deuda 14, P15) | Comando `files:prune-orphans` con `--dry-run`: por cada clínica (`CurrentClinic::run`) borra los archivos de las carpetas de fotos y del logo que no sean el `photo_path` de un paciente (también eliminado) ni el `logo_path` de la clínica, y que tengan más de 24 horas (protege una subida en curso; pregunta 26). Informa y registra en el log lo borrado. Programado a diario a las 03:00 en `routes/console.php`; en producción necesita el cron del *scheduler* (FASE 10, despliegue) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `system-settings` | `system-settings.show` (**nuevo**) | — | 200, `SystemSettingsResource`: `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (todas las claves, `null` si no tienen valor), `has_logo` | — |
| PATCH/PUT | `system-settings` | `system-settings.update` | `UpdateSystemSettingsRequest`: `name`, `legal_name`, `settings.<clave>` (`sometimes`); `ruc` y `ruc_dv` `prohibited` | 200 | 422 |
| POST | `system-settings/logo` | `system-settings.upload-logo` (**nuevo**) | `UploadClinicLogoRequest` (`multipart/form-data`, `logo`) | 200, `SystemSettingsResource` | 413; 422 |
| GET | `system-settings/logo` | `system-settings.show-logo` (**nuevo**) | — | 200, el archivo (como la foto) | 404 `CLINIC_LOGO_NOT_FOUND` |
| DELETE | `system-settings/logo` | `system-settings.delete-logo` (**nuevo**) | — | 200 | 404 `CLINIC_LOGO_NOT_FOUND` |

**Permisos:** cuatro nuevos (123 → 127). `recepcionista` y `veterinario` suman `system-settings.show` y
`system-settings.show-logo` (los documentos y recibos llevan los datos y el logo). `CLAUDE.md` §4 suma la descarga del logo a la
excepción de `ApiResponse`.

**Errores** (`ClinicException`, existente):

| `error_code` | HTTP | Mensaje |
|---|---|---|
| `CLINIC_LOGO_NOT_FOUND` | 404 | La clínica no tiene logo. |

**Concurrencia.** Editar la configuración y subir o borrar el logo bloquean la fila de la clínica antes de leer `logo_path` o las
claves. Orden: clínica → configuración.

**Tests** (`tests/Feature/Settings`, con `Storage::fake`):

| Test | Qué verifica |
|---|---|
| `SystemSettingsTest` | Lectura por los tres roles; edición por el administrador y 403 para los demás; clave desconocida → 422; regla de cada clave; `null` borra; `ruc` → 422; auditoría del cambio |
| `ClinicLogoTest` | Subida de los tres tipos, sin EXIF; texto con extensión `.png` o SVG → 422; más de 2 MB → 413; reemplazo borra el anterior después del commit; descarga con sus cabeceras; sin logo → 404; ruta ajena → 404 sin borrar; `has_logo` en `me` y en la configuración; la ruta no sale de la API |
| `PruneOrphanFilesTest` | Borra los huérfanos viejos de fotos y logo; conserva el `photo_path` de un paciente eliminado, el logo vigente y los archivos de menos de 24 horas; `--dry-run` no borra; no toca archivos de otra clínica fuera de su carpeta |
| `PatientPhotoTest` | Sin cambios, en verde con `PrivateImageStore` |

**Mutaciones críticas:** quitar la validación de la ruta del logo; quitar el umbral de 24 horas; no contar los pacientes eliminados
en la limpieza; quitar el bloqueo de la clínica al reemplazar el logo; aceptar una clave fuera de la lista.

**Postman:** `syncvet-fase-02-configuracion` con dos archivos en `docs/testing/postman/archivos`.

### 2.6 Auditoría (y deuda 13)

**Alcance.** Consulta de `audit_logs` por el administrador (P14) y la conversión de las fechas con hora en un lugar común (deuda 13).

**Migraciones:** ninguna: los índices `(clinic_id, created_at)`, `(clinic_id, event, created_at)`, `(user_id, created_at)` y
`(auditable_type, auditable_id)` cubren los filtros.

**Reglas.**

| Regla | Detalle |
|---|---|
| Clínica | `AuditLog` ya tiene el `ClinicScope` (corrige el análisis, que decía lo contrario): el listado y el *route binding* solo ven la clínica en contexto, y los `LOGIN_FAILED` sin clínica (`clinic_id` nulo) nunca aparecen. Un test lo exige |
| Filtros | `from` y `to` (fecha y hora; `to` no anterior a `from`), `user_id` (de la clínica, también eliminado), `event` (`AuditEvent`), `auditable_type` (lista cerrada: los tipos de los modelos con `Auditable`), `auditable_id` (exige `auditable_type`) y `search` literal en el nombre o el email del usuario; más `per_page` y `page` |
| Orden | `created_at` y `id` descendentes |
| Valores sensibles | `AuditLogger` ya enmascara `password`, `remember_token` y `token`. La ruta del logo ya no llega a la auditoría: `SystemSetting::auditValues()` guarda `"(archivo)"` (R-041), y la foto del paciente hace lo mismo con `photo_path` desde la 2.6. El Resource del detalle además oculta `photo_path` en los registros anteriores que ya tienen la ruta (rutas internas). El listado no trae `old_values` ni `new_values`: solo el detalle (pregunta 27) |
| Inmutable | Sin rutas de escritura; el modelo ya rechaza modificar y eliminar (`AUDIT_LOG_IMMUTABLE`) |
| Deuda 13 | Cast propio `App\Casts\AppDateTime`: al guardar convierte un valor con zona (`Z` o desplazamiento) a `America/Asuncion`; sin zona, se toma como hora de Asunción (como hoy, pregunta 28). Lo usan `Patient::deceased_at` (se quita la conversión de `PatientService`) y todo atributo con hora asignable de los modelos generados (`Appointment`, `Consultation`, `ScheduleBlock`…). Los filtros con hora (`from` y `to`, y el `after()` de `UpdatePatientRequest`) usan el mismo `App\Support\ApiDateTime::toLocal()`. Un test recorre los modelos y exige el cast en todo atributo de `$fillable` con `datetime`. La salida sigue en UTC (`Z`) |

**Endpoints:**

| Método | Ruta | Nombre y permiso | Request | Respuesta | Errores |
|---|---|---|---|---|---|
| GET | `audit-logs` | `audit-logs.index` | `IndexAuditLogRequest` | 200, `AuditLogResource`: `id`, `event` y su `label`, `auditable_type`, `auditable_id`, `user` (`id`, `name`), `ip_address`, `created_at` | 422 |
| GET | `audit-logs/{auditLog}` | `audit-logs.show` | — | 200, `ShowAuditLogResource`: suma `old_values`, `new_values` y `user_agent` | 404 (también de otra clínica o sin clínica) |

Solo el administrador (P14): ningún rol de la clínica recibe estos permisos.

**Errores:** solo los genéricos (`VALIDATION_ERROR`, `RESOURCE_NOT_FOUND`, `FORBIDDEN`).

**Tests** (`tests/Feature/Audit` y `tests/Unit`):

| Test | Qué verifica |
|---|---|
| `AuditLogIndexTest` | Cada filtro; `to` antes de `from` → 422; `auditable_id` sin tipo → 422; tipo fuera de la lista → 422; orden; paginación; no trae registros de otra clínica ni `LOGIN_FAILED` sin clínica; el recepcionista y el veterinario → 403 |
| `AuditLogShowTest` | Detalle con valores; contraseña enmascarada; sin `photo_path` ni `logo_path`; de otra clínica o sin clínica → 404 |
| `AppDateTimeTest` | `Z`, `-03:00` y sin zona guardan la hora correcta de Asunción, en SQLite y MySQL; `deceased_at` sigue igual de punta a punta |
| `DateTimeCastCoverageTest` | Todo atributo con hora de `$fillable` usa `AppDateTime` |

**Mutaciones críticas:** quitar el `ClinicScope` del listado (con `withoutGlobalScopes`); quitar el ocultamiento de `photo_path`;
volver a `datetime` en `deceased_at`; quitar la conversión de zona del filtro `from`; aceptar `auditable_id` sin tipo.

**Postman:** `syncvet-fase-02-auditoria` (genera registros con las otras colecciones y los filtra).

## Documentación por entrega

| Entrega | Además de lo de `docs/README.md` (Mantenimiento) |
|---|---|
| 2.1 | `CLAUDE.md` §3 (orden de bloqueo clínica → sucursal → depósito) y una sección nueva §6.4 de sucursales y depósitos (las pendientes de diseño pasan a §6.x) |
| 2.2a | `CLAUDE.md` §6 (usuarios: email único global, roles, último administrador) |
| 2.2b | `CLAUDE.md` §3 (usuarios inactivos) y §4 (`USER_INACTIVE`); guía de Postman con el login de un inactivo; deuda 6 sin cambios |
| 2.3 | `CLAUDE.md` §3 (orden usuario → veterinario → serie) y §6 (veterinarios) |
| 2.4 | `CLAUDE.md` §6 (servicios) |
| 2.5 | `CLAUDE.md` §4 (descarga del logo) y §6 (configuración); `development/setup.md` (disco `clinic_logos` y el cron del *scheduler*); deuda 14 resuelta |
| 2.6 | `CLAUDE.md` §3 (fechas con hora: `AppDateTime`); deuda 13 resuelta; módulo de auditoría COMPLETADO |

## Preguntas del diseño

Lo que el diseño no podía decidir solo, con su propuesta y la respuesta del usuario (R-029): todas aprobadas según la propuesta.

| # | Pregunta | Propuesta | Respuesta | Fecha |
|---|---|---|---|---|
| 16 | ¿Quién pone el código de sucursales y depósitos (`SUC-01`, `DEP-01`)? | Lo escribe el administrador: único en la clínica, en mayúsculas, editable (no son documentos ni salen de una serie) | Aprobada (usuario) | 2026-11-06 |
| 17 | ¿El alta de una sucursal crea su depósito por defecto? | Sí, en la misma transacción, con `warehouse_code` obligatorio y `warehouse_name` ("Principal" por defecto) | Aprobada (usuario) | 2026-11-06 |
| 18 | Al inactivar una sucursal, ¿qué pasa con los usuarios que la tienen como actual? | Quedan sin sucursal actual (`current_branch: null` en `me`) hasta que elijan otra; su asignación se conserva | Aprobada (usuario) | 2026-11-06 |
| 19 | ¿La recepción y el veterinario listan sucursales? | Sí, solo `branches.index` (la agenda las necesita en la FASE 3) | Aprobada (usuario) | 2026-11-06 |
| 20 | Contraseñas: ¿el cambio por el administrador revoca los tokens del usuario? ¿Se agrega `PATCH me/password` para la contraseña propia? | Sí a los dos (en la 2.2b, sin permiso, con la contraseña actual) | Aprobada (usuario) | 2026-11-06 |
| 21 | ¿El precio de un servicio admite decimales? | No mientras la moneda sea PYG: entero de 0 a 999.999.999.999 (la columna sigue en `decimal(14,2)`) | Aprobada (usuario) | 2026-11-06 |
| 22 | ¿Qué duración admite un servicio? | De 5 a 720 minutos, en múltiplos de 5 (la agenda de la FASE 3 usa bloques de 5) | Aprobada (usuario) | 2026-11-06 |
| 23 | ¿Qué claves tiene `system_settings`? | `contact_email`, `contact_phone`, `website` y `document_footer` (pie de recetas y comprobantes no fiscales); `logo_path`, interna | Aprobada (usuario) | 2026-11-06 |
| 24 | ¿El RUC de la clínica se edita por la API? | No: se muestra; un error de carga se corrige con un comando más adelante (lo crea `clinic:create`) | Aprobada (usuario) | 2026-11-06 |
| 25 | ¿Cómo es el logo? | JPG, PNG o WEBP de hasta 2 MB, vuelto a codificar como la foto, sin redimensionar; más grande → 413 | Aprobada (usuario) | 2026-11-06 |
| 26 | ¿Qué antigüedad debe tener un archivo huérfano para borrarlo? ¿Se programa el comando? | 24 horas; diario a las 03:00, con el cron en el despliegue (FASE 10) | Aprobada (usuario) | 2026-11-06 |
| 27 | ¿El listado de la auditoría trae los valores anteriores y nuevos? | No: solo el detalle, para que el listado sea liviano; las rutas internas (`photo_path`, `logo_path`) nunca salen | Aprobada (usuario) | 2026-11-06 |
| 28 | Una fecha con hora sin zona, ¿se acepta? | Sí, como hora de Asunción (es lo que pasa hoy con `deceased_at`: no cambia el contrato) | Aprobada (usuario) | 2026-11-06 |
| 29 | ¿Se amplía `veterinarians.license_number` a 60 para el sufijo de baja? | Sí, con una migración; se valida hasta 30 | Aprobada (usuario) | 2026-11-06 |
| 30 | ¿Se puede quitar el rol `veterinario` a un usuario con ficha de veterinario? | No: 409 `USER_IS_VETERINARIAN` hasta que se elimine la ficha (si está en uso, el usuario se inactiva) | Aprobada (usuario) | 2026-11-06 |
| 31 | Entregas: la 2.2 se divide en 2.2a y 2.2b, y las sucursales pasan a ser la 2.1 | Aprobar con el diseño | Aprobada (usuario) | 2026-11-06 |

## Criterio de cierre

Un administrador deja la clínica lista para operar sin tocar la base: sucursales y depósitos, usuarios con roles y sucursales,
veterinarios, servicios, datos y logo de la clínica, y consulta de la auditoría. Cada regla tiene su test con el rol de la
operación, y cada ruta nueva su acceso por rol y su aislamiento por clínica. Suite en verde en SQLite y en MySQL. Deudas 13 y 14
resueltas; la 2 revisada en los modelos de la fase.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado, una entrega por vez.

```text
Implementa la entrega 2.X de la FASE 2 según docs/fases/fase-02-datos-maestros.md (diseño aprobado).

- Respeta las respuestas de R-029 y las de las preguntas del diseño.
- Reutiliza lo existente (CatalogLock, CatalogName, DeletedSuffix, DocumentSequenceService, ImageReencoder, ListFilters, SearchTerm).
- Sigue CLAUDE.md. Corre la suite completa en SQLite (y en MySQL si la entrega toca migraciones, búsquedas, concurrencia o autenticación) e infórmame. No hagas commit.
```

## Resultado

Lo completa el cierre de cada entrega ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y
aserciones, desvíos del diseño y por qué.

### Entrega 2.1

Implementación en `feature/fase-02-01-sucursales`, creada desde `docs/fase-02-diseno` (`405ea96`), revisada en una sesión de
Claude Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-031)) y
validada por el usuario ([validación del usuario](#validación-del-usuario-r-031)). Implementación, correcciones y cierre van en
un solo commit. Decisiones en [R-030](../decisions/registro.md) y R-031.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `BranchController`, `BranchWarehouseController` y `WarehouseController`; `IndexBranchRequest`, `StoreBranchRequest`, `UpdateBranchRequest`, `IndexWarehouseRequest`, `StoreWarehouseRequest`, `UpdateWarehouseRequest` y `BranchMessages`; `BranchResource`, `ShowBranchResource` y `WarehouseResource`; `BranchService` y `WarehouseService`; `BranchException`; `tests/Feature/Branches` (`BranchIndexTest`, `BranchStoreTest`, `BranchUpdateTest`, `WarehouseTest`, `BranchLockTest`) y `tests/Concerns/InteractsWithBranches`; `docs/testing/postman/syncvet-fase-02-sucursales.postman_collection.json` |
| Cambiados | `routes/api.php` (`apiResource` de sucursales sin `destroy`, depósitos anidados con `index` y `store`, y `warehouses` con `update`), `Branch` y `Warehouse` (deuda 2, abajo), `RolesAndPermissionsSeeder` (`branches.index` al recepcionista y al veterinario, pregunta 19), `lang/es/validation.php` (`establishment`, `is_default`, `warehouse_code`, `warehouse_name`, `branch_id`), `ClinicCreateCommand` (descripción "Alta de una clínica…", por el verbo `crear` de `VoseoTest`) |
| Tests cambiados | `RoleAccessTest` (parámetros `branch` y `warehouse`), `RouteBindingTest` (sucursal y depósito de otra clínica → 404 en cada ruta, sin cambios), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas), `VoseoTest` (verbo `crear`), `tests/Fixtures/clinic-roles.json` |
| Documentación | `CLAUDE.md` §1, §3 (orden de bloqueo clínica → sucursal → depósito o usuario) y §6.4 nueva (las pendientes de diseño pasan a §6.5); la guía de Postman; este documento, el registro (R-030 y R-031), la deuda técnica (2, revisada en `Branch` y `Warehouse`), el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 26 a 33; los permisos siguen en 123.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `branches` | `branches.index` | 200, activas (la por defecto primero); `include_inactive=1`, todas | 422 |
| POST | `branches` | `branches.store` | 201, `ShowBranchResource` con su depósito por defecto | 422 |
| GET | `branches/{branch}` | `branches.show` | 200, con todos sus depósitos | 404 |
| PATCH/PUT | `branches/{branch}` | `branches.update` | 200 | 404; 409 `BRANCH_DEFAULT_NOT_DEACTIVATABLE`; 422 `BRANCH_ESTABLISHMENT_NOT_EDITABLE`; 422 |
| GET | `branches/{branch}/warehouses` | `warehouses.index` | 200, paginado; activos salvo `include_inactive=1` | 404; 422 |
| POST | `branches/{branch}/warehouses` | `warehouses.store` | 201 | 404; 422 (también en `branch`: sucursal inactiva) |
| PATCH/PUT | `warehouses/{warehouse}` | `warehouses.update` | 200 | 404; 409 `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE`; 422 |

**Tests.** Suite del 2026-11-07, después de las correcciones: 664 casos con los *data providers*; en SQLite, 7525 aserciones con 2
omitidos (solo de MySQL); contra MySQL 8.4.3 (`syncvet_test_mysql`), 7541 aserciones sin omitidos. Frente al cierre de la FASE 1
(616 casos; 6756 y 6772 aserciones): 48 casos y 769 aserciones más en las dos bases. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `BranchStoreTest` | 13 | 98 | Alta con su depósito por defecto (códigos en mayúsculas, "Principal" o el nombre enviado, sin asignar la sucursal a quien la crea); `is_default` mueve la por defecto; la primera de una clínica sin ninguna es la por defecto; código y nombre únicos (sin mayúsculas, acentos ni espacios repetidos); código inválido; establecimiento mal formado o repetido; teléfono; código del depósito obligatorio y único; los de otra clínica están libres; el recepcionista lista y no crea |
| `BranchUpdateTest` | 13 | 75 | Cambio de la por defecto; `is_default: false` → 422 en la por defecto y sin efecto en otra; inactivar la por defecto → 409; inactiva como por defecto → 422; inactivar y reactivar otra (sus depósitos no cambian); establecimiento con facturas → 422 y sin facturas → 200; únicos al editar; usuarios sin la sucursal actual inactivada, con su asignación; `PATCH` y `PUT` parciales; el detalle con todos los depósitos |
| `WarehouseTest` | 9 | 67 | Alta anidada (un `branch_id` del cuerpo se ignora); uno por defecto por sucursal; el por defecto no se inactiva ni se desmarca; inactivo como por defecto → 422; `branch_id` al editar → 422; código único en la clínica y nombre en la sucursal; listado con `include_inactive` y `search` por nombre y por código; sucursal inactiva → 422 en `branch` y, reactivada, 201; otra clínica → 404 |
| `BranchIndexTest` | 3 | 31 | Activas de la clínica con la por defecto primero; `include_inactive`; `search` literal en código y nombre; paginación y filtros mal formados |
| `BranchLockTest` | 4 | 33 | Orden de los bloqueos dentro de la transacción del Service: clínica antes de leer las sucursales en el alta; clínica → sucursal en la edición; clínica → sucursal → usuarios al inactivar; clínica → sucursal → depósito en el alta y la edición de un depósito (`for update` en MySQL) |

**Protecciones quitadas de a una.** En la implementación, las cinco del diseño, cada una con un test que falla: el chequeo de la
por defecto al inactivar, el paso de `is_default` a la anterior, el chequeo de facturas de `establishment`, el bloqueo de la
clínica en el alta (en SQLite y en MySQL) y el scope de la clínica en el *route binding* de la sucursal (la 2.1 no tiene un
`exists` de sucursal). En las correcciones, el chequeo de la sucursal inactiva y el voseo de "creá" (abajo).

**Deuda 2 (`Branch` y `Warehouse`).** Revisados: los casts (`is_default`, `is_active`) y las relaciones existentes (`warehouses`,
`users`, `branch`) coinciden con las claves foráneas. Suman valores por defecto (`is_default` falso, `is_active` verdadero), el
código en mayúsculas y el nombre limpio de espacios (`CatalogName::clean`) al guardar, y `Branch::invoices()`.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | `is_default: false` responde 422 solo en la sucursal o el depósito que es el por defecto; en otro no cambia nada | El frontend manda el formulario completo | Implementación; aceptado por el usuario |
| 2 | Si la clínica no tiene sucursal por defecto, la que se crea pasa a serlo; igual el primer depósito de una sucursal sin depósito por defecto | Siempre hay una por defecto; solo pasa en una clínica sin sucursales, como las de `ClinicFactory` | Implementación; aceptado por el usuario |
| 3 | Código, nombre y establecimiento únicos los revisa el Service con la clínica bloqueada, no reglas `unique` del Form Request | Dos altas simultáneas no terminan en un 500 por el índice único; la respuesta es la misma, 422 `VALIDATION_ERROR` | Implementación; aceptado por el usuario |
| 4 | El mismo `establishment` con facturas responde 200 | No es un cambio | Implementación; aceptado por el usuario |
| 5 | El listado de depósitos está paginado, con `ListFilters` e `include_inactive` | Como los demás listados | Implementación; aceptado por el usuario |
| 6 | Los nombres usan `CatalogMessages::NAME_REGEX` (al menos una letra) | El diseño no fijaba el formato; es el de especies y razas | Implementación; aceptado por el usuario |
| 7 | Un depósito se podía crear en una sucursal inactiva | El diseño no lo mencionaba | Implementación; **rechazado** por el usuario: 422 en `branch` ("La sucursal está inactiva."), corregido en la revisión |
| 8 | Orden de bloqueo nuevo: clínica → sucursal → usuario | Inactivar una sucursal vacía `current_branch_id` usuario por usuario, para la auditoría | Implementación; aceptado por el usuario |

#### Correcciones de la revisión (R-031)

La revisión de la 2.1, en una sesión aparte de la implementación, encontró un hallazgo de gravedad media (el desvío 7, rechazado)
y cuatro de baja: tests que pasaban con la regla rota. El usuario pidió corregirlos. Decisiones en
[R-031](../decisions/registro.md).

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | Se podía crear un depósito en una sucursal inactiva (desvío 7) | `WarehouseService::create()` revisa `is_active` sobre la sucursal ya bloqueada (ve una inactivación simultánea): 422 en `branch`, "La sucursal está inactiva.". La colección de Postman suma el pedido 24b | `WarehouseTest::test_a_warehouse_is_not_created_in_an_inactive_branch`: sin el chequeo, 201 en lugar de 422 |
| 2 | Baja | La búsqueda de depósitos por código no tenía test: sin `whereContains('code', …)` la suite pasaba | `?search=dep-08` encuentra el depósito por su código | `WarehouseTest::test_the_list_shows_the_active_warehouses_of_the_branch` |
| 3 | Baja | Nada comprobaba que al usuario que crea una sucursal no se le asigna | Aserción de `branch_user` vacío para la sucursal nueva | `BranchStoreTest::test_a_branch_is_created_with_its_default_warehouse` |
| 4 | Baja | El verbo `crear` ("creá uno nuevo") no estaba en `VoseoTest`: "crea uno nuevo" pasaba | `crear` en `VERBS`. La descripción de `clinic:create` ("Crea una clínica…", tercera persona) pasa a "Alta de una clínica…" para no dar una falsa alarma | `VoseoTest::test_messages_use_voseo` con "crea uno nuevo" |
| 5 | Baja | El orden clínica → sucursal → usuario (desvío 8) no tenía test | Test de orden al inactivar una sucursal que un usuario tiene como actual | `BranchLockTest::test_deactivating_a_branch_locks_clinic_branch_and_then_the_users` |

La revisión confirmó además: la lógica en los Services; los Controllers con `HasMiddleware` y permisos que existen en el seeder;
errores con `BranchException` o 422 de validación, sin 500; transacciones y bloqueos en el orden del diseño; sin migraciones;
contratos existentes sin cambios; el recepcionista y el veterinario listan sucursales y reciben 403 en las otras seis rutas
(`RoleAccessTest`); y `ValidationTranslationsTest`, frente a `origin/main`, solo suma los pedidos de las rutas nuevas (el
implementador lo había vaciado por error y lo restauró).

#### Validación del usuario (R-031)

El 2026-11-07 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-sucursales`, con el
pedido 24b de las correcciones: 107 de 107 pruebas en verde, sin errores (37 requests). Suite sobre el mismo código: la de las
correcciones (664 casos; 7525 aserciones en SQLite con 2 omitidos y 7541 en MySQL 8.4.3). Con este cierre el módulo de sucursales
y depósitos queda COMPLETADO; la FASE 2 sigue con la 2.2a (usuarios).

### Entrega 2.2a

Implementación en `feature/fase-02-02a-usuarios`, creada desde `feature/fase-02-01-sucursales`, revisada en una sesión de Claude
Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-033)) y validada por el
usuario ([validación del usuario](#validación-del-usuario-r-033)). Implementación, correcciones y cierre van en un solo commit.
Decisiones en [R-032](../decisions/registro.md) y R-033.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `UserController` y `RoleController`; `IndexUserRequest`, `StoreUserRequest`, `UpdateUserRequest` y `UserMessages`; `UserResource`, `ShowUserResource` y `RoleResource`; `UserService`; `UserException`; `App\Enums\UserRole`; `App\Support\PersonName` (R-033); `tests/Feature/Users` (`UserStoreTest`, `UserUpdateTest`, `UserIndexTest`, `UserLockTest`, `RolesIndexTest`) y `tests/Concerns/InteractsWithUsers`; `docs/testing/postman/syncvet-fase-02-usuarios.postman_collection.json` |
| Cambiados | `routes/api.php` (`apiResource` de usuarios sin `destroy` y `GET roles`), `lang/es/validation.php` (`roles`, `roles.*`, `role`, `branch_ids`, `branch_ids.*`), `StoreClientRequest`, `UpdateClientRequest` y `ClientMessages` (nombres de personas, R-033), `.gitignore` (`*.code-workspace`) |
| Tests cambiados | `RoleAccessTest` (parámetro `user`), `RouteBindingTest` (usuario de otra clínica → 404 en `GET`, `PATCH` y `PUT`, sin cambios), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas; el nombre de un campo con comodín, `roles.*`, se busca como lo hace el validador), `ClientStoreTest` y `ClientUpdateTest` (nombres con apóstrofo y guion) |
| Documentación | `CLAUDE.md` §1, §3 (orden de bloqueo clínica → usuario), §6.1 (nombres de personas) y §6.5 nueva, usuarios (las pendientes de diseño pasan a §6.6); la guía de Postman; este documento, el registro (R-032 y R-033), la deuda técnica (2, revisada en `User`), el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 33 a 38; los permisos siguen en 123.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `users` | `users.index` | 200, paginado; `search`, `role` y `branch_id` | 422 |
| POST | `users` | `users.store` | 201, `ShowUserResource` | 422 |
| GET | `users/{user}` | `users.show` | 200, `ShowUserResource` | 404 (también de otra clínica) |
| PATCH/PUT | `users/{user}` | `users.update` | 200 | 404; 409 `USER_LAST_ADMIN`; 422 (también `password` sobre el propio usuario) |
| GET | `roles` | `roles.index` | 200, los tres roles con `name` y `label`, sin paginar | — |

**Tests.** Suite del 2026-11-07, después de las correcciones: 706 casos con los *data providers*; en SQLite, 8277 aserciones con 2
omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 8293 aserciones sin omitidos. Frente al cierre de la 2.1 (664 casos;
7525 y 7541 aserciones): 42 casos y 752 aserciones más en las dos bases. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `UserStoreTest` | 13 | 131 | Alta con roles y sucursales (email en minúsculas, la por defecto como sucursal actual, sin tokens); la contraseña y su hash fuera de la auditoría y `ROLES_SYNCED`; la primera activa si la por defecto no está; email ASCII y de hasta 150; email único en toda la base, también de otra clínica o eliminado, sin intentar guardar; el email tomado entre el chequeo y el guardado → 422; contraseña débil o filtrada; sucursal de otra clínica, inactiva o inexistente; roles válidos y sin repetir; el mensaje de una lista vacía; nombres con apóstrofo y guion; `clinic_id` del cuerpo ignorado; el recepcionista y el veterinario → 403 en las 5 rutas |
| `UserUpdateTest` | 15 | 114 | `ROLES_SYNCED` (sin otro registro si no cambian); el último administrador → 409, con un eliminado y uno de otra clínica que no cuentan, sin cambios a medias; con otro administrador → 200; un administrador real que se quita su propio rol (solo, 409; con otro, 200); la sucursal actual que sale de la lista y la del usuario que no tiene una; la sucursal inactiva que ya tiene; el cambio de contraseña revoca los tokens del usuario y no los de otro; con tokens reales, el del administrador sigue y la contraseña no llega a la auditoría; la propia → 422 (también vacía o nula) y la sesión sigue; email único; `PATCH` y `PUT` parciales; listas vacías y nulos → 422; el detalle sin secretos |
| `UserIndexTest` | 5 | 42 | Solo los de la clínica y sin eliminados; `search` literal en nombre, apellido y email; `role` y `branch_id` (también una sucursal inactiva); filtros mal formados y sucursal de otra clínica → 422; paginación |
| `UserLockTest` | 2 | 11 | La edición bloquea la clínica y después el usuario antes de contar los administradores; el alta, la clínica antes de leer las sucursales |
| `RolesIndexTest` | 2 | 7 | Los tres roles con su etiqueta; el Enum coincide con los roles del seeder |

En `tests/Feature/Clients`, `ClientStoreTest` y `ClientUpdateTest` suman un test cada uno (nombres con apóstrofo y guion, R-033).

**Mutaciones.** Las cinco del diseño, en la implementación (R-032), cada una con un test que falla: quitar el chequeo del último
administrador, el bloqueo de la clínica al contar, `withTrashed()` del email único, el filtro de clínica del `exists` de las
sucursales y la revocación de tokens. En las correcciones, quitar el chequeo de la contraseña propia hace fallar
`test_the_administrator_does_not_change_the_own_password_here` (200 en lugar de 422).

**Deuda 2.** `User` revisado contra la tabla y las claves foráneas: casts (`password` → `hashed`) y relaciones (`clinic`,
`currentBranch`, `branches`, `veterinarian`) correctos, sin cambios.

**Desvíos del diseño.** Los nueve de la implementación, aceptados por el usuario (R-032): el bloqueo de la clínica en toda alta y
edición (1), la primera sucursal activa como actual (2), el email único en el Service (3), la revocación con cualquier `password`
(4), el cambio de sucursales en la auditoría (5), `roles` como `{name, label}` (6), "administrador activo" como no eliminado hasta
la 2.2b (7), `EMAIL_DNS_CHECK` en el email (8) y el orden del listado (9). **El 7 queda condicionado:** la 2.2b cuenta solo los
administradores con `is_active` y lo prueba con un test (un administrador inactivo no evita el 409). Las correcciones suman uno que
cambia una regla de la FASE 1: los nombres de personas (R-033).

#### Correcciones de la revisión (R-033)

La revisión no encontró errores en el código. Lo que el usuario decidió corregir:

| # | Gravedad | Hallazgo | Corrección | Test |
|---|---|---|---|---|
| 1 | Media | El administrador que cambiaba su propia contraseña por `users/{user}` revocaba también el token en uso (el pedido siguiente, 401), al revés que el `PATCH me/password` de la 2.2b | `password` sobre el propio usuario → 422, "Para cambiar tu contraseña usá tu perfil.", también vacía o nula | `UserUpdateTest::test_the_administrator_does_not_change_the_own_password_here` |
| 2 | Baja | Un usuario sin sucursal actual que recibía sucursales seguía sin una | La regla del alta también cuando no tiene sucursal actual | `UserUpdateTest::test_a_user_without_current_branch_gets_one_with_the_rule_of_the_store` |
| 3 | Baja | Sin test con un administrador real que se quita su propio rol | Test nuevo | `UserUpdateTest::test_a_real_administrator_removes_the_own_role` |
| 4 | Baja | La revocación se probaba sin tokens guardados para quien hace el cambio, y la auditoría de la contraseña solo en el alta | Test con tokens reales y la auditoría de la edición | `UserUpdateTest::test_a_password_change_keeps_the_token_of_the_administrator_and_stays_out_of_the_audit` |
| 5 | Baja | `UserIndexTest` fallaba a veces: el apellido al azar del usuario que hace el pedido podía ordenarse primero | `actAsUserManager` le fija nombre, apellido y email | `UserIndexTest` |
| 6 | — | Nombres de personas (pedido del usuario; cambia la FASE 1) | Apóstrofo y guion entre letras y la `ü` en clientes, con `App\Support\PersonName` | `ClientStoreTest`, `ClientUpdateTest` y `UserStoreTest::test_person_names_take_an_apostrophe_or_a_hyphen_between_letters` |

La revisión confirmó además: la lógica en los Services; los Controllers con `HasMiddleware` y permisos que existen en el seeder;
errores con `UserException` o 422 de validación, sin 500; el orden de bloqueo clínica → usuario; la contraseña con
`#[\SensitiveParameter]`, fuera de las respuestas y de la auditoría; login, `me` y logout sin cambios; un usuario de otra clínica no
aparece en el listado ni cuenta como administrador; el recepcionista y el veterinario reciben 403 en las 5 rutas.

#### Validación del usuario (R-033)

El 2026-11-07 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-usuarios`, con el pedido
16b de las correcciones (30 requests): sin errores. Número de pruebas: 78 de 78 pruebas, en 30 requests (cifra tomada de Newman: la colección corrida otra vez el 2026-11-07 contra la base de desarrollo, con `SUC-USR` ya creada, sin fallas; el Collection Runner del usuario no informó la cifra). Suite
sobre el mismo código: la del cierre (706 casos; 8277 aserciones en SQLite con 2 omitidos y 8293 en MySQL). La FASE 2 sigue con la
2.2b (usuarios inactivos, baja, sucursal actual y contraseña propia).

### Entrega 2.2b

Implementación en `feature/fase-02-02b-usuarios`, creada desde `feature/fase-02-02a-usuarios`, revisada en una sesión de Claude
Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-035)) y validada por el
usuario ([validación del usuario](#validación-del-usuario-r-035)). Implementación, correcciones y cierre van en un solo commit.
Decisiones en [R-034](../decisions/registro.md) y R-035.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `database/migrations/2026_11_07_000000_add_is_active_to_users.php`; `UpdateCurrentBranchRequest` y `UpdateOwnPasswordRequest`; `tests/Feature/Users` (`UserDeactivateTest`, `UserDeleteTest`) y `tests/Feature/Auth` (`LoginInactiveUserTest`, `InactiveUserTokenTest`, `CurrentBranchTest`, `OwnPasswordTest`) |
| Cambiados | `User` (`is_active` activo por defecto, revocación de tokens al inactivar, scope `active()`); `UserService` (inactivación, baja con `USAGE`, `USER_SELF_ACTION` y el último administrador activo); `AuthService` (login del inactivo, `setCurrentBranch()` y `changeOwnPassword()` con su límite de intentos); `SetCurrentClinic` (token de un inactivo → 403); `AuthController` y `UserController`; `UpdateUserRequest`, `IndexUserRequest` y `UserMessages` (`is_active`, `include_inactive`); `UserResource` y `ShowUserResource` (`is_active`); `routes/api.php` (`destroy` de usuarios y las dos rutas de `me`); `lang/es/validation.php` (`current_password`); la colección de Postman de usuarios (30 → 53 requests) |
| Tests cambiados | `InteractsWithApiRoutes` (las dos rutas de `me` en `ROUTES_WITHOUT_PERMISSION`), `InteractsWithUsers` (`users.destroy`), `RoleAccessTest` (desvío 5), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas), `RouteBindingTest` (`DELETE` de un usuario de otra clínica → 404), `UserLockTest` (orden de la baja, de la inactivación y de `me/current-branch`), `UserStoreTest` (desvío 6) |
| Documentación | `CLAUDE.md` §1, §3 (usuarios inactivos y orden de bloqueo de `me/current-branch`, de la inactivación y de la baja), §4 (`USER_INACTIVE`, `USER_SELF_ACTION`, `USER_IN_USE`) y §6.5; la guía de Postman; este documento, el registro (R-034 y R-035), el handoff y los documentos de estado. La deuda 6 sigue igual |

**Endpoints.** Las rutas pasan de 38 a 41; los permisos siguen en 123; las migraciones, de 32 a 33.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| DELETE | `users/{user}` | `users.destroy` | 200, `data: null` | 404 (también de otra clínica); 409 `USER_IN_USE`, `USER_SELF_ACTION`, `USER_LAST_ADMIN` |
| PATCH | `me/current-branch` | Sin permiso (`auth.current-branch`) | 200, `MeResource` | 401; 403 `USER_INACTIVE`; 422 |
| PATCH | `me/password` | Sin permiso (`auth.password`) | 200, `data: null` | 401; 403 `USER_INACTIVE`; 422; 429 `AUTH_TOO_MANY_ATTEMPTS` |

Además: `PATCH`/`PUT users/{user}` acepta `is_active` (409 `USER_SELF_ACTION` o `USER_LAST_ADMIN`); `GET users`, `include_inactive`;
`POST login` responde 403 `USER_INACTIVE` a un inactivo con la contraseña correcta, y toda ruta con `clinic`, a su token vigente.

**Migración.** `is_active` booleano, no nulo, por defecto `true`, después de `password`, sin índice. Probada en MySQL durante la
implementación: sube, baja y vuelve a subir (`tinyint(1)`, por defecto 1).

**Tests.** Suite del 2026-11-07, después de las correcciones: 742 casos con los *data providers*; en SQLite, 8642 aserciones con 2
omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 8658 aserciones sin omitidos. Frente al cierre de la 2.2a (706 casos;
8277 y 8293 aserciones): 36 casos y 365 aserciones más en las dos bases. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `UserDeactivateTest` | 7 | 46 | Inactivar revoca todos los tokens (el viejo, 401) y no los de otros; reactivar no los devuelve; `is_active: true` sin cambio conserva los tokens; a sí mismo → 409 (reenviar `true` no lo es); el último administrador activo → 409 sin cambios a medias; un administrador inactivo no cuenta (condición del desvío 7 de la 2.2a); el listado sin inactivos salvo `include_inactive=1`; `is_active` booleano y en la auditoría |
| `UserDeleteTest` | 8 | 38 | Baja sin uso: sufijo en el email (libre para un alta nueva), tokens revocados, `branch_user` conservado, 404 después; con ficha de veterinario (también eliminada), con `cash_registers.user_id`, con `schedule_blocks.created_by` y con `cash_movements.cancelled_by` (segunda columna) → 409 sin revocar tokens; a sí mismo → 409; el último administrador activo → 409; `USAGE` cubre toda clave foránea a `users`; el recepcionista y el veterinario → 403 |
| `LoginInactiveUserTest` | 5 | 37 | Con la contraseña correcta, 403 `USER_INACTIVE` sin token y `LOGIN_FAILED` con el motivo; con una incorrecta, 422 `INVALID_CREDENTIALS`; con la incorrecta, la misma respuesta completa que un activo en 6 intentos (el sexto, 429 con `Retry-After`) y la auditoría sin el motivo; el inactivo con la correcta no cuenta para el límite; la clínica suspendida se revisa antes |
| `InactiveUserTokenTest` | 2 | 19 | Un token vigente de un inactivo → 403 en `me`, `clients` y `branches`, y en `me/password` y `me/current-branch` sin cambiar la contraseña ni la sucursal actual; `logout` funciona |
| `CurrentBranchTest` | 5 | 39 | Sucursal asignada y activa → 200 con `MeResource`; no asignada, inactiva, de otra clínica, inexistente o mal formada → 422; el Service rechaza una inactivada después de la validación y una no asignada sin pasar por el Request; sin token → 401 |
| `OwnPasswordTest` | 5 | 53 | Con tokens reales: conserva el token en uso, revoca los demás del usuario y no los de otro, login con la nueva, la contraseña y su hash fuera de la auditoría; la actual incorrecta → 422; la nueva débil, filtrada, vacía o igual a la actual → 422; el sexto intento en un minuto → 429 con `Retry-After` (el contador es del usuario); un cambio correcto limpia el contador |
| `UserLockTest` | 5 | 35 | Suma tres a los de la 2.2a: la baja y la inactivación bloquean la clínica y después el usuario antes de contar los administradores y de revisar el uso; `me/current-branch`, clínica → sucursal → usuario |

**Mutaciones.** Nueve en la implementación (R-034), cada una con un test que falla: quitar la revocación de tokens al inactivar, el
chequeo de `is_active` del login, revisarlo antes de verificar la contraseña, el chequeo de `SetCurrentClinic`, el chequeo de uso de
la baja, los dos chequeos de "a sí mismo" (baja e inactivación), contar también los administradores inactivos y el límite de
intentos de la contraseña propia; sacar una tabla de `USAGE` hace fallar `test_usage_covers_every_foreign_key_to_users`. En las
correcciones, siete más: sacar `updatePassword` o `updateCurrentBranch` del middleware `clinic`, guardar el motivo en el intento
fallido de un inactivo, no contar ese intento, cortar el bucle de `isInUse` en la primera columna, quitar el `whereHas` de
`setCurrentBranch` y bloquear el usuario antes que la sucursal.

**Desvíos del diseño.** Los siete de la implementación, aceptados por el usuario (R-034): el último administrador generalizado (1),
el bloqueo clínica → sucursal → usuario de `me/current-branch` (2), los errores de validación de `me/password` sin contar intentos
(3), los dos registros de auditoría de la baja (4), el 422 de las rutas de `me` sin cuerpo en `RoleAccessTest` (5), dos tests
inestables de la 2.2a (6) y el esquema actual en el test de las claves foráneas en MySQL (7). Además, un agregado al diseño pedido
por el usuario: el límite de 5 intentos por minuto en `me/password`. **La condición del desvío 7 de la 2.2a (R-032) quedó cumplida:**
`USER_LAST_ADMIN` cuenta solo los administradores con `is_active`, con su test.

#### Correcciones de la revisión (R-035)

La revisión no encontró errores en el código: el comportamiento cumplía el diseño en todo lo probado. Lo que el usuario decidió
corregir:

| # | Gravedad | Hallazgo | Corrección | Test |
|---|---|---|---|---|
| 1 | Media | Ningún test probaba que un inactivo no usa `me/password` ni `me/current-branch`: sacarlas del middleware `clinic` no hacía fallar la suite | Test con un token vigente de un inactivo en las dos rutas | `InactiveUserTokenTest::test_an_inactive_user_does_not_change_the_own_password_nor_the_current_branch` |
| 2 | Baja | El login de un inactivo con la contraseña incorrecta se probaba con un intento, solo el código y el mensaje | La respuesta completa frente a un activo en 6 intentos, el 429 en los dos y la auditoría sin el motivo | `LoginInactiveUserTest::test_with_a_wrong_password_an_inactive_user_looks_like_an_active_one` |
| 3 | Baja | `USAGE` se probaba solo con tablas de una columna: revisar solo la primera no hacía fallar la suite | Uso por `created_by` y por `cancelled_by` (segunda columna) | `UserDeleteTest::test_a_created_by_or_a_second_column_is_usage` |
| 4 | Baja | La asignación que revisa `setCurrentBranch` y su orden de bloqueo, sin test | Test del Service sin el Request y del orden clínica → sucursal → usuario | `CurrentBranchTest::test_a_branch_not_assigned_is_rejected_by_the_service` y `UserLockTest::test_choosing_the_current_branch_locks_the_clinic_then_the_branch_then_the_user` |
| 5 | Baja | El comentario de `UserService::USAGE` nombraba `UserUsageTest`, que no existe | Nombra `UserDeleteTest::test_usage_covers_every_foreign_key_to_users` | — |

La revisión confirmó además: la lógica en los Services; errores con `UserException`, `AuthException` o 422 de validación, sin 500;
los bloqueos en el orden clínica → sucursal → usuario; la contraseña actual y la nueva con `#[\SensitiveParameter]`, fuera de las
respuestas y de la auditoría; la migración; login, `me` y logout sin cambios fuera del 403 `USER_INACTIVE`, y el logout de un
inactivo; la baja que libera el email; `USAGE` igual a las claves foráneas de las migraciones; el 429 con `Retry-After` y el contador
por usuario; el recepcionista y el veterinario, 403 en `DELETE users/{user}` y 200 en `me/current-branch` y `me/password`.

#### Validación del usuario (R-035)

El 2026-11-07 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-usuarios`: 53 requests,
130 de 130 pruebas, 0 errores. Suite sobre el mismo código: la del cierre (742 casos; 8642 aserciones en SQLite con 2 omitidos y 8658
en MySQL). La FASE 2 sigue con la 2.3 (veterinarios).

### Entrega 2.3

Implementación en `feature/fase-02-03-veterinarios`, creada desde `feature/fase-02-02b-usuarios`, revisada en una sesión de Claude
Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-037)) y validada por el
usuario ([validación del usuario](#validación-del-usuario-r-037)). La implementación y las correcciones quedaron en dos commits WIP
(`4ff4a4c` y `c6a6847`), y el cierre va en un commit aparte. Decisiones en [R-036](../decisions/registro.md) y R-037.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `database/migrations/2026_11_09_000000_widen_veterinarians_license_number.php`; `VeterinarianController`; `IndexVeterinarianRequest`, `StoreVeterinarianRequest`, `UpdateVeterinarianRequest` y `VeterinarianMessages`; `VeterinarianResource` y `ShowVeterinarianResource`; `VeterinarianService`; `tests/Feature/Veterinarians` (`VeterinarianStoreTest`, `VeterinarianRestoreTest`, `VeterinarianUpdateTest`, `VeterinarianDeleteTest`, `VeterinarianIndexTest`, `VeterinarianLockTest`), `tests/Feature/Database/VeterinarianLicenseMigrationTest`, `tests/Concerns/InteractsWithVeterinarians` e `InspectsQueries` (los helpers del orden de las consultas, sacados de `UserLockTest`); `docs/testing/postman/syncvet-fase-02-veterinarios.postman_collection.json` |
| Cambiados | `routes/api.php` (`apiResource('veterinarians')`), `Veterinarian` (deuda 2, abajo), `UserService` (`USER_IS_VETERINARIAN`), `ShowUserResource` (`veterinarian`), `lang/es/validation.php` (`user_id`, `license_number`, `specialty`, `commission_percentage`, `status`; `include_inactive` pasa a "incluir los inactivos", R-037) |
| Tests cambiados | `RoleAccessTest` (parámetro `veterinarian`), `RouteBindingTest` (veterinario de otra clínica → 404 en cada ruta, sin cambios), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas), `UserLockTest` (usa `InspectsQueries`), `UserUpdateTest` (`USER_IS_VETERINARIAN` y `veterinarian` en el detalle) |
| Documentación | `CLAUDE.md` §1, §3 (orden clínica → usuario → veterinario → serie y la baja), §4 (`USER_IS_VETERINARIAN`, `VETERINARIAN_IN_USE`) y §6.6 nueva, veterinarios (las pendientes de diseño pasan a §6.7); la guía de Postman; este documento, el de la [FASE 3](fase-03-agenda-y-turnos.md) (la baja con uso en Postman), el registro (R-036 y R-037), la deuda técnica (2, revisada en `Veterinarian`), el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 41 a 46; los permisos siguen en 123; las migraciones, de 33 a 34.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `veterinarians` | `veterinarians.index` | 200, paginado, `VeterinarianResource` (sin comisión ni notas); activos salvo `include_inactive=1`; `status`, `search` y `branch_id` | 422 |
| POST | `veterinarians` | `veterinarians.store` | 201, `ShowVeterinarianResource`; también la restauración de una ficha eliminada | 422 |
| GET | `veterinarians/{veterinarian}` | `veterinarians.show` | 200, `ShowVeterinarianResource` | 404 (también de otra clínica) |
| PATCH/PUT | `veterinarians/{veterinarian}` | `veterinarians.update` | 200 | 404; 422 |
| DELETE | `veterinarians/{veterinarian}` | `veterinarians.destroy` | 200, `data: null` | 404; 409 `VETERINARIAN_IN_USE` |

Además: `PATCH`/`PUT users/{user}` responde 409 `USER_IS_VETERINARIAN` al quitar el rol `veterinario` a un usuario con ficha, y
`GET users/{user}` muestra `veterinarian`. Solo el administrador escribe y ve el detalle; la recepción y el veterinario listan (R-037).

**Migración.** `license_number` de 30 a 60 caracteres, nula; la bajada se detiene con un mensaje claro si alguna matrícula pasa de 30,
sin cortarla. Probada en MySQL: en la implementación, sube, baja y vuelve a subir, y con una matrícula de más de 30 la bajada se
detiene y la columna queda en 60; en las correcciones, un test (solo en MySQL) baja sin matrículas largas (la columna vuelve a 30 y la
de 30 caracteres no se corta) y vuelve a subir.

**Tests.** Suite del 2026-11-09, después de las correcciones: 797 casos con los *data providers*; en SQLite, 9217 aserciones con 3
omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 9237 aserciones sin omitidos. Frente al cierre de la 2.2b (742 casos;
8642 y 8658 aserciones): 55 casos más, y 575 aserciones más en SQLite y 579 en MySQL. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `VeterinarianStoreTest` | 17 | 93 | Alta con `VET-` correlativo, sus datos, sus sucursales y la auditoría; valores por defecto; usuario sin el rol, inactivo, de otra clínica, eliminado o inexistente → 422; el que ya es veterinario → 422; `code` → 422; matrícula única en la clínica (la de otra clínica, libre) y de hasta 30; normalizada antes de validar (`mv-1001` choca con `MV-1001` sin llegar al guardado; se guarda en mayúsculas); comisión de 0 a 100 con hasta 2 decimales; `status`; la recepción y el veterinario listan (sin comisión ni notas) y reciben 403 en las otras cuatro rutas |
| `VeterinarianRestoreTest` | 6 | 38 | Misma fila y mismo código, datos del pedido, `restored` en la auditoría y la serie sin usar (la ficha nueva siguiente sale `VET-000002`); matrícula de otro veterinario → 422 (también en minúsculas) y la ficha sigue eliminada; la restauración guarda la matrícula normalizada; el índice único como respaldo (422, no 500); un usuario sin el rol no restaura; la ficha eliminada de un usuario de otra clínica no se restaura; el Service rechaza a un usuario de otra clínica sin pasar por el Request |
| `VeterinarianUpdateTest` | 5 | 35 | Inactivar en uso, `PATCH` y `PUT` parciales y la auditoría; `user_id` y `code` → 422; matrícula de otro → 422 (también en minúsculas) y la propia normalizada; quitar el rol `veterinario` → 409 y, eliminada la ficha, 200; un usuario inactivo no cambia la ficha y la respuesta lo muestra |
| `VeterinarianDeleteTest` | 10 | 52 | Baja sin uso con el sufijo en la matrícula (libre para otra ficha) y 404 después; la matrícula de 30 con el sufijo entero; sin matrícula; con uso en cada tabla de `USAGE` → 409 sin cambios; `USAGE` cubre toda clave foránea a `veterinarians` |
| `VeterinarianIndexTest` | 5 | 46 | Activos de la clínica sin eliminados, paginados, sin comisión ni notas; `status`; `include_inactive=1` trae activos e inactivos, `status` manda y un valor inválido → 422; `search` literal en código, nombre y matrícula; `branch_id` por las sucursales del usuario (también una inactiva; de otra clínica → 422); filtros mal formados → 422 |
| `VeterinarianLockTest` | 4 | 32 | Alta: clínica → usuario → ficha → serie, con el rol revisado después de bloquear el usuario; restauración: clínica → usuario → ficha, sin la serie; quitar el rol: clínica → usuario antes de buscar la ficha; baja: la ficha antes de revisar el uso (`for update` en MySQL) |
| `VeterinarianLicenseMigrationTest` | 3 | 5 | La columna admite 60 (`varchar(60)` en MySQL); la bajada se detiene con una matrícula de más de 30, sin cortarla; sin matrículas largas, baja a 30 y vuelve a subir (solo en MySQL) |

**Mutaciones.** Las cinco del diseño, en la implementación (R-036): quitar la restauración, el chequeo del rol, el chequeo de uso y el
de `USER_IS_VETERINARIAN`, cada una con un test que falla; quitar el bloqueo del usuario no falla en SQLite (no escribe `for update`,
como prevé el diseño) y hace fallar dos tests en MySQL. En la revisión, quitar el `lockForUpdate` de la ficha: pasa en SQLite y falla
`VeterinarianLockTest` en MySQL. En las correcciones, cada una con un test que falla: quitar `inClinic()` de `lockedUser()`, no pasar
la matrícula a mayúsculas, no normalizarla antes de validar (solo en el Service) y quitar `include_inactive`.

**Deuda 2.** `Veterinarian` revisado contra la tabla y las claves foráneas: relaciones (`user`, también eliminado) y casts correctos;
suma los valores por defecto de `status` (`VeterinarianStatus::ACTIVE->value`) y `commission_percentage`, y su documentación.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | La restauración vuelve a su valor por defecto los campos que no llegan; la auditoría guarda `updated` y `restored` | Como un alta; si no, quedaría la matrícula vieja con el sufijo de baja. `restore()` guarda el modelo | Implementación; aceptado por el usuario |
| 2 | Orden de bloqueo clínica → usuario → ficha → serie en el alta y la restauración | Precisión 1 del usuario; compatible con `UserService` | Implementación; aceptado por el usuario |
| 3 | `USER_IS_VETERINARIAN` salta aunque el usuario hoy no tenga el rol, si tiene una ficha no eliminada y los roles nuevos no incluyen `veterinario` | Una ficha no queda sin el rol | Implementación; aceptado por el usuario |
| 4 | Mensajes propios: "El usuario no tiene el rol veterinario." y, para un usuario inactivo, de otra clínica, eliminado o inexistente, "El usuario no existe o está inactivo." | El diseño no los fijaba | Implementación; aceptado por el usuario |
| 5 | Sin forma de listar activos e inactivos juntos | El diseño solo pedía `status` | Implementación; **corregido** a pedido del usuario: `include_inactive=1`, como sucursales y usuarios |
| 6 | El uso "también eliminados" no aplica hoy: ninguna tabla de uso tiene borrado lógico | La consulta con `DB::table` los contará cuando lo tengan | Implementación; aceptado por el usuario |
| 7 | La matrícula no se normalizaba: en MySQL "mv-1001" y "MV-1001" chocaban y en SQLite no | El diseño no lo fijaba | Implementación; **corregido** a pedido del usuario: mayúsculas y sin espacios en los extremos antes de validar |

#### Correcciones de la revisión (R-037)

La revisión no encontró errores de comportamiento fuera de los desvíos 5 y 7. Lo que el usuario decidió:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | Desvío 7: la matrícula no se normalizaba; en SQLite "mv-1001" se guardaba junto a "MV-1001" (201) y en MySQL respondía 422 | `VeterinarianMessages::normalizeLicense()` en `prepareForValidation` del alta y de la edición (la restauración entra por el alta) y en el Service | `VeterinarianStoreTest::test_the_license_number_is_normalized_before_validating_and_saving` (también si solo normaliza el Service: el 422 tiene que salir de la validación), y las aserciones nuevas de `VeterinarianUpdateTest` y `VeterinarianRestoreTest` |
| 2 | Media | Desvío 5: `include_inactive` no existía (se ignoraba; `include_inactive=x` respondía 200) | `include_inactive` en `IndexVeterinarianRequest` y el Controller: `status` manda; `include_inactive=1` sin `status`, activos e inactivos. El atributo compartido pasa a "incluir los inactivos" | `VeterinarianIndexTest::test_include_inactive_brings_active_and_inactive_and_status_prevails` |
| 3 | — | La recepción y el veterinario recibían 403 en el detalle; el pedido de la revisión decía que "listan y ven" | Decisión del usuario: solo listan; el detalle, con la comisión, es del administrador. Sin cambios en el seeder; tests de que el listado no muestra `commission_percentage` ni `notes` | `VeterinarianStoreTest::test_the_receptionist_and_the_veterinarian_only_list` y `VeterinarianIndexTest` |
| 4 | Baja | Quitar `inClinic()` de `lockedUser()` no hacía fallar la suite: solo el `exists` del Request frenaba a un usuario de otra clínica | Tests del alta con el usuario de otra clínica que tiene una ficha eliminada y del Service sin el Request | `VeterinarianRestoreTest::test_the_service_rejects_a_user_of_another_clinic` |
| 5 | Baja | `Veterinarian` usaba el string `'active'` como valor por defecto; la bajada de la migración sin matrículas largas no tenía test | `VeterinarianStatus::ACTIVE->value`; test en MySQL de la bajada y la nueva subida | `VeterinarianLicenseMigrationTest::test_rolling_back_without_longer_license_numbers_narrows_the_column_and_keeps_them` |

La colección de Postman suma 4 pedidos (05b, 16b, 16c y 16d): de 28 a 32 requests.

La revisión confirmó además: la lógica en los Services; errores con `VeterinarianException`, `UserException` o 422 de validación,
sin 500 (dos pedidos con la misma matrícula, 422 por el índice); el orden de bloqueo clínica → usuario → veterinario → serie,
compatible con `UserService`; el código `VET-` correlativo sin huecos por la restauración; la migración y su bajada; quitar el rol a un
usuario con la ficha eliminada se permite; un veterinario de otra clínica no aparece en el listado ni se restaura; la recepción y el
veterinario listan y reciben 403 en el alta, el detalle, la edición y la baja.

#### Validación del usuario (R-037)

El 2026-11-09 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-veterinarios`: 75 de 75
pruebas, 0 errores (32 requests). Suite sobre el mismo código: la del cierre (797 casos; 9217 aserciones en SQLite con 3 omitidos y
9237 en MySQL). La baja con uso no está en la colección: la cubre `VeterinarianDeleteTest` y se agrega en la FASE 3 con las agendas.
Con este cierre el módulo de veterinarios queda COMPLETADO; la FASE 2 sigue con la 2.4 (servicios).

### Entrega 2.4

Implementación en `feature/fase-02-04-servicios`, creada desde `feature/fase-02-03-veterinarios`, revisada en una sesión de Claude
Code aparte de la implementación, corregida ([correcciones de la revisión](#correcciones-de-la-revisión-r-039)) y validada por el
usuario ([validación del usuario](#validación-del-usuario-r-039)). La implementación y las correcciones quedaron en dos commits WIP
(`a2079a2` y `a5fad25`), y el cierre va en un commit aparte, con la corrección de la 2.3 (abajo). Decisiones en
[R-038](../decisions/registro.md) y R-039.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `ServiceController`; `IndexServiceRequest`, `StoreServiceRequest`, `UpdateServiceRequest` y `ServiceMessages` (con su `NAME_REGEX`, R-039); `ServiceResource` y `ShowServiceResource`; `ServiceCatalogService`; `App\Rules\WholeAmount` (el precio entero); `tests/Feature/Services` (`ServiceStoreTest`, `ServiceUpdateTest`, `ServiceDeleteTest`, `ServiceIndexTest`, `ServiceLockTest`) y `tests/Concerns/InteractsWithServices`; `docs/testing/postman/syncvet-fase-02-servicios.postman_collection.json` |
| Cambiados | `routes/api.php` (`apiResource('services')`), `Service` (deuda 2, abajo), `lang/es/validation.php` (`category`, `description`, `duration_minutes`, `standard_price`, `tax_rate`); en el cierre, `VeterinarianService` (corrección de la 2.3, abajo) |
| Tests cambiados | `RoleAccessTest` (parámetro `service`), `RouteBindingTest` (servicio de otra clínica → 404 en cada ruta, sin cambios), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas y `WholeAmount`, con su propio mensaje); en el cierre, `VeterinarianStoreTest` |
| Documentación | `CLAUDE.md` §1, §3 (orden clínica → servicio → serie y las violaciones de índice único por el nombre del índice), §4 (`SERVICE_NAME_NOT_EDITABLE`, `SERVICE_IN_USE`) y §6.7 nueva, servicios (las pendientes de diseño pasan a §6.8); la guía de Postman; este documento, el de la [FASE 3](fase-03-agenda-y-turnos.md) (un servicio usado en Postman), el registro (R-038 y R-039), la deuda técnica (2, revisada en `Service`), el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 46 a 51; los permisos siguen en 123; las migraciones, en 34 (`services.name` de 150 admite 120 más
el sufijo).

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `services` | `services.index` | 200, paginado, `ServiceResource`; activos salvo `include_inactive=1`; `status` (manda), `category` y `search` | 422 |
| POST | `services` | `services.store` | 201, `ShowServiceResource` | 409 `DOCUMENT_CODE_CONFLICT`; 422 |
| GET | `services/{service}` | `services.show` | 200, `ShowServiceResource` (suma `description` y las fechas) | 404 (también de otra clínica) |
| PATCH/PUT | `services/{service}` | `services.update` | 200 | 404; 422 `SERVICE_NAME_NOT_EDITABLE`; 422 |
| DELETE | `services/{service}` | `services.destroy` | 200, `data: null` | 404; 409 `SERVICE_IN_USE` |

El administrador escribe; la recepción y el veterinario listan y ven el detalle (permisos que ya tenían) y reciben 403 al escribir.

**Tests.** Suite del 2026-11-09, después de la corrección de la 2.3 en el cierre: 859 casos con los *data providers*; en
SQLite, 9869 aserciones con 3 omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 9889
aserciones sin omitidos. Frente al cierre de la 2.3 (797 casos; 9217 y 9237 aserciones): 62 casos más, y 652 aserciones más en SQLite y en MySQL. La tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `ServiceStoreTest` | 37 | 174 | Alta con `SRV-` correlativo, el nombre limpio, sus datos y la auditoría; valores por defecto (30, 10 y `active`); nombre repetido sin mayúsculas, acentos ni espacios (también frente a un inactivo) → 422, el de otra clínica libre; `code` → 422; el código repetido → 409 `DOCUMENT_CODE_CONFLICT`, no el 422 del nombre; el índice como respaldo del nombre en el alta y la edición (422); precio entero de 0 a 999.999.999.999 (decimales, `.0` de JSON, negativos, exponentes, separadores, floats, booleanos y textos → 422); duración de 5 a 720 en múltiplos de 5; tasa de IVA, categoría y estado; el formato del nombre (`/`, `,`, `+` y `%` aceptados; `//`, el sufijo de baja, otros símbolos y sin letras → 422); la recepción y el veterinario listan y ven el detalle y reciben 403 al escribir |
| `ServiceUpdateTest` | 6 | 69 | `PATCH` y `PUT` parciales y la auditoría; `code` → 422 y las reglas del alta; el nombre de otro → 422 y el propio con otras mayúsculas, sin uso, cambia; con uso en cada tabla de `USAGE`, el nombre no cambia (422 `SERVICE_NAME_NOT_EDITABLE`, también solo las mayúsculas) y el precio, el IVA, la duración, la categoría y el estado sí (200) |
| `ServiceDeleteTest` | 6 | 32 | Baja sin uso con el sufijo en el nombre (libre para un alta nueva) y 404 después; el nombre de 120 con el sufijo entero; con uso en cada tabla de `USAGE` → 409 sin cambios; `USAGE` cubre toda clave foránea a `services` |
| `ServiceIndexTest` | 4 | 44 | Activos de la clínica sin eliminados, paginados y por nombre, con `category` como `{value, label}` y `tax_rate` como número; `status` manda (también con `include_inactive=1`), `include_inactive` y `category`; `search` literal en código y nombre; filtros mal formados → 422 |
| `ServiceLockTest` | 3 | 21 | Alta: la clínica antes de leer los nombres y la serie al final; cambio de nombre: clínica → servicio antes de revisar el uso y los nombres; baja: el servicio antes de revisar el uso |

**Mutaciones.** Las cinco del diseño, en la implementación (R-038), cada una con tests que fallan: quitar el chequeo del nombre usado
(4), el chequeo de uso de la baja (4), el sufijo de baja (2), la regla de enteros del precio (15) y el bloqueo de la clínica en el
alta (1, `ServiceLockTest`). En las correcciones, cada una con un test que falla: volver a buscar la palabra `name` en el mensaje de
la excepción, quitar el respaldo del índice del nombre y quitar la exclusión de `//` del nombre. En el cierre, en `VeterinarianService`:
volver a buscar `license_number` en el mensaje y quitar el respaldo del índice de la matrícula.

**Deuda 2.** `Service` revisado contra la tabla y las claves foráneas: relaciones y casts correctos; suma el cast de
`duration_minutes` a entero, los valores por defecto de las columnas en `$attributes` (un servicio recién creado ya los muestra) y el
nombre limpio al guardar (`CatalogName::clean`), como sucursales, especies y razas.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | Obligatorios en el alta: `name`, `category` y `standard_price`; la duración (30), el IVA (10) y el estado (`active`) toman su valor por defecto | Que un servicio no quede con precio 0 por olvido | Implementación; aceptado por el usuario |
| 2 | Cuenta como cambio de nombre solo lo que cambia el nombre ya limpio: reenviar el mismo con espacios de más pasa; cambiar solo las mayúsculas es un cambio (422 si está usado) | El formulario manda todos los campos | Implementación; aceptado por el usuario |
| 3 | El nombre usaba la regla de sucursales y especies (`CatalogMessages::NAME_REGEX`): sin `/` ni `%` ("Consulta/urgencia" → 422) | El diseño no fijaba el formato | Implementación; **resuelto** a pedido del usuario en la revisión: regex propio de servicios (R-039) |
| 4 | El precio sale como texto con dos decimales (`"150000.00"`), como la comisión de los veterinarios; se guarda con BCMath | Igual que los demás importes | Implementación; aceptado por el usuario |
| 5 | Un número de JSON con `.0` (`150000.0`) → 422 (con un test que escribe el cuerpo JSON a mano); un texto con espacios en los extremos (`" 1500"`) se acepta, porque `TrimStrings` los quita antes de validar | La regla trabaja sobre lo que llega después del middleware | Implementación; aceptado por el usuario |
| 6 | Orden del listado por nombre e id | El diseño no lo fijaba | Implementación; aceptado por el usuario |
| 7 | La colección de Postman no tiene rango de corrida propio: las demás ya cubren de 000000 a 999998. El número de la corrida va solo en los nombres de sus servicios, que ninguna otra colección crea, y la corrida los da de baja | No hay rango de seis dígitos libre | Implementación; aceptado por el usuario |
| 8 | El Service se llama `ServiceCatalogService` | `ServiceService` era confuso | Implementación; aceptado por el usuario |

#### Correcciones de la revisión (R-039)

La revisión, en una sesión aparte de la implementación, encontró un error de comportamiento (hallazgo 1), el desvío 3 para decidir y
dos huecos de tests. Lo que el usuario decidió:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | `withUniqueName` reconocía el choque del nombre buscando `'name'` en el mensaje de la excepción, que trae el SQL del insert y siempre nombra la columna: un código `SRV-` repetido respondía 422 "Ya hay un servicio con ese nombre." en lugar de 409 `DOCUMENT_CODE_CONFLICT` | `ServiceCatalogService::isNameViolation()`: por el índice (`services_clinic_id_name_unique` en MySQL, `services.name` en SQLite); otra violación sigue su camino y la del código llega a `DocumentSequenceService`. Regla nueva en `CLAUDE.md` §3 | `ServiceStoreTest::test_a_repeated_code_answers_409_and_not_the_name_error` (con la serie simulada) y `test_the_unique_index_backs_up_the_name_with_the_same_422` (alta y edición, con la fila simultánea cargada en el evento del modelo) |
| 2 | Media | Desvío 3: "/", ",", "+" y "%" se rechazaban. La única razón era el sufijo de baja (`//deleted_…`), que nada interpreta en los servicios; el riesgo real era un nombre con `//deleted_<id>_<ts>` que chocara en el índice con el de una baja | `ServiceMessages::NAME_REGEX`: al menos una letra, sin `//`, y letras, números, espacios y `. , - ' ( ) / + %`; el mensaje de `name.regex` lo dice. `CatalogMessages::NAME_REGEX` no cambia | `ServiceStoreTest::test_the_name_accepts_slashes_commas_plus_and_percent` ("Castración macho/hembra", "Vacuna 5+1", "Consulta, control", "Análisis 100%" → 201) y los casos `//`, el sufijo de baja, otro símbolo y sin letras de `test_the_name_is_validated` (422) |
| 3 | Baja | El servicio usado que cambia la tasa de IVA, la duración y la categoría no tenía test | El PATCH del test suma `tax_rate`, `duration_minutes` y `category` (200) | `ServiceUpdateTest::test_a_used_service_keeps_its_name_and_changes_its_price` |
| 4 | Baja | `include_inactive=1&status=inactive` no tenía test | Aserción nueva: solo los inactivos | `ServiceIndexTest::test_status_include_inactive_and_category` |
| 5 | Informativo | El valor anterior de `standard_price` en la auditoría sale de `getRawOriginal` (`AuditLogger`): en SQLite, un número (`150000`); en MySQL, texto (`"150000.00"`). Sin floats | Sin cambios | — |

La colección de Postman suma 2 pedidos (25, alta con "/" en el nombre, y 26, su baja): de 25 a 27 requests y de 61 a 64 pruebas.

**Corrección de la 2.3 en el cierre.** `VeterinarianService::withUniqueLicense` tenía el mismo error del hallazgo 1: buscaba
`license_number` en el mensaje, así que un código `VET-` repetido respondía 422 "Ya hay un veterinario con esa matrícula.", aunque el
pedido no trajera matrícula. Ahora `isLicenseViolation()` lo reconoce por el índice
(`veterinarians_clinic_id_license_number_unique` en MySQL, `veterinarians.license_number` en SQLite). Tests nuevos en
`VeterinarianStoreTest`: `test_a_repeated_code_answers_409_and_not_the_license_error` (409 `DOCUMENT_CODE_CONFLICT` con la serie
simulada, con y sin matrícula) y `test_the_unique_index_backs_the_license_number_on_store_and_update` (el 422 del índice en el alta y
la edición; el de la restauración ya estaba en `VeterinarianRestoreTest`). No queda otro Service que identifique violaciones por el
texto del mensaje: `DocumentSequenceService` ya lo hacía por el índice.

La revisión confirmó además: la lógica en el Service; errores con `ServiceException` o 422 de validación, sin 500; el precio sin
floats en el Request, el Service, el Resource y la auditoría; los bloqueos (clínica en el alta y el cambio de nombre, el servicio en
la edición y la baja, la serie al final); `USAGE` cubre toda clave foránea a `services`; el nombre de un servicio eliminado queda libre
y el índice `(clinic_id, name)` no choca (dos bajas del mismo nombre en el mismo segundo, sufijos distintos); `category` como
`{value, label}` y `tax_rate` como número en el listado y el detalle; la recepción y el veterinario listan y ven el detalle (200) y
reciben 403 al escribir.

#### Validación del usuario (R-039)

El 2026-11-09 el usuario corrió en Postman (Collection Runner, una iteración) la colección `syncvet-fase-02-servicios`: 64 de 64
pruebas, 0 errores (27 requests). Suite: la del cierre, sobre el código con la corrección de la 2.3 (859 casos;
9869 aserciones en SQLite con 3 omitidos y 9889 en MySQL). Un servicio usado no está en la colección:
lo cubren `ServiceDeleteTest` y `ServiceUpdateTest`, y se agrega en la FASE 3 con las agendas y los turnos. Con este cierre el módulo
de servicios queda COMPLETADO; la FASE 2 sigue con la 2.5 (configuración de la clínica, logo y archivos huérfanos).

### Entrega 2.5

Implementación en `feature/fase-02-05-configuracion`, creada desde `feature/fase-02-04-servicios`, con una corrección de la primera
validación con Postman (un 500), revisada en una sesión de Claude Code aparte de la implementación, corregida
([correcciones de la revisión](#correcciones-de-la-revisión-r-041)) y validada por el usuario
([validación del usuario](#validación-del-usuario-r-041)). La implementación, la corrección del 500 y las correcciones de la revisión
quedaron en tres commits WIP (`fe10d2b`, `62b160e` y `f0b906a`), y el cierre va en un commit aparte. Decisiones en
[R-040](../decisions/registro.md) y R-041.

**Archivos.**

| Tipo | Archivos |
|---|---|
| Nuevos | `SystemSettingsController` y `ClinicLogoController`; `UpdateSystemSettingsRequest`, `UploadClinicLogoRequest` y `PrivateImageRequest` (base abstracta, también de la foto); `SystemSettingsResource`; `SystemSetting`; `SystemSettingKey`; `SystemSettingsService`, `ClinicLogoService` y `OrphanFileService`; `App\Support\PrivateImageStore`; `PruneOrphanFilesCommand` (`files:prune-orphans`); `tests/Feature/Settings` (`SystemSettingsTest`, `ClinicLogoTest`, `PruneOrphanFilesTest` y `ClinicPaths`); `docs/testing/postman/syncvet-fase-02-configuracion.postman_collection.json` y, en `postman/archivos`, `logo-clinica.png` y `logo-clinica.webp` |
| Cambiados | `routes/api.php` (cinco rutas), `routes/console.php` (la limpieza a las 03:00), `config/filesystems.php` (disco `clinic_logos`), `RolesAndPermissionsSeeder` (cuatro permisos), `PatientPhotoService` y `UploadPatientPhotoRequest` (sobre `PrivateImageStore` y `PrivateImageRequest`), `MeResource` (`clinic.has_logo`), `CatalogLock` (devuelve la clínica), `DemoSeeder` (`MARKER` del enum), `lang/es/validation.php` (los campos nuevos); en las correcciones, `Auditable` y `AuditLogger` (`auditValues()`) y el comentario de la migración de `system_settings` |
| Tests cambiados | `RoleAccessTest` (con un logo para ver y borrar), `LoginTest` y `MeTest` (`has_logo`), `ValidationTranslationsTest` (pedidos inválidos a las rutas nuevas; salta las bases abstractas), `tests/Fixtures/clinic-roles.json` (los permisos de cada rol). `PatientPhotoTest`, sin cambios |
| Documentación | `CLAUDE.md` §1, §3 (orden clínica → configuración), §4 (`CLINIC_LOGO_NOT_FOUND`, la descarga del logo y el 413 del logo), §6.2 (la foto sobre `PrivateImageStore`, deuda 15), §6.8 nueva, configuración, logo y archivos huérfanos (las pendientes de diseño pasan a §6.9), y §7 (deuda 14 resuelta, 15 nueva); `development/setup.md` (disco `clinic_logos` y la limpieza); la guía de Postman; este documento, el de la [FASE 10](fase-10-reportes-y-piloto.md) (el cron del *scheduler*), el registro (R-040 y R-041), la deuda técnica, el handoff y los documentos de estado |

**Endpoints.** Las rutas pasan de 51 a 56; los permisos, de 123 a 127; las migraciones siguen en 34.

| Método | Ruta | Permiso | Respuesta | Errores |
|---|---|---|---|---|
| GET | `system-settings` | `system-settings.show` (nuevo) | 200, `SystemSettingsResource`: `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (cada clave editable, `null` sin valor) y `has_logo` | — |
| PATCH/PUT | `system-settings` | `system-settings.update` | 200, semántica parcial | 422 (clave desconocida, regla de cada clave, `ruc` y `ruc_dv`) |
| POST | `system-settings/logo` | `system-settings.upload-logo` (nuevo) | 200, `SystemSettingsResource` | 413 `PAYLOAD_TOO_LARGE`; 422 |
| GET | `system-settings/logo` | `system-settings.show-logo` (nuevo) | 200, el archivo (tipo guardado, `nosniff`, CSP, `no-store`) | 404 `CLINIC_LOGO_NOT_FOUND` |
| DELETE | `system-settings/logo` | `system-settings.delete-logo` (nuevo) | 200, `SystemSettingsResource`; con una ruta inválida, limpia la referencia sin tocar archivos (R-041) | 404 `CLINIC_LOGO_NOT_FOUND` (sin logo) |

El administrador escribe; la recepción y el veterinario leen la configuración y ven el logo, y reciben 403 al escribir. `me` y el
login suman `clinic.has_logo`.

**Tests.** Suite del 2026-11-09, sobre el código de `f0b906a` (el cierre solo cambia documentación): 921 casos con los *data
providers*; en SQLite, 10477 aserciones con 3 omitidos (solo de MySQL); contra MySQL (`syncvet_test_mysql`), 10497 aserciones sin
omitidos. Frente al cierre de la 2.4 (859 casos; 9869 y 9889 aserciones): 62 casos más, y 608 aserciones más en SQLite y en MySQL. La
tabla cuenta casos (SQLite).

| Clase | Tests | Aserciones | Verifica |
|---|---|---|---|
| `SystemSettingsTest` | 26 | 122 | Lectura por los tres roles (sin la ruta del logo); edición parcial con `PATCH` y `PUT` (el correo en minúsculas) y 403 para los demás; `null` borra la fila, y la auditoría (alta y baja; el mismo valor no es un cambio; la clave siempre); la de los datos de la clínica; clave desconocida y `logo_path` → 422 sin cambios a medias; la regla y los límites de cada clave; `ruc`, `ruc_dv`, `name` y `legal_name`; aislamiento por clínica; la clínica bloqueada antes de leer las claves; una clave desconocida en la base (200, aviso, fila intacta: el 500 de la validación); la marca del `DemoSeeder`; una clave en otras mayúsculas (SQLite y MySQL) |
| `ClinicLogoTest` | 29 | 240 | Subida de JPG, PNG y WEBP con nombre al azar en su disco privado; sin EXIF; texto con extensión de imagen, SVG, GIF y JPEG roto → 422; obligatorio y más de 25 megapíxeles → 422; 2 MB aceptado y más → 413; el cortado por PHP → 413; reemplazo que borra el anterior, solo después del commit, y la transacción que falla deja el anterior; descarga con sus cabeceras; los otros roles lo ven y no lo cambian (403); sin logo → 404; borrado; una ruta fuera de la carpeta → 404 al verla, y el borrado limpia la referencia sin tocar archivos; archivo perdido → 404 con aviso; `has_logo` en `me` y en la configuración, sin la ruta; el logo de otra clínica; el bloqueo de la clínica; la auditoría con `"(archivo)"` y sin rutas |
| `PruneOrphanFilesTest` | 7 | 50 | Borra los huérfanos viejos de fotos y del logo, y conserva el `photo_path` de un paciente (también eliminado), el logo vigente y lo de menos de 24 horas; `--dry-run` informa lo mismo que borra y no borra; nada fuera de las carpetas, aunque una ruta de la base apunte afuera o tenga `..`; clínicas X y X0 y un `photo_path` cruzado entre clínicas; un borrado que falla (log y código 1); una clínica que falla (log, nada borrado en ella, las demás siguen, código 1); programado a las 03:00 |

**Mutaciones.** Las cinco del diseño, comprobadas en el cierre, cada una con tests que fallan: quitar la validación de la ruta del
logo en la descarga, quitar el umbral de 24 horas, no contar los pacientes eliminados, quitar el bloqueo de la clínica al reemplazar el
logo y aceptar una clave fuera de la lista. En la revisión, `withoutGlobalScopes()` en la limpieza no hacía fallar ningún test; con las
correcciones falla `test_each_clinic_keeps_only_the_files_its_own_patients_use`. En las correcciones, cada una con un test que falla:
quitar el `"(archivo)"` de la auditoría, dejar vacío `auditContext()`, volver al 404 en el borrado con una ruta ajena, no capturar la
falla de una clínica en la limpieza y comparar la clave solo de forma exacta al escribir (en MySQL).

**Deuda 2.** Sin modelos generados en esta entrega: `SystemSetting` es nuevo y `Clinic` no cambia.

**Desvíos del diseño.**

| # | Desvío | Motivo | Quién |
|---|---|---|---|
| 1 | `demo_data` es una clave interna de `SystemSettingKey`, como `logo_path`: no se edita (422) ni sale en la respuesta | El `DemoSeeder` ya marcaba sus clínicas con esa clave (R-010), y la lectura daba 500 | Corrección del 500; aceptado por el usuario |
| 2 | Una fila con una clave fuera del enum se ignora al leer, con un aviso en el log, y no se borra | El diseño no decía qué hacer con una clave cargada sin la API | Corrección del 500; aceptado por el usuario |
| 3 | `DELETE system-settings/logo` con un `logo_path` fuera de la carpeta limpia la referencia (200) sin tocar archivos; el diseño decía 404 | Sin eso, el administrador no podía sacar un logo roto | Revisión; decidido por el usuario (R-041) |

#### Correcciones de la revisión (R-041)

**El 500 de la primera validación con Postman.** `GET system-settings` respondía 500 en la base de desarrollo: el `DemoSeeder` marca
sus clínicas con la clave `demo_data` en `system_settings` (R-010), que no estaba en `SystemSettingKey`, y el cast `key` del modelo
lanzaba un `ValueError` al leer todas las filas. Corrección (`62b160e`): la lectura toma las filas sin el modelo y una clave
desconocida se ignora con un aviso; `demo_data` pasa a ser una clave interna (desvíos 1 y 2). Tests:
`SystemSettingsTest::test_an_unknown_key_in_the_database_is_ignored_with_a_warning` (sin la corrección, 500) y
`test_the_demo_seeder_marker_is_an_internal_key`.

La revisión, en una sesión aparte de la implementación, encontró lo siguiente. Lo que el usuario decidió:

| # | Gravedad | Hallazgo | Corrección | Test que falla sin la protección |
|---|---|---|---|---|
| 1 | Media | La ruta del logo quedaba en `audit_logs`, y una modificación guardaba solo `{"value": …}`, sin la clave: la 2.6 no podría ocultarla | `SystemSetting::auditValues()` guarda `"(archivo)"` en el alta, la modificación y la baja de `logo_path`; `auditContext()` suma siempre la clave. Gancho nuevo `Auditable::auditValues()`, que aplica `AuditLogger` | `ClinicLogoTest::test_the_change_is_audited_without_the_path` y `SystemSettingsTest::test_the_audit_of_a_key_always_has_the_key` |
| 2 | Media | El test de "no confunde archivos entre clínicas" pasaba con `withoutGlobalScopes()` en la limpieza | Clínicas con ids X y X0, y un paciente de A con un `photo_path` en la carpeta de B: el archivo se borra como huérfano de B | `PruneOrphanFilesTest::test_each_clinic_keeps_only_the_files_its_own_patients_use` |
| 3 | Baja | Con un `logo_path` inválido, `has_logo` es `true`, verlo y borrarlo dan 404, y el logo roto solo se sacaba subiendo otro | `has_logo` queda como `has_photo`; el borrado limpia la referencia (200) sin tocar archivos (desvío 3). La foto, deuda 15 | `ClinicLogoTest::test_a_logo_path_outside_the_clinic_folder_is_not_used` |
| 4 | Baja | Una fila con la clave en otras mayúsculas: en MySQL, el `PATCH` escribía en ella y la lectura la ignoraba (el valor se perdía; la revisión lo informó como un 500 y no lo era). Comparar solo exacto habría chocado con el índice único (un 500) | Al escribir, la fila exacta o la de otras mayúsculas, que queda con la clave del enum; al leer, exacta | `SystemSettingsTest::test_a_key_stored_in_other_case_does_not_break_the_edition` (en MySQL) |
| 5 | Baja | Un enlace simbólico (o un error del disco) en una carpeta cortaría la limpieza de todas las clínicas | Cada clínica se revisa entera antes de borrar en ella; si falla, log, nada borrado en ella y las demás siguen; código 1 | `PruneOrphanFilesTest::test_a_clinic_that_fails_is_skipped_and_the_others_are_cleaned` |
| 6 | Baja | `ClinicPaths` dentro de `SystemSettingsTest.php` (PSR-4); comentarios viejos en `DemoSeeder` y en la migración; `in_array` sobre todas las rutas | Archivo propio, comentarios corregidos, `array_flip` | — |
| 7 | Baja | El 413 del logo cortado por PHP (`UPLOAD_ERR_INI_SIZE`) no tenía test | Test nuevo, como el de la foto | `ClinicLogoTest::test_a_file_cut_by_php_answers_413` |

La revisión confirmó además: la lógica en los Services; errores con `ClinicException` o 422 de validación; el bloqueo clínica →
configuración en la edición, la subida y el borrado; los cuatro permisos en el seeder y en los roles; la foto del paciente sin cambios
con `PrivateImageStore` (respuestas, cabeceras, códigos, mensajes del log y rutas); la ruta del logo fuera de `system-settings` y de
`me`; el reemplazo y el borrado después del commit; el logo de otra clínica o una ruta fuera de su carpeta → 404 sin borrar nada; la
limpieza solo dentro de las carpetas de cada clínica, con lo que conserva, y `--dry-run` del mismo cálculo.

#### Validación del usuario (R-041)

El 2026-11-09 el usuario corrió en Postman (Collection Runner, una iteración), después de las correcciones de la revisión, la colección
`syncvet-fase-02-configuracion`: 67 de 67 pruebas, 0 errores (24 requests); y, como regresión por `PrivateImageStore`, la de la foto
del paciente (`syncvet-fase-01-foto`): 60 de 60, 0 errores. Suite: la del cierre (921 casos; 10477 aserciones en SQLite con 3
omitidos y 10497 en MySQL). Con este cierre el módulo de configuración de la clínica queda COMPLETADO y la deuda 14, resuelta; la
FASE 2 sigue con la 2.6 (auditoría y deuda 13).
