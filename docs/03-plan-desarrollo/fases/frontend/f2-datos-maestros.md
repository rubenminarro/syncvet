# F2 — Datos maestros (frontend)

Etapa: Datos de la clínica. Estado: **APROBADO** por el usuario el 2026-11-16 (R-074), en `docs/front-f2-diseno` (desde `main` en `1daee8c`, el merge de
`chore/post-f1`), con las [preguntas del diseño](#preguntas-del-diseño) respondidas: todas las recomendaciones, también la entrega
front-02 del backend antes de la 2.3. Cada entrega empieza con la autorización del usuario. El estado vigente de cada fase está en [`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Las pantallas de administración de la clínica, sobre la API ya cerrada de la
[FASE 2 del backend](../backend/fase-02-datos-maestros.md): sucursales y depósitos, usuarios (con la inactivación, la baja y la
contraseña de otro usuario), veterinarios, servicios, la configuración de la clínica con el logo y la consulta de la auditoría. Se
usan tal cual las convenciones de la F0 y la F1 ([`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) §5): listados con
`useSearchText` y la búsqueda en la URL, formularios con las reglas de la API y un solo envío, `ConfirmDialog`, la `key` en las
pantallas de detalle, las claves de `query-keys.ts`, el trato por `error_code`, las fechas en `America/Asuncion` y el voseo.

## Lo que da la API (resumen del análisis)

Las reglas completas están en [`backend/CLAUDE.md`](../../../../backend/CLAUDE.md) §6.4 a §6.9; acá, solo lo que decide la
interfaz. Permisos leídos de `tests/Fixtures/clinic-roles.json` (el administrador los tiene todos).

| Recurso | Endpoints | Permisos | Notas para el frontend |
|---|---|---|---|
| Sucursales | `GET branches` (`search` en código y nombre, `include_inactive`, paginado), `POST branches`, `GET/PATCH branches/{branch}` (sin baja) | `branches.index`: los tres roles. `.show`, `.store`, `.update`: solo el administrador | El detalle (`ShowBranchResource`) trae **todos** sus depósitos (también los inactivos), el por defecto primero; `created_at` y `updated_at`. El alta exige `warehouse_code` (y `warehouse_name`, "Principal" si no llega). Los códigos se pasan a mayúsculas en la API |
| Depósitos | `GET/POST branches/{branch}/warehouses` (paginado, `include_inactive`), `PATCH warehouses/{warehouse}` (sin detalle ni baja) | `warehouses.index`, `.store`, `.update`: solo el administrador | `branch_id` en la edición → 422 (`prohibited`). Una sucursal inactiva no suma depósitos: 422 en `branch` (una clave que no es un campo del formulario) |
| Usuarios | `GET users` (`search`, `role`, `branch_id`, `include_inactive`; por apellido), `POST users`, `GET/PATCH/DELETE users/{user}`; `GET roles` (sin paginar, `{name, label}`) | `users.*` y `roles.index`: solo el administrador | El listado (`UserResource`) trae `roles` (`{name, label}`), `branches` (`id`, `code`, `name`), `current_branch_id` e `is_active`; **no** trae la ficha de veterinario (ver [H1](#hallazgos-del-análisis)). El detalle suma `veterinarian` (`id`, `code`, o `null`), `created_at` y `updated_at` |
| Perfil | `PATCH me/password`, `PATCH me/current-branch` | Sin permiso | Ya están en la F0 (`/perfil` y el selector de sucursal) |
| Veterinarios | `GET veterinarians` (`search`, `status`, `include_inactive`, `branch_id`; por código), `POST veterinarians`, `GET/PATCH/DELETE veterinarians/{veterinarian}` | `veterinarians.index`: los tres. `.show`, `.store`, `.update`, `.destroy`: solo el administrador | El listado trae `code`, `user` (`id`, `name`, `email`, `is_active`), `license_number`, `specialty`, `status` (`{value, label}`) y `branches` (las del usuario); **sin** `commission_percentage` ni `notes`, que están solo en el detalle (`"12.50"`, texto) |
| Servicios | `GET services` (`search`, `category`, `status`, `include_inactive`; por nombre), `POST services`, `GET/PATCH/DELETE services/{service}` | `services.index` y `.show`: los tres. `.store`, `.update`, `.destroy`: solo el administrador | `standard_price` sale como texto con dos decimales (`"150000.00"`), `tax_rate` como número (0, 5 o 10), `category` y `status` como `{value, label}`. La lista de categorías **no** tiene endpoint (`ServiceCategory`) |
| Configuración | `GET system-settings`, `PATCH`/`PUT system-settings` (parcial) | `system-settings.show` y `.show-logo`: los tres. `.update`, `.upload-logo`, `.delete-logo`: solo el administrador | `clinic` (`name`, `legal_name`, `ruc`, `ruc_dv`), `settings` (`contact_email`, `contact_phone`, `website`, `document_footer`, cada una `null` sin valor) y `has_logo`. `ruc` y `ruc_dv` → 422 (`prohibited`); `settings.<clave>: null` borra la clave |
| Logo | `POST/GET/DELETE system-settings/logo` (campo `logo`, multipart) | Ver los tres; subir y borrar, el administrador | Como la foto del paciente: el `GET` devuelve el archivo (`private, no-store`). JPG, PNG o WEBP de hasta 2 MB por el contenido y 25 megapíxeles; más grande, 413 `PAYLOAD_TOO_LARGE` con `errors.logo`. Sin logo, 404 `CLINIC_LOGO_NOT_FOUND`. `me.clinic.has_logo` dice si hay |
| Auditoría | `GET audit-logs` (`from`, `to`, `user_id`, `event`, `auditable_type`, `auditable_id`, `search`; por fecha descendente), `GET audit-logs/{auditLog}` | `audit-logs.index` y `.show`: solo el administrador | El listado trae `event` (`{value, label}`), `auditable_type` (el tipo corto: `"patient"`), `auditable_id`, `user` (`id`, `name`, o `null`), `ip_address` y `created_at` (UTC, `Z`). El detalle suma `old_values`, `new_values` y `user_agent`. Las listas de eventos (`AuditEvent`) y de tipos (`AuditLog::AUDITABLE_TYPES`) **no** tienen endpoint |

Todos los listados aceptan `per_page` de 1 a 100 (10 por defecto) y `search` de hasta 100. Ninguna consulta de la fase depende de
la sucursal actual: las claves empiezan con su módulo, sin `branchKey`.

`error_code` de negocio de la fase (todos van por el trato `business` de la tabla de la F0, con lo que suma cada entrega):

| `error_code` | HTTP | Mensaje de la API | Trato propio en la F2 |
|---|---|---|---|
| `BRANCH_ESTABLISHMENT_NOT_EDITABLE` | 422 | "El código de establecimiento no se puede cambiar: la sucursal ya tiene facturas." | Al campo `establishment` (no a la alerta) |
| `BRANCH_DEFAULT_NOT_DEACTIVATABLE`, `WAREHOUSE_DEFAULT_NOT_DEACTIVATABLE` | 409 | "La sucursal (el depósito) por defecto no se puede inactivar: marcá otra (otro) como … por defecto primero." | En el diálogo, con "Cerrar"; el navegador ya oculta "Inactivar" en la por defecto |
| `USER_SELF_ACTION` | 409 | "No podés inactivarte ni darte de baja a vos mismo." | En el diálogo, con "Cerrar"; el navegador ya oculta los dos botones en el propio usuario |
| `USER_LAST_ADMIN` | 409 | "La clínica tiene que tener al menos un administrador activo." | En el formulario (alerta arriba) o en el diálogo de inactivar o de baja, con "Cerrar". El navegador no lo puede saber antes: lo dice la API |
| `USER_IN_USE` | 409 | "El usuario tiene registros a su nombre y no se puede eliminar: inactivalo." | En el diálogo de baja, el mensaje y "Inactivar" (si está activo) |
| `USER_IS_VETERINARIAN` | 409 | "El usuario tiene una ficha de veterinario: eliminala antes de quitarle el rol." | Alerta arriba del formulario, con el enlace a la ficha (`veterinarian.code` del detalle) |
| `VETERINARIAN_IN_USE` | 409 | "El veterinario tiene registros y no se puede eliminar: inactivalo." | En el diálogo de baja, el mensaje y "Inactivar" |
| `SERVICE_NAME_NOT_EDITABLE` | 422 | "El servicio ya se usó y no puede cambiar de nombre: creá otro e inactivá este." | Al campo `name` |
| `SERVICE_IN_USE` | 409 | "El servicio ya se usó y no se puede eliminar: inactivalo." | En el diálogo de baja, el mensaje y "Inactivar" |
| `CLINIC_LOGO_NOT_FOUND` | 404 | "La clínica no tiene logo." | Al ver: el lugar del logo vacío, sin error; al borrar: aviso y se vuelve a pedir la configuración |
| `PAYLOAD_TOO_LARGE` | 413 | (el logo) | Ya está en la tabla de la F0: `errors.logo` al campo; sin `errors`, aviso |

## Hallazgos del análisis

| # | Hallazgo | Propuesta |
|---|---|---|
| H1 | El alta de un veterinario elige un usuario activo con el rol `veterinario` y **sin ficha**, pero `GET users` no dice quién ya tiene ficha (`UserResource` no trae `veterinarian`; solo `ShowUserResource`). El selector ofrecería usuarios que la API rechaza con 422 "Ese usuario ya es veterinario." | [Pregunta 1](#preguntas-del-diseño): una entrega de soporte del backend chica (**front-02**) que pase `veterinarian` (`id`, `code`, o `null`) de `ShowUserResource` a `UserResource`, con la relación cargada en el listado (sin N+1). Sin ella, el selector ofrece todos y el 422 va al campo |
| H2 | Las categorías de servicio (`ServiceCategory`), los eventos de la auditoría (`AuditEvent`) y los tipos auditables (`AuditLog::AUDITABLE_TYPES`) no tienen endpoint: los filtros y los selectores necesitan la lista y su texto | Constantes en el `types.ts` de cada módulo, con el texto en castellano; un test de Vitest lee el archivo PHP del backend (como `src/test/roles.ts` lee `clinic-roles.json` y el test de voseo lee `VoseoTest`) y falla si la lista cambia. Sin endpoints nuevos |
| H3 | `PATCH users/{user}` con `password` (también `null` o vacía) sobre el propio usuario responde 422 "Para cambiar tu contraseña usá tu perfil."; sobre otro, `null` es "obligatoria" | La edición **no manda la clave `password`** salvo que se cambie (excepción a "los opcionales vacíos van en `null`"); con el propio usuario, el formulario no muestra la contraseña y enlaza a `/perfil` |
| H4 | `is_default: false` en la sucursal o el depósito por defecto → 422; "el frontend manda el formulario completo" (§6.4) | En la por defecto, "Por defecto" se muestra marcado y deshabilitado (se manda `true`); en las demás, marcarlo la pasa a ser la por defecto |
| H5 | Editar el propio usuario (roles, sucursales, nombre), inactivar una sucursal o cambiar el nombre de la clínica o su logo cambia `me` (permisos, `branches`, la sucursal actual, `clinic.has_logo`) | Esas mutaciones invalidan `['me']` (y, si cambió la sucursal actual, `invalidateBranchQueries`), así el menú, el selector de sucursal y el aviso "Elegí tu sucursal" se actualizan sin recargar |
| H6 | La auditoría filtra por `user_id` de un usuario **eliminado**, pero `GET users` no los lista | El selector del filtro usa `GET users?include_inactive=1`; en cada fila del listado, el usuario es un enlace "solo los de este usuario" que pone `?usuario=` (también de un eliminado, que el selector muestra como "Usuario n.º …") |
| H7 | En los filtros de la auditoría, `to` con hora es el instante exacto (`ApiDateTime::endOf`): "hasta las 18:00" deja afuera lo de las 18:00:30 | [Pregunta 10](#preguntas-del-diseño): "hasta" con hora se manda con los segundos en 59 (incluye todo ese minuto) |
| H8 | La foto del paciente (1.4) ya resuelve el control por el contenido, el tamaño, la vista previa y `getBlob`/`BlobImage`; el logo es lo mismo con 2 MB | En la 2.5, el control de `features/patients/photo.ts` pasa a `src/lib/files/image.ts` con el límite y los mensajes como parámetros, y la foto lo usa sin cambiar su comportamiento (sus tests no cambian) |
| H9 | La barra de menú es horizontal (`SessionLayout`): con seis módulos más, al administrador no le entra en una línea | [Pregunta 2](#preguntas-del-diseño): los módulos de administración (sucursales, usuarios, la clínica y la auditoría) bajo `/configuracion`, con una página de entrada; "Veterinarios" y "Servicios", con su ítem, porque los tres roles los listan |

## Decisiones comunes de la fase

* **URL en castellano** ([pregunta 2](#preguntas-del-diseño)):

  | URL | Entrega |
  |---|---|
  | `/configuracion` (la página de entrada, reemplaza a la provisoria de la 0.2b) | 2.1 |
  | `/configuracion/sucursales`, `/nueva`, `/$id`, `/$id/editar` | 2.1 |
  | `/configuracion/usuarios`, `/nuevo`, `/$id`, `/$id/editar` | 2.2a y 2.2b |
  | `/veterinarios`, `/nuevo`, `/$id`, `/$id/editar` | 2.3 |
  | `/servicios`, `/nuevo`, `/$id`, `/$id/editar` | 2.4 |
  | `/configuracion/clinica` | 2.5 |
  | `/configuracion/auditoria`, `/$id` | 2.6 |

  Las ediciones con `_` (`$id_.editar.tsx`), como en la F1. Cada detalle con su `loader` (el id, entero positivo, sin llamar a la
  API si no; el 404 a `notFound()`) y su página con `key={id}`.
* **Listados**: el modelo de `/clientes` (búsqueda local con `useSearchText`, filtros y página en la URL validados en
  `validateSearch` con todas sus claves, `placeholderData: keepPreviousData`, 20 por página fijo, "Esta página no existe." fuera de
  rango). "Incluir las inactivas" o "los inactivos", como en `/especies` (`?inactivas=1`, `?inactivos=1`). Los filtros de estado de
  veterinarios y servicios (`?estado=activos|inactivos|todos`) se traducen a `status` o `include_inactive=1` en `api.ts`.
* **Formularios**: React Hook Form con Zod, las reglas de forma de cada Form Request con sus mensajes (función pura con tests en el
  borde de cada límite), el largo en caracteres sin `maxLength` nativo donde la API admite `\p{L}`, `applyValidationErrors` para el
  422, Guardar bloqueado con `isSubmitting`, la mutación pendiente y la `ref` de un solo `mutate`, los opcionales vacíos en `null`
  (salvo `password`, H3) y guardar y "Cancelar" con `replace`. Los códigos (`code` de sucursal y depósito) y la matrícula se pasan a
  mayúsculas y sin espacios de los extremos antes de validar y mandar, como la API; el email, a minúsculas.
* **Activar, inactivar y eliminar** ([pregunta 13](#preguntas-del-diseño)): botones en el detalle, con `ConfirmDialog` (`tone`
  `primary` para activar o inactivar, `danger` para eliminar), y no un campo del formulario. El error de negocio va dentro del
  diálogo; con `*_IN_USE`, solo el mensaje de la API y "Inactivar" (sin repetir la pregunta "¿Querés eliminar…?"), como la 1.5. Las
  altas de veterinarios y servicios no muestran el estado (va `active`, el valor por defecto de la API).
* **Claves de las consultas** (`api.ts` de cada módulo):

  | Clave | Qué |
  |---|---|
  | `['branches', 'list', { search, include_inactive, page }]` | `GET branches` |
  | `['branches', 'detail', id]` | `GET branches/{id}` (con sus depósitos) |
  | `['branches', 'options']` | `GET branches?include_inactive=1&per_page=100`, para los selectores y los filtros de usuarios y veterinarios |
  | `['users', 'list', { search, role, branch_id, include_inactive, page }]` | `GET users` |
  | `['users', 'detail', id]` | `GET users/{id}` |
  | `['users', 'options', { role, include_inactive }]` | `GET users?…&per_page=100`: el usuario del veterinario (2.3) y el filtro de la auditoría (2.6) |
  | `['roles']` | `GET roles` (`staleTime: Infinity`: es una lista fija) |
  | `['veterinarians', 'list', { search, status, include_inactive, branch_id, page }]` | `GET veterinarians` |
  | `['veterinarians', 'detail', id]` | `GET veterinarians/{id}` |
  | `['services', 'list', { search, category, status, include_inactive, page }]` | `GET services` |
  | `['services', 'detail', id]` | `GET services/{id}` |
  | `['settings']` | `GET system-settings` |
  | `['settings', 'logo']` | El logo como `Blob` (`staleTime: Infinity`, `gcTime` de 5 minutos, como la foto) |
  | `['audit-logs', 'list', { from, to, user_id, event, auditable_type, auditable_id, search, page }]` | `GET audit-logs` |
  | `['audit-logs', 'detail', id]` | `GET audit-logs/{id}` |

  Invalidaciones (nunca toda la caché): sucursales y depósitos → `['branches']`, `['users']` y `['veterinarians']` (muestran las
  sucursales) y `['me']` (H5); usuarios → `['users']`, `['veterinarians']` (el nombre y `user.is_active`) y, si es el propio,
  `['me']`; veterinarios → `['veterinarians']`, `['users']` (la ficha en el detalle y, con front-02, en el listado) y `['me']` si la
  ficha es del usuario de la sesión (`me.veterinarian`); servicios → `['services']`; configuración → `['settings']` y `['me']`;
  logo → `['settings']`, `['me']` y la caché del archivo (`resetQueries` al reemplazarlo, `Query.reset()` al borrarlo, como la
  foto). La auditoría no se invalida: es de solo lectura (`staleTime` de 0: al volver a entrar, se vuelve a pedir). Los detalles
  borrados que no están a la vista se vacían con `resetQueries`, como en la F1.
* **Permisos.** Cada ruta con `requirePermission`; los botones con `useCan()`. `PERMISSIONS` suma los de la fase con los nombres de
  la API. Los tests, con `meFor(rol)` de los tres roles reales. Las pantallas de `/configuracion/…` son del administrador: aunque el
  recepcionista y el veterinario listan sucursales, no tienen pantalla de sucursales ([pregunta 4](#preguntas-del-diseño)).
* **Menú** ([pregunta 2](#preguntas-del-diseño)): "Servicios" (`services.index`) y "Veterinarios" (`veterinarians.index`), para los
  tres roles, después de "Especies"; "Configuración" (`system-settings.update`, el administrador) al final, como hoy. El ítem queda
  activo en todas las páginas de `/configuracion/…`.
* **Sin paquetes nuevos.** La F2 no instala nada.
* **Código común nuevo** (cada uno en la entrega que lo usa primero): `useSingleAction` en `src/lib/hooks` (2.1, tarea 3c);
  `CheckboxGroupField` en `src/components/ui` (roles y sucursales, 2.2a); `src/lib/files/image.ts` (2.5, H8); la normalización del
  teléfono de clientes, si está en `features/clients`, pasa a `src/lib` en la 2.1 (sucursales) y la usan también la configuración.

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 2.1 | Sucursales y depósitos, la página de entrada de `/configuracion` y lo que queda de la tarea 3c (`useSingleAction` a `src/lib/hooks` y el diálogo de baja del paciente con `PATIENT_IN_USE`) | No | `feature/front-2-1-sucursales`, desde `docs/front-f2-diseno` | COMPLETADO (R-075, [resultado](#entrega-21)) |
| 2.2a | Usuarios: listado, detalle, alta y edición (roles, sucursales, la contraseña de otro usuario, el propio usuario) | No | `feature/front-2-2a-usuarios`, desde `feature/front-2-1-sucursales` | COMPLETADO (R-076, [resultado](#entrega-22a)) |
| 2.2b | Usuarios: inactivar y reactivar, baja, el último administrador y "Incluir los inactivos" | No | `feature/front-2-2b-usuarios`, desde `feature/front-2-2a-usuarios` | COMPLETADO (R-077, [resultado](#entrega-22b)) |
| front-02 | Soporte del backend: la ficha de veterinario en `GET users` (aprobada con la [pregunta 1](#preguntas-del-diseño)) | — | `feature/front-02-usuarios-veterinario`, desde `feature/front-2-2b-usuarios` (el nombre, del usuario en R-077) | COMPLETADO (R-078, [resultado](#entrega-front-02)) |
| 2.3 | Veterinarios: listado (los tres roles), detalle, alta desde el usuario, edición, inactivar y baja | front-02 | `feature/front-2-3-veterinarios`, desde `feature/front-02-usuarios-veterinario` (creada y subida en el cierre de front-02, R-078) | COMPLETADO (R-079, [resultado](#entrega-23)) |
| 2.4 | Servicios: listado y detalle (los tres roles), alta, edición, inactivar y baja; el precio en guaraníes | No | `feature/front-2-4-servicios`, desde `feature/front-2-3-veterinarios` (creada y subida en el cierre de la 2.3, R-079) | COMPLETADO (R-080, [resultado](#entrega-24)) |
| 2.5 | Configuración de la clínica: datos, claves y logo (y el logo en la barra; el menú plegable en pantallas chicas, del desvío 10 de la 2.4) | No | `feature/front-2-5-configuracion`, desde `feature/front-2-4-servicios` (creada y subida en el cierre de la 2.4, R-080) | COMPLETADO (R-081, [resultado](#entrega-25)) |
| 2.6 | Auditoría: listado con filtros (fechas con y sin hora en Asunción) y detalle de los cambios | No | `feature/front-2-6-auditoria`, desde `feature/front-2-5-configuracion` (en el cierre de la 2.5, R-081) | APROBADA (R-074) |

Cada rama sale de la anterior, como en la F1, y todo se integra a `main` al cerrar la F2. Los usuarios van en dos entregas
([pregunta 3](#preguntas-del-diseño)), como en el backend: juntos serían el doble que las demás. front-02 va antes de la 2.3, la
única que la usa. Cada entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md).

## Diseño por entrega

### 2.1 Sucursales y depósitos

**Antes, la tarea 3c** (dos commits propios al empezar la entrega):

* `useSingleAction` (hoy en `features/patients/use-patient-action.ts` y repetido en `features/species/CatalogDialogs.tsx`, desvío
  6 de la 1.5) pasa a `src/lib/hooks/use-single-action.ts`, con sus tests; pacientes y especies la importan de ahí, sin cambiar su
  comportamiento. El movimiento del archivo, en un commit sin cambios de contenido.
* El diálogo de baja del paciente (1.3b, `PatientActions.tsx`) con `PATIENT_IN_USE` deja de decir "¿Querés eliminar a …? No se puede
  deshacer.": muestra solo el mensaje de la API, con "Registrar el fallecimiento" (si no está fallecido) y "Cerrar", como la 1.5
  (desvío 12 de la 1.5). Test del texto con y sin el error.

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion` | `system-settings.update` | La página de entrada: una tarjeta por sección (Sucursales en la 2.1; Usuarios, la Clínica y la Auditoría, cada una en su entrega), solo las que el usuario puede ver |
| `/configuracion/sucursales?buscar=&inactivas=&pagina=` | `branches.show` | Búsqueda ("Buscá por código o nombre"), "Incluir las inactivas"; tabla: código, nombre (enlace), establecimiento, teléfono, "Por defecto" e "Inactiva". "Nueva sucursal" (`branches.store`) |
| `/configuracion/sucursales/nueva` | `branches.store` | El formulario de la sucursal y su depósito por defecto: código y nombre del depósito ("Principal" de ayuda; vacío va `null` y la API pone "Principal") |
| `/configuracion/sucursales/$id` | `branches.show` | Datos, alta y última modificación (`formatDateTime`) y la tarjeta "Depósitos" (del detalle: todos, el por defecto primero, con "Por defecto" e "Inactivo"). Acciones de la sucursal: Editar, "Marcar como por defecto" (si está activa y no lo es) e Inactivar o Activar. De cada depósito: Editar, "Marcar como por defecto" e Inactivar o Activar; "Nuevo depósito" (oculto en una sucursal inactiva) |
| `/configuracion/sucursales/$id/editar` | `branches.update` | El mismo formulario, sin los campos del depósito |

**Formulario.** Código, nombre, establecimiento (tres dígitos), dirección, teléfono y "Sucursal por defecto" (H4: en la por
defecto, marcado y deshabilitado). Los depósitos, en un diálogo (`ConfirmDialog` con `size="md"` y campos, como la 1.3b): código,
nombre y "Depósito por defecto".

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `code`, `warehouse_code` | Obligatorio, hasta 20, `^[A-Z0-9-]+$` después de pasar a mayúsculas y recortar (con los mensajes de `BranchMessages`) | Código en uso en la clínica (también el de un depósito) |
| `name`, `warehouse_name` | 2 a 100, al menos una letra (`CatalogMessages::NAME_REGEX`, como en especies) | Nombre repetido sin mayúsculas, acentos ni espacios (la sucursal en la clínica; el depósito en su sucursal) |
| `establishment` | Obligatorio, `^(?!000)\d{3}$` | En uso; `BRANCH_ESTABLISHMENT_NOT_EDITABLE` (al campo) |
| `address` | Hasta 255, `BranchMessages::ADDRESS_REGEX` | — |
| `phone` | `^\+?[1-9]\d{7,14}$`, sin los espacios y guiones escritos (como clientes) | — |

**Mutaciones.** `POST branches`, `PATCH branches/{id}` (el formulario, `{ is_default: true }` o `{ is_active }`),
`POST branches/{id}/warehouses`, `PATCH warehouses/{id}`; invalidan lo de [Decisiones comunes](#decisiones-comunes-de-la-fase). Al
inactivar la sucursal actual del usuario de la sesión, `me` vuelve sin sucursal actual y el layout pide elegir otra (H5); el diálogo
lo avisa: "Los usuarios que la tienen como sucursal actual van a tener que elegir otra.".

**Errores.** 422 a sus campos (el de `branch`, sucursal inactiva, a la alerta del diálogo); `BRANCH_ESTABLISHMENT_NOT_EDITABLE` al
campo; `*_DEFAULT_NOT_DEACTIVATABLE` en el diálogo con "Cerrar"; 404 en una acción, aviso y vuelta al listado.

**Tests (Vitest).** `validateSearch`; las reglas de Zod en su borde (`.each`); el código en minúsculas mandado en mayúsculas; la
por defecto con el control deshabilitado y `is_default: true` en el cuerpo; el alta con el depósito (`warehouse_name` vacío en
`null`); "Nuevo depósito" oculto en una inactiva; inactivar la por defecto oculto y, con el 409, el mensaje en el diálogo;
`BRANCH_ESTABLISHMENT_NOT_EDITABLE` en su campo; inactivar la sucursal actual invalida `['me']`; el recepcionista y el veterinario
van a `/sin-permiso` y no ven "Configuración"; la tarea 3c (los tests de `useSingleAction` en su lugar nuevo y el diálogo del
paciente).

**Playwright.** Alta de una sucursal con su depósito; marcarla por defecto y ver que la anterior dejó de serlo; volver a la
original; un depósito nuevo, marcado por defecto, y el intento de inactivar la sucursal por defecto (el botón no está) y el depósito
anterior (sí se inactiva).

**Mutaciones a probar.** `is_default: false` mandado en la por defecto; sin invalidar `['me']` al inactivar; "Nuevo depósito" en
una inactiva; el código sin pasar a mayúsculas.

**En el navegador.** El diálogo de depósito con el teclado; inactivar la sucursal actual y ver el aviso "Elegí tu sucursal" sin
recargar; la página de entrada de `/configuracion`; el ancho de tablet.

#### Pendiente para el cierre (2.1)

Vacío: la entrega se cerró (R-075) y todo lo que había acá pasó a su [resultado](#entrega-21).

### 2.2a Usuarios: listado, detalle, alta y edición

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion/usuarios?buscar=&rol=&sucursal=&inactivos=&pagina=` | `users.index` | Búsqueda ("Buscá por nombre, apellido o email"), rol (de `GET roles`), sucursal (`['branches', 'options']`, las inactivas con "(inactiva)") e "Incluir los inactivos"; tabla: nombre (enlace), email, roles (`label`), sucursales (códigos) e "Inactivo". "Nuevo usuario" (`users.store`) |
| `/configuracion/usuarios/$id` | `users.show` | Datos, roles, sucursales (con la actual marcada), la ficha de veterinario (`veterinarian.code`, enlace a `/veterinarios/$id` desde la 2.3; con el rol y sin ficha, "Crear la ficha de veterinario", también desde la 2.3), alta y última modificación. "(vos)" junto al nombre del usuario de la sesión |
| `/configuracion/usuarios/nuevo` | `users.store` | El formulario |
| `/configuracion/usuarios/$id/editar` | `users.update` | El mismo formulario |

**Formulario.** Nombre, apellido, email, roles (`CheckboxGroupField` con `GET roles`), sucursales (`CheckboxGroupField` con las
activas y, al editar, también las inactivas que el usuario ya tiene, con "(inactiva)") y la contraseña:

* **Alta**: "Contraseña" y "Repetí la contraseña", obligatorias.
* **Edición de otro usuario** ([pregunta 6](#preguntas-del-diseño)): "Cambiar la contraseña" (`CheckboxField`) muestra las dos;
  sin marcar, la clave `password` no se manda (H3). Con el aviso "Se van a cerrar todas sus sesiones." (la API revoca sus tokens).
* **Edición del propio usuario**: sin contraseña, con el texto "Tu contraseña se cambia en tu perfil." y el enlace a `/perfil`.

Las mutaciones que llevan una contraseña, con `gcTime: 0` y `reset()` en `onSettled` (convención de la 0.2b).

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `first_name`, `last_name` | Obligatorios, 2 a 100, `PersonName` (la regla de clientes) | — |
| `email` | Obligatorio, hasta 150, solo ASCII, la forma mínima; a minúsculas y recortado antes de mandar | Email en uso en **toda** la base, también de otra clínica o de un eliminado ("Ya hay un usuario con ese email."); el DNS con `email_dns` |
| `roles` | Al menos uno | `USER_LAST_ADMIN`, `USER_IS_VETERINARIAN` |
| `branch_ids` | Al menos una | Una sucursal inactivada mientras tanto (422, `branch_ids.0` va al campo) |
| `password` | 12 o más caracteres, mayúsculas y minúsculas, un número y un símbolo (`Password::defaults()`, con los mensajes de `UserPassword`); la repetición igual | Filtrada (Have I Been Pwned) |

**Mutaciones.** `POST users` (a su detalle), `PATCH users/{id}` (el formulario completo, sin `password` si no cambia).

**El propio usuario** (H5, [pregunta 7](#preguntas-del-diseño)): guardar invalida `['me']`. Si el administrador se quita el rol
`administrador` (con otro administrador activo, si no la API responde `USER_LAST_ADMIN`), primero un `ConfirmDialog`: "Vas a dejar
de ser administrador y no vas a poder volver a esta pantalla."; con el 200, `me` se vuelve a pedir y se va a `/`. Si cambian sus
sucursales y la actual sale, `invalidateBranchQueries`.

**Errores.** 422 a sus campos (`password` de un propio usuario no puede llegar: el campo no está); `USER_LAST_ADMIN`, alerta
arriba; `USER_IS_VETERINARIAN`, alerta con el enlace a la ficha; 404, aviso y vuelta al listado.

**Tests (Vitest).** `validateSearch` (rol desconocido, sucursal que no es un entero); la contraseña en el borde de cada regla
(11 y 12 caracteres, sin símbolo, sin mayúscula); la edición sin "Cambiar la contraseña" no manda `password` (ni `null`); el propio
usuario sin el campo y con el enlace a `/perfil`; la sucursal inactiva que ya tenía en las opciones; el email en minúsculas en el
cuerpo; 422 del email y de `branch_ids.0` a sus campos; `USER_LAST_ADMIN` y `USER_IS_VETERINARIAN`; quitarse el rol de administrador
(el diálogo, `['me']` y la vuelta a `/`); la contraseña fuera de la caché de mutaciones; el recepcionista sin la pantalla.

**Playwright.** Alta de un recepcionista con una sucursal; entrar con él en otro contexto y ver su menú; el administrador le cambia
la contraseña y la sesión del otro contexto termina con "Tu sesión terminó. Ingresá de nuevo."; el email repetido (422 en su campo).

**Mutaciones a probar.** `password: null` en la edición sin cambiarla; la contraseña visible en el propio usuario; sin invalidar
`['me']` al editarse a sí mismo.

**En el navegador.** El formulario con muchas sucursales; el diálogo de quitarse el rol; la pestaña Red: el cuerpo de la edición sin
`password`.

#### Pendiente para el cierre (2.2a)

Vacío: la entrega se cerró (R-076) y todo lo que había acá pasó a su [resultado](#entrega-22a).

### 2.2b Usuarios: inactivar, reactivar y baja

**Acciones en el detalle** (`users.update` y `users.destroy`), ocultas en el propio usuario:

* **Inactivar** (`ConfirmDialog`, `tone="primary"`): "Se van a cerrar todas sus sesiones y no va a poder ingresar hasta que lo
  reactives."; `PATCH users/{id}` con `{ is_active: false }`. Errores en el diálogo con "Cerrar": `USER_LAST_ADMIN` (el último
  administrador activo) y `USER_SELF_ACTION` (si llegara, por un detalle viejo de otra pestaña).
* **Reactivar**: `{ is_active: true }`, con el aviso "Va a tener que ingresar de nuevo." (reactivar no devuelve los tokens).
* **Eliminar** (`tone="danger"`): `DELETE users/{id}`; con el 200, al listado (el detalle se vacía con `resetQueries`). Con
  `USER_IN_USE`, el mensaje de la API y "Inactivar" (si está activo), que cierra este diálogo y abre el de inactivar; con
  `USER_LAST_ADMIN` o `USER_SELF_ACTION`, el mensaje y "Cerrar".

El listado ya tiene "Incluir los inactivos" desde la 2.2a; acá se prueba el ida y vuelta con estas acciones.

**Tests (Vitest).** Los botones ocultos en el propio usuario; inactivar y reactivar (el cuerpo parcial, el aviso, la invalidación de
`['users']` y `['veterinarians']`); la baja con el 200 (a la lista), con `USER_IN_USE` (el botón "Inactivar" y el otro diálogo) y con
`USER_LAST_ADMIN`; el inactivo con su marca en el listado y en el detalle.

**Playwright.** Inactivar al usuario creado en el caso de la 2.2a con su sesión abierta en otro contexto: la sesión termina y el
login responde el mensaje de `USER_INACTIVE`; reactivarlo y entrar; eliminarlo; eliminar al usuario de `vet-a` del `DemoSeeder` (tiene
ficha): `USER_IN_USE` y "Inactivar" en el diálogo (sin confirmarlo).

**Mutaciones a probar.** Los botones visibles en el propio usuario; `USER_IN_USE` sin el botón "Inactivar"; la baja sin vaciar el
detalle.

**En el navegador.** El paso del diálogo de baja al de inactivar con el teclado (el foco); el inactivo en el listado con y sin el
filtro.

#### Pendiente para el cierre (2.2b)

Vacío: la entrega se cerró (R-077) y todo lo que había acá pasó a su [resultado](#entrega-22b).

### front-02 Soporte del backend: la ficha de veterinario en el listado de usuarios

**Aprobada con la [pregunta 1](#preguntas-del-diseño) (R-074).** `veterinarian` (`id`, `code`, o `null` sin ficha o con la ficha
eliminada) pasa de `ShowUserResource` a `UserResource`, y `UserController::RELATIONS` suma `veterinarian`: el listado, el alta, el
detalle y la edición lo traen. Sin rutas, permisos, migraciones ni `error_code` nuevos; aditivo. Tests: el listado con y sin ficha y
con la ficha eliminada (`null`), sin N+1 (cantidad de consultas fija con 1 y con 5 usuarios). Postman: una aserción en la colección
de usuarios. `backend/CLAUDE.md` §6.5 y la [guía de Postman](../../../05-pruebas/guia-postman.md). Al cerrar: `composer test` y Newman
de la colección de usuarios.

#### Pendiente para el cierre (front-02)

Vacío: la entrega se cerró (R-078) y todo lo que había acá pasó a su [resultado](#entrega-front-02).

### 2.3 Veterinarios

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/veterinarios?buscar=&estado=&sucursal=&pagina=` | `veterinarians.index` (los tres) | Búsqueda ("Buscá por código, nombre o matrícula"), estado (Activos, Inactivos, Todos) y sucursal; tabla: código, nombre (enlace al detalle solo con `veterinarians.show`), matrícula, especialidad, sucursales, estado y "Usuario inactivo" (`user.is_active`). "Nuevo veterinario" (`veterinarians.store`) |
| `/veterinarios/$id` | `veterinarians.show` (el administrador) | Código, el usuario (enlace a `/configuracion/usuarios/$id`; "Usuario inactivo"), matrícula, especialidad, comisión ("12,5 %"), sucursales, estado, notas, alta y última modificación |
| `/veterinarios/nuevo?usuario=` | `veterinarians.store` | El formulario con el usuario; `?usuario=` lo deja elegido (el botón "Crear la ficha de veterinario" del detalle del usuario) |
| `/veterinarios/$id/editar` | `veterinarians.update` | El mismo formulario, con el usuario y el código solo para leer |

**El código `VET-` y el usuario.** El código no se escribe ni se manda nunca (`code` → 422): se muestra al volver del 201. El
usuario se elige en el alta de `['users', 'options', { role: 'veterinario' }]` (activos, no eliminados) **sin ficha** (con front-02,
`veterinarian === null`); sin ninguno, "No hay usuarios con el rol veterinario sin ficha." con el enlace a "Nuevo usuario". Si el
usuario tenía una ficha eliminada, la API la restaura con el mismo código (201): el detalle muestra el código de siempre. El
`user_id` no se manda al editar. En el detalle del usuario (2.2a) queda el enlace a la ficha o el botón para crearla.

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `user_id` | Obligatorio | Inactivo, sin el rol o ya veterinario (422 al campo; mensajes de `VeterinarianMessages`) |
| `license_number` | Hasta 30; a mayúsculas y recortada antes de mandar (vacía, `null`) | Repetida en la clínica ("Ya hay un veterinario con esa matrícula.") |
| `specialty` | Hasta 100 | — |
| `commission_percentage` | De 0 a 100 con hasta 2 decimales; acepta coma o punto y se manda como texto con punto (como el peso del paciente); vacío, `0` | — |
| `notes` | Hasta 1000 | — |

**Acciones en el detalle.** Inactivar o Activar (`PATCH` con `{ status }`) y Eliminar (`VETERINARIAN_IN_USE`: el mensaje y
"Inactivar"). Con el 200 de la baja, al listado.

**Código común** (decisión del usuario en la revisión de la 2.2b, desvío 2): la función que trata los errores en los diálogos
(`dialogError`: el 404 al aviso y al listado; el 422 y los errores de negocio, el mensaje y solo "Cerrar"; lo demás, el mensaje y
el botón para probar de nuevo), hoy repetida en `features/branches/BranchDialogs.tsx` y `features/users/UserActions.tsx`, pasa a
código común (`src/lib/api`, con sus tests) y la usan sucursales, usuarios y veterinarios, sin cambiar su comportamiento.

**Tests (Vitest).** `validateSearch` y el estado traducido (`status=inactive`, `include_inactive=1`); el enlace del listado solo
para el administrador; el recepcionista en `/veterinarios/$id` va a `/sin-permiso`; el selector sin los usuarios con ficha y con
`?usuario=`; la matrícula en mayúsculas; la comisión con coma; `code` y `user_id` nunca en el cuerpo de la edición; 422 de la
matrícula; la baja con `VETERINARIAN_IN_USE`.

**Playwright.** Desde el detalle de un usuario nuevo con el rol veterinario, "Crear la ficha de veterinario" y ver el código
`VET-`; la matrícula de `vet-a` en minúsculas (422); inactivar y ver la ficha en "Inactivos"; eliminarla y volver a crearla (el
mismo código); el recepcionista ve el listado sin enlaces ni "Nuevo veterinario".

**Mutaciones a probar.** El selector con los usuarios con ficha; `user_id` mandado al editar; el enlace al detalle para el
recepcionista.

**En el navegador.** El paso del usuario a su ficha y de vuelta; el listado con el estado y la sucursal; el ancho de tablet.

#### Pendiente para el cierre (2.3)

Vacío: la entrega se cerró (R-079) y todo lo que había acá pasó a su [resultado](#entrega-23).

### 2.4 Servicios

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/servicios?buscar=&categoria=&estado=&pagina=` | `services.index` (los tres) | Búsqueda ("Buscá por código o nombre"), categoría y estado; tabla: código, nombre (enlace), categoría, duración ("30 min"), precio (`formatGuaranies`), IVA ("10 %") y estado. "Nuevo servicio" (`services.store`) |
| `/servicios/$id` | `services.show` (los tres) | Los mismos datos, la descripción, alta y última modificación; las acciones, solo para el administrador |
| `/servicios/nuevo`, `/servicios/$id/editar` | `services.store`, `services.update` | El formulario |

**El precio en guaraníes** ([pregunta 8](#preguntas-del-diseño)). Un campo de texto (`inputMode="numeric"`), no `type="number"`:
acepta dígitos y los puntos de miles ("150.000"), que se sacan antes de validar; se manda **como texto de solo dígitos**
(`"150000"`), nunca como número de coma flotante. Rechaza en el navegador, con el mensaje de `WholeAmount` ("El precio debe ser un
número entero, sin decimales, de 0 a 999.999.999.999."), la coma, los decimales, el signo y más de 12 dígitos. Al editar, el
`"150000.00"` de la API se muestra "150.000" (los `.00` se quitan como texto, sin pasar por un número; si viniera con decimales
distintos de cero, se muestra tal cual y la API decide).

**Formulario.** Nombre, categoría (`SelectField` con las constantes de H2), duración (de 5 a 720, de a 5; 30 por defecto), precio,
IVA (`RadioGroup`: 0, 5 o 10 %; 10 por defecto) y descripción.

**Validación.**

| Campo | Zod (navegador) | Solo la API |
|---|---|---|
| `name` | 2 a 120, `ServiceMessages::NAME_REGEX` (en JavaScript con `u` y `s`: al menos una letra, sin `//`, letras, números, espacios y `. , - ' ( ) / + %`) | Repetido sin mayúsculas ni acentos ("Ya hay un servicio con ese nombre."); `SERVICE_NAME_NOT_EDITABLE` (al campo) |
| `category` | Una de la lista | — |
| `duration_minutes` | Entero de 5 a 720, múltiplo de 5 | — |
| `standard_price` | Obligatorio; lo de arriba | — |
| `tax_rate` | 0, 5 o 10 (número en el cuerpo) | — |
| `description` | Hasta 1000 | — |

**Acciones en el detalle.** Inactivar o Activar (`{ status }`) y Eliminar (`SERVICE_IN_USE`: el mensaje y "Inactivar").

**Tests (Vitest).** El precio: "150.000" y "150000" mandan `"150000"`; "150000,50", "150.000,00", "-1", "1e5" y 13 dígitos rechazados
sin pedido; "999.999.999.999" pasa; `"150000.00"` mostrado "150.000" en el formulario y "Gs. 150.000" en la tabla; las reglas del
nombre en su borde ("Vacuna 5+1" y "Castración macho/hembra" pasan, "a//b" no); la duración (4, 5, 722, 720); `SERVICE_NAME_NOT_EDITABLE`
en `name`; la baja con `SERVICE_IN_USE`; el recepcionista ve el detalle sin acciones; la lista de categorías igual a la del backend
(H2).

**Playwright.** Alta de un servicio con el precio "150.000" y verlo "Gs. 150.000" después de recargar; el precio con coma rechazado
sin pedido; cambiar el nombre de un servicio usado por los turnos del `DemoSeeder`, si lo hay (`SERVICE_NAME_NOT_EDITABLE`), o la
baja de uno recién creado.

**Mutaciones a probar.** El precio mandado como número (`Number(...)`); los puntos de miles sin sacar; `tax_rate` como texto.

**En el navegador.** El teclado numérico en el ancho de un celular; el precio largo en la tabla; la pestaña Red con el cuerpo del
alta.

#### Pendiente para el cierre (2.4)

Vacío: la entrega se cerró (R-080) y todo lo que había acá pasó a su [resultado](#entrega-24).

### 2.5 Configuración de la clínica y logo

**Pantalla.** `/configuracion/clinica` (`system-settings.update`), con su tarjeta en `/configuracion`:

* **Datos**: nombre (2 a 100) y razón social (2 a 150); el RUC (`ruc`-`ruc_dv`) solo para leer, con "El RUC no se cambia desde
  acá.". Nunca se mandan `ruc` ni `ruc_dv`.
* **Contacto y documentos**: correo de contacto (hasta 150, a minúsculas, la forma mínima del email), teléfono (la regla de
  clientes), sitio web (hasta 150; empieza con `https://` y `new URL()` lo acepta; el mensaje de la API) y pie de recetas y
  comprobantes (hasta 500). Vacío → `null` (la API borra la clave).
* `PATCH system-settings` con el formulario completo (`name`, `legal_name` y `settings` con las cuatro claves). Con el 200, aviso y
  la invalidación de `['settings']` y `['me']`.

**Logo** (H8). La tarjeta del logo, como la foto de la 1.4: `BlobImage` con `api.getBlob('system-settings/logo', { expectedType: 'image/' })`
si `has_logo`; `FileField` con vista previa y "Subir el logo"; "Quitar el logo" con `ConfirmDialog`. El navegador controla como la
API: 2 MB justos pasan (`2 * 1024 * 1024`, `MAX_KILOBYTES = 2048`) y el tipo por el contenido, con los mensajes de
`UploadClinicLogoRequest`. 413 con `errors.logo` al campo. `CLINIC_LOGO_NOT_FOUND` al ver: el lugar vacío; al borrar: aviso y se
vuelve a pedir la configuración. La mutación con `gcTime: 0` y `reset()`.

**El logo en la barra** ([pregunta 9](#preguntas-del-diseño)). `Brand` muestra el logo (alto de 32 px, `alt` con el nombre de la
clínica) si `me.clinic.has_logo`, para los tres roles (`system-settings.show-logo`), con la misma clave `['settings', 'logo']`: subir
o quitar el logo lo cambia en la barra sin recargar. Sin logo o con un error, la marca de siempre (nunca una imagen rota). Otra
pestaña ve el logo nuevo al recargar (la caché del archivo es de cada pestaña).

**El menú en pantallas chicas** (decisión del usuario en la revisión de la 2.4, desvío 10). Con "Servicios" y "Veterinarios", la
barra de menú horizontal de `SessionLayout` mide unos 820 px y a 375 px se sale de la pantalla (ya pasaba antes de la 2.4, con
unos 700 px). En la 2.5, junto con el logo en la barra: en pantallas chicas, el menú se pliega detrás de un botón, accesible con
el teclado y con lector de pantalla (`aria-expanded`, `aria-controls` y un nombre accesible; Escape lo cierra y el foco vuelve al
botón), sin el JS de Tabler (un componente de React). Tests: el botón y el menú plegado al ancho de un celular, abrir y cerrar con
el teclado; en el navegador, 375 px sin desplazamiento horizontal de la página.

**Tests (Vitest).** `image.ts` con el límite como parámetro (la foto sigue con 5 MB: sus tests no cambian); el logo de 2 MB justos
y de un byte más; el tipo por el contenido; el formulario: `ruc` fuera del cuerpo, las claves vacías en `null`, el email en
minúsculas, el sitio con `http://` rechazado; el 422 de `settings.website` a su campo; `Brand` con y sin logo y con el 404; la caché
del logo vaciada al reemplazarlo y al borrarlo.

**Playwright.** Cambiar el teléfono de contacto y verlo después de recargar (y dejarlo como estaba); subir un logo (`logo-clinica.png` de
`docs/05-pruebas/postman/archivos`, como la foto de la 1.4), verlo en la barra después de recargar, reemplazarlo
(`logo-clinica.webp`) y quitarlo.

**Mutaciones a probar.** `ruc` mandado; el límite de 5 MB en el logo; la barra sin invalidar al quitar el logo.

**En el navegador.** El logo en la barra con un logo ancho y uno alto (sin deformarse); la consola sin errores; el ancho de tablet.

#### Pendiente para el cierre (2.5)

Vacío: la entrega se cerró (R-081) y todo lo que había acá pasó a su [resultado](#entrega-25).

### 2.6 Auditoría

**Pantallas.**

| URL | Permiso | Contenido |
|---|---|---|
| `/configuracion/auditoria?desde=&hasta=&usuario=&evento=&tipo=&registro=&buscar=&pagina=` | `audit-logs.index` | Filtros y tabla: fecha y hora (`formatDateTime`), usuario (enlace "solo los de este usuario", H6; "—" sin usuario), evento (`label`), registro (el tipo en castellano y "n.º 12", enlace que filtra por ese registro) e IP |
| `/configuracion/auditoria/$id` | `audit-logs.show` | Fecha y hora con segundos, usuario, evento, registro, IP, navegador (`user_agent`) y los cambios |

**Filtros de fecha** (en Asunción, nunca con la zona de la PC). "Desde" y "Hasta", cada una con `DateField` y una hora opcional
(`TimeField`):

* **Fecha sola**: se manda `2026-11-09` tal cual; la API la toma como el día entero de Asunción (de 00:00 a 23:59:59).
* **Con hora**: se manda con `toApiDateTime` (`2026-11-09T13:00:00.000-03:00`); "hasta" con los segundos en 59 (H7, pregunta 10).
* En la URL, la hora de pared sin zona (`?desde=2026-11-09T13:00`); `desde` y `hasta` se suman a `TEXT_SEARCH_PARAMS` y
  `validateSearch` descarta lo que no tenga esa forma.
* Zod repite las reglas de la API con sus mensajes: la forma ("Ingresá una fecha (2026-11-09) o una fecha con hora
  (2026-11-09T13:00:00Z).") y "hasta" no anterior a "desde" ("La fecha hasta no puede ser anterior a la fecha desde."), comparando
  las horas de pared como texto (una fecha sola, como 00:00 en "desde" y 23:59:59 en "hasta"), sin pasar por `Date`.

**Los demás filtros.** Usuario (`['users', 'options', { include_inactive: true }]`), evento y tipo (las constantes de H2, con el
texto en castellano), registro (n.º; exige el tipo, con el mensaje de la API "Para filtrar por el id del registro, indicá también su
tipo.") y la búsqueda ("Buscá por el nombre o el email del usuario").

**Los cambios y lo que nunca se muestra.** Una tabla campo, antes y después (`created`: solo después; `deleted`: solo antes); el
campo con el nombre de la API ([pregunta 11](#preguntas-del-diseño)), y cada valor como texto: `null` es "(vacío)", una lista o un
objeto, su JSON con sangría en un `<pre>` (nunca como HTML). La API ya cambia las contraseñas y los tokens por `********` y las rutas
de archivos por `"(archivo)"` (§6.9); el frontend las muestra tal cual y, además ([pregunta 12](#preguntas-del-diseño)), una función
pura `maskAuditValues` cambia por `********` el valor de una clave con `password` o `token` y por `"(archivo)"` el de una clave que
termina en `_path` o un texto con la forma `clinics/{id}/…`, también anidados: si la API un día dejara pasar una, la pantalla no la
muestra.

**Tests (Vitest).** `validateSearch` (fechas mal formadas, `registro` sin `tipo`, un evento o un tipo desconocido); el pedido con la
fecha sola, con hora (`-03:00`) y "hasta" con `:59`, con la PC en UTC; "hasta" antes de "desde" sin pedido; la fila con el usuario
`null`; el filtro por un usuario eliminado ("Usuario n.º …"); el detalle de `created`, `updated` y `deleted`; `maskAuditValues` con
`password`, `remember_token`, `photo_path`, `logo_path` y una ruta anidada; ningún `********` ni ruta reemplazados dos veces; las
listas de eventos y tipos iguales a las del backend (H2); el recepcionista sin la pantalla.

**Playwright.** Editar las notas de un cliente, buscar en la auditoría por hoy, el tipo cliente y su n.º, y ver en el detalle las
notas antes y después; el mismo filtro con una hora "desde" posterior al cambio (la lista vacía); el alta de un usuario en la
auditoría sin su contraseña.

**Mutaciones a probar.** La fecha mandada con `toISOString()` de un `Date` local; "hasta" sin `:59`; `maskAuditValues` sin recorrer
los valores anidados.

**En el navegador.** Los filtros de fecha y hora en el selector nativo; un detalle con `branch_ids` (una lista) y uno largo; la URL
con filtros recargada.

#### Pendiente para el cierre (2.6)

Vacío: la entrega se cerró (R-082) y todo lo que había acá pasó a su [resultado](#entrega-26).

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| front-02 | `backend/CLAUDE.md` §6.5, la [guía de Postman](../../../05-pruebas/guia-postman.md) y su colección, este documento, el roadmap y el handoff |
| 2.1 a 2.6 | `frontend/CLAUDE.md` (las convenciones nuevas: `useSingleAction` común, `CheckboxGroupField`, `image.ts`, el precio en guaraníes, los filtros de fecha; la tabla de errores si cambia), este documento (Resultado), el roadmap, `modulos.md` y el handoff |

## Preguntas del diseño

| # | Pregunta | Opciones | Recomendación | Respuesta |
|---|---|---|---|---|
| 1 | El listado de usuarios no dice quién ya tiene ficha de veterinario (H1). ¿Se suma en el backend? | (a) Entrega de soporte front-02, chica y aditiva; (b) el selector ofrece a todos y el 422 va al campo | **(a)**: el alta ofrece solo a quien se puede elegir y el listado de usuarios puede mostrar el código `VET-` | Aprobada (usuario, 2026-11-16) |
| 2 | ¿Dónde van las pantallas de administración? (H9) | (a) Bajo `/configuracion/…` con una página de entrada, y "Veterinarios" y "Servicios" con su ítem; (b) un ítem del menú por módulo; (c) un menú desplegable "Administración" | **(a)**: la barra horizontal no admite diez ítems, el ítem "Configuración" ya es del administrador, y veterinarios y servicios los listan los tres roles. (c) necesita un desplegable propio (sin el JS de Tabler) | Aprobada (usuario, 2026-11-16) |
| 3 | ¿Usuarios en dos entregas (2.2a y 2.2b)? | Dos; una | **Dos**, como en el backend: el formulario con las contraseñas y el propio usuario por un lado, la inactivación y la baja con sus 409 por el otro | Aprobada (usuario, 2026-11-16) |
| 4 | El recepcionista y el veterinario pueden listar las sucursales (`branches.index`). ¿Tienen pantalla? | Solo el administrador; los tres, solo la lista | **Solo el administrador**: los otros ven sus sucursales en el selector de la barra; el listado de la API queda para los selectores de la F3 | Aprobada (usuario, 2026-11-16) |
| 5 | ¿Los depósitos tienen pantalla propia? | Dentro del detalle de la sucursal, con diálogos; `/configuracion/sucursales/$id/depositos/…` | **Dentro del detalle**: son pocos campos, no hay `GET` de un depósito y el detalle de la sucursal ya los trae todos | Aprobada (usuario, 2026-11-16) |
| 6 | La contraseña de otro usuario al editarlo (H3) | (a) "Cambiar la contraseña" que muestra los campos, con la repetición, el aviso de que se cierran sus sesiones y las reglas de forma en Zod; (b) los campos siempre visibles y opcionales; (c) una acción aparte en el detalle | **(a)**: no se manda nada por error y el aviso aparece justo cuando hace falta. El control de filtraciones, solo la API | Aprobada (usuario, 2026-11-16) |
| 7 | El administrador se quita su propio rol de administrador (con otro activo) | Con confirmación y vuelta a `/`; sin confirmación | **Con confirmación**: pierde el acceso a la pantalla en la que está | Aprobada (usuario, 2026-11-16) |
| 8 | El precio de los servicios | (a) Texto que acepta los puntos de miles ("150.000") y se manda como texto de solo dígitos; (b) solo dígitos, sin puntos | **(a)**: en Paraguay el precio se escribe con puntos; la coma y los decimales se rechazan con el mensaje de la API | Aprobada (usuario, 2026-11-16) |
| 9 | ¿El logo se muestra en la barra? | Sí, para los tres roles; solo en `/configuracion/clinica` | **Sí**: los tres pueden verlo y es la forma de saber en qué clínica se está | Aprobada (usuario, 2026-11-16) |
| 10 | "Hasta" con hora en la auditoría (H7) | Con los segundos en 59 (todo ese minuto); el instante exacto | **Con 59**: "hasta las 18:00" incluye lo de las 18:00 | Aprobada (usuario, 2026-11-16) |
| 11 | Los campos en el detalle de la auditoría | El nombre de la API (`first_name`); un texto en castellano por campo | **El nombre de la API** en la F2: es una pantalla del administrador y una tabla de textos por modelo es mucho para lo que da; se puede sumar después | Aprobada (usuario, 2026-11-16) |
| 12 | ¿El frontend oculta también las contraseñas y las rutas de la auditoría (`maskAuditValues`)? | Sí, además de la API; solo la API | **Sí**: es una función pura chica con sus tests y protege si la API un día deja pasar una | Aprobada (usuario, 2026-11-16) |
| 13 | ¿Activar, inactivar y eliminar como botones del detalle (también el estado de veterinarios y servicios)? | Botones con diálogo; el estado como campo del formulario | **Botones**, como especies: un cambio de estado tiene sus avisos y sus 409, y el formulario queda para los datos | Aprobada (usuario, 2026-11-16) |

## Criterio de cierre

* Las entregas aprobadas cerradas, con Vitest y Playwright en verde, `typecheck`, `lint` y `build` sin errores ni avisos.
* Por front-02: `composer test` y la suite del backend completa en MySQL y en serie, al cerrar la fase.
* Newman de **todas** las colecciones de `docs/05-pruebas/postman` desde la raíz (R-057), sin fallas.

## Resultado

### Entrega 2.1

Estado: **COMPLETADO** el 2026-11-16 (R-075). Sucursales y depósitos, la página de entrada de `/configuracion` y lo que quedaba de
la tarea 3c, en `feature/front-2-1-sucursales` (desde `docs/front-f2-diseno` en `16aec1d`), sin integrar a `main` (se integra al
cerrar la F2). Commits: `7de78f3` y `7e27822` (la tarea 3c), `626759b` (WIP de la implementación), `b30db49` (WIP de la
revisión), `a31b6ef` (WIP del depósito inactivo) y el de este cierre (R-075: la documentación).

**Implementación.**

* **Tarea 3c, resuelta.** `useSingleAction` sale de `features/patients/use-patient-action.ts` a `src/lib/hooks/use-single-action.ts`
  (`7de78f3`), sin cambiar su cuerpo y con dos tests propios; pacientes y especies la importan de ahí, y se borra la copia de
  `features/species/CatalogDialogs.tsx` (desvío 6 de la 1.5). El diálogo de baja del paciente con `PATIENT_IN_USE` muestra solo el
  mensaje de la API, sin "¿Querés eliminar…? No se puede deshacer." (`7e27822`; desvío 12 de la 1.5), con su test con y sin el
  error.
* **Pantallas** (todas del administrador): `/configuracion`, la página de entrada (`frontend/src/app/ConfigurationPage.tsx`), con una tarjeta por
  sección que el usuario puede ver (Sucursales, por ahora); `/configuracion/sucursales` (`branches.show`), con la búsqueda por
  código o nombre, "Incluir las inactivas" y la página en la URL, como `/clientes`; `/nueva` (`branches.store`), con el código y
  el nombre del depósito por defecto (vacío, `null`: la API pone "Principal"); `/$id`, con los datos, el alta y la última
  modificación y la tarjeta "Depósitos"; `/$id/editar` (`branches.update`). En `features/branches`. `PERMISSIONS` suma
  `branchesShow`, `branchesStore`, `branchesUpdate`, `warehousesStore` y `warehousesUpdate`.
* **Reglas.** Las de forma de `StoreBranchRequest`, `UpdateBranchRequest`, los de depósitos y `BranchMessages`, con sus mensajes
  (`features/branches/form.ts`): los códigos sin los espacios de los extremos y en mayúsculas solo ASCII, como `strtoupper`;
  el establecimiento de tres dígitos, de `001` a `999`; el teléfono con la regla de clientes, común en `src/lib/phone.ts`. La
  sucursal y el depósito por defecto: la casilla marcada y deshabilitada, `is_default: true` en el cuerpo, sin "Inactivar"; los
  409 `*_DEFAULT_NOT_DEACTIVATABLE` y los 422 en el diálogo, con solo "Cerrar". Una sucursal o un depósito inactivo: la casilla
  sin marcar y deshabilitada, con "Activá la sucursal para marcarla como la por defecto." o "Activá el depósito para marcarlo
  como el por defecto."; la sucursal inactiva, sin "Nuevo depósito". `BRANCH_ESTABLISHMENT_NOT_EDITABLE`, en su campo; un 404 en
  una acción de la sucursal, aviso y de vuelta al listado; el de un depósito, aviso y la sucursal pedida de nuevo.
* **Caché.** Cada cambio (`afterBranchChanged`) invalida `['branches']`, `['users']`, `['veterinarians']` y `['me']` (el selector
  de la barra usa `me.branches`) y, si cambió la sucursal actual, las consultas de la sucursal: al inactivar la actual, el layout
  pide elegir otra sin recargar.
* **Pruebas.** Vitest: `form.test.ts`, `search.test.ts`, `BranchPages.test.tsx` (con los 6 tests de la revisión y el del depósito
  inactivo), `use-single-action.test.tsx` y el diálogo del paciente. Playwright: `e2e/sucursales.spec.ts` (nuevo: alta con su
  depósito, edición, un depósito nuevo marcado por defecto, el anterior inactivado y la sucursal inactivada) y el caso "el
  administrador entra a /configuracion" de `permisos.spec.ts`, 2 de 2. Mutaciones de la implementación: 4 de 4 detectadas
  (`is_default: false` en la por defecto, sin invalidar `['me']`, "Nuevo depósito" en una inactiva y el código sin pasar a
  mayúsculas).

| # | Desvío | Motivo |
|---|---|---|
| 1 | `useSingleAction` no fue un movimiento de archivo: se sacó de `use-patient-action.ts` (donde sigue `usePatientGone`) en un commit propio, con dos tests nuevos | El hook no tenía archivo propio |
| 2 | `/configuracion` pasó a `frontend/src/routes/_sesion/configuracion/index.tsx`, con el id de la ruta cambiado en el mismo commit | El movimiento solo no compilaba |
| 3 | "Inactivada" e "Inactivado" en lugar de "Inactiva" e "Inactivo" | El test de voseo toma "Inactiva" por el imperativo de "inactivar" (como en especies, 1.5) |
| 4 | El listado junta "Por defecto" e "Inactivada" en una columna "Estado" | Dos columnas de marcas casi vacías |
| 5 | Playwright siguió el caso del pedido de la entrega, no el del diseño (marcar la sucursal por defecto y volver a la original); se tocó también el caso de `/configuracion` de `permisos.spec.ts` | Marcar la por defecto cambia la de toda la base de prueba; lo cubre Vitest |
| 6 | Navegador: 5 controles, no los cuatro temas del diseño; "inactivar la sucursal actual" quedó en Vitest y Playwright | No se inactiva nada en la demo |
| 7 | El diálogo de depósito no se manda con Enter | Dos campos y el botón en el pie, como el del fallecimiento (1.3b) |
| 8 | Los diálogos de estado con un 422 o un error de negocio muestran solo el mensaje y "Cerrar"; el 404 de un depósito, aviso y la sucursal de nuevo | Como la 1.5; el depósito no tiene detalle |
| 9 | `afterBranchChanged` invalida `['users']` y `['veterinarians']`, que todavía no existen | Llegan en la 2.2a y la 2.3 |
| 10 | Cambiado en la revisión: en la edición de una sucursal inactiva, "Sucursal por defecto" queda sin marcar y deshabilitada, con su explicación (antes, habilitada, con el 422 de la API en el campo); la misma regla para el depósito inactivo | Decisión del usuario |

**Revisión (2026-11-16).** Sobre `7de78f3`, `7e27822` y `626759b`, con el presupuesto por entrega: sin hallazgos que cambien el
comportamiento (el movimiento de `useSingleAction`, el diálogo del paciente, la por defecto, el alta con su depósito, la paridad con
la API, la invalidación de `me`, los permisos según `clinic-roles.json` y los diálogos). Hallazgos menores, corregidos en `b30db49`:
el desvío 10 y seis tests que faltaban en `BranchPages.test.tsx` (el doble clic en la confirmación de la sucursal, el doble clic en
"Crear el depósito", el 404 en una acción de la sucursal, el 404 de un depósito, el teclado en el diálogo de depósito y la edición
de una sucursal inactiva). Mutaciones: antes de esos tests, las tres sobrevivían; después, sin la guarda de un solo pedido en
`StatusDialog` y sin la vuelta al listado de `useBranchGone`, detectadas. **Sin la guarda de `WarehouseDialog`, sigue sin
detectarse**: es una mutación equivalente en jsdom, porque el clic llama a `setErrors`, React dibuja el botón en espera en el mismo
evento y `Button` ignora el segundo clic; la `ref` queda como segunda defensa. Después, en `a31b6ef`, la misma regla para el
depósito inactivo, con su test (decisión del usuario).

**Validación.** El usuario dio la entrega por validada con la revisión y sus correcciones, los desvíos 1 a 9 aceptados y el 10
cambiado (R-075). Evidencia: Claude Code, 5 de 5 en Edge sin ventana contra `syncvet.test` (la demo quedó sin cambios); Playwright,
`e2e/sucursales.spec.ts` y el caso de `/configuracion` de `permisos.spec.ts`, 2 de 2; desde `frontend/`, `typecheck`, `lint` y
`build` sin errores ni avisos. La última corrida completa de `npm test` fue sobre `b30db49`; el test del depósito inactivo
(`a31b6ef`) se verificó con `vitest related` (393 tests en 19 archivos, en verde) y ESLint de lo tocado. La sucursal `E2E-…` del caso
de Playwright quedó inactivada en `syncvet_e2e`, que se vacía en cada corrida.

Corrida de `npm test` del frontend (2026-11-16): 909 tests en 46 archivos, en verde.

### Entrega 2.2a

Estado: **COMPLETADO** el 2026-11-16 (R-076). Usuarios: listado, detalle, alta y edición, en `feature/front-2-2a-usuarios` (desde
`feature/front-2-1-sucursales`, en el cierre de la 2.1, `0f7b80b`), sin integrar a `main` (se integra al cerrar la F2). Commits:
`fff201c` (WIP de la implementación), `e8ecdeb` (WIP de la revisión) y el de este cierre (R-076: la documentación).

**Implementación.**

* **Pantallas** (todas del administrador): la tarjeta "Usuarios" en `/configuracion`; `/configuracion/usuarios` (`users.index`),
  con la búsqueda por nombre, apellido o email, el rol (de `GET roles`), la sucursal (`['branches', 'options']`, las inactivas con
  "(inactivada)"), "Incluir los inactivos" y la página en la URL, como `/clientes`; `/$id` (`users.show`), con los datos, los
  roles, las sucursales con la actual marcada, el código de la ficha de veterinario (sin enlace hasta la 2.3) y "(vos)" en el
  usuario de la sesión; `/nuevo` (`users.store`) y `/$id/editar` (`users.update`). En `features/users`. `PERMISSIONS` suma
  `usersIndex`, `usersShow`, `usersStore`, `usersUpdate` y `rolesIndex`.
* **Código común nuevo.** `CheckboxGroupField` en `src/components/ui` (controlado, con `Controller`), para los roles y las
  sucursales; `branchOptionsQueryOptions` (`['branches', 'options']`, `GET branches?include_inactive=1&per_page=100`) en
  `features/branches/api.ts`; la regla de nombres de personas en `src/lib/person-name.ts` (`PERSON_NAME_PATTERN`), que clientes
  importa de ahí sin cambiar su comportamiento.
* **Reglas.** Las de forma de `StoreUserRequest`, `UpdateUserRequest`, `UserMessages` y `UserPassword`, con sus mensajes
  (`features/users/form.ts`): los nombres con `PersonName`, de 2 a 100 caracteres; el email solo ASCII, hasta 150, sin los
  espacios de los extremos y en minúsculas; al menos un rol y una sucursal (las activas y, al editar, también las inactivas que el
  usuario ya tiene); la contraseña con `Password::defaults()` (12 o más caracteres, mayúsculas y minúsculas, un número y un
  símbolo) y su repetición. La contraseña: obligatoria en el alta; al editar a otro usuario, detrás de "Cambiar la contraseña", con
  el aviso "Se van a cerrar todas sus sesiones.", y sin la clave `password` si no se cambia (ni `null`, H3); al editarse a sí
  mismo, sin los campos, con el enlace a `/perfil`.
* **Errores.** 422 a sus campos (`branch_ids.0` a `branch_ids`; al editarse a sí mismo, el de `password` a la alerta, porque el
  campo no está); `USER_LAST_ADMIN`, alerta arriba; `USER_IS_VETERINARIAN`, alerta con el código de la ficha; 404 al guardar,
  aviso y vuelta al listado.
* **El propio usuario** (H5). Guardar invalida `['me']` y, si la sucursal actual cambió, las consultas de la sucursal
  (`afterUserChanged`). Quitarse el rol de administrador pide antes la confirmación ("Vas a dejar de ser administrador y no vas a
  poder volver a esta pantalla."); con el 200, `me` de nuevo y la vuelta a `/`.
* **Contraseña fuera de la caché.** El alta y la edición con `gcTime: 0` y `reset()` en `onSettled`; los errores se tratan en las
  opciones de `useMutation` (desvío 4).
* **Pruebas.** Vitest: `form.test.ts`, `search.test.ts` y `UserPages.test.tsx` (con los 3 tests de la revisión). Playwright:
  `e2e/usuarios.spec.ts` (nuevo: alta con rol y sucursal, edición de sus roles, cambio de su contraseña con el ingreso con la nueva
  en otro contexto, donde la anterior ya no sirve, y `USER_LAST_ADMIN` al quitarse el rol el único administrador), 1 de 1. Mutaciones de la implementación: 4 de 4
  detectadas (`password` mandado sin "Cambiar la contraseña", la contraseña visible en el propio usuario, sin invalidar `['me']` al
  editarse a sí mismo y sin la confirmación de quitarse el rol de administrador).

| # | Desvío | Motivo |
|---|---|---|
| 1 | Playwright siguió el caso del pedido de la entrega (alta con rol y sucursal, edición de sus roles, cambio de su contraseña con el ingreso con la nueva en otro contexto, y `USER_LAST_ADMIN` al quitarse el rol el único administrador), no el del diseño (el menú del usuario nuevo, la sesión del otro contexto cerrada y el email repetido) | Pedido de la entrega; el 422 del email y el de `branch_ids.0` están en Vitest |
| 2 | "Inactivado" (la marca del usuario) y "(inactivada)" (la sucursal en el filtro y en el formulario), no "Inactivo" ni "(inactiva)" | El test de voseo toma "Inactiva" por el imperativo (desvío 3 de la 2.1) |
| 3 | `USER_IS_VETERINARIAN`: la alerta muestra el código de la ficha como texto ("Ficha de veterinario: VET-…."), sin enlace; el detalle, el código sin enlace y sin "Crear la ficha de veterinario" | `/veterinarios/$id` llega en la 2.3 |
| 4 | Los errores del alta y la edición se tratan en las opciones de `useMutation`, no en las de `mutate()`, y las variables llevan el formulario (`{ values, form }`) | `reset()` en `onSettled` (la contraseña fuera de la caché, `gcTime: 0`) descarta los callbacks de `mutate()`: el error no llegaba al formulario |
| 5 | La regla de nombres de personas pasó a `src/lib/person-name.ts` (`PERSON_NAME_PATTERN`), y clientes la importa de ahí sin cambiar su comportamiento | Es la misma regla (`PersonName`) en clientes y usuarios |
| 6 | Los `loader` del alta y la edición traen también los roles y las sucursales: un 403 o un 5xx de esos pedidos va a la página de error de la ruta | El formulario no se dibuja sin las casillas |
| 7 | La columna "Nombre" del listado muestra "Apellido, Nombre" | El listado de la API ordena por apellido |
| 8 | "Incluir los inactivos" ya está en el listado de la 2.2a (el pedido nombraba solo el rol y la sucursal) | Está en la URL de la 2.2a del diseño |
| 9 | `e2e/support/session.ts`: `User` pasa a ser `{ email, name }` (antes, solo los tres del `DemoSeeder`) | Para entrar con el usuario creado en el caso |
| 10 | Edge: el usuario temporal se creó con un email `@mailinator.com` y se borró por la API (`DELETE users/61`, 200; queda eliminado con el sufijo de baja, como toda baja). Dos corridas del script fallaron antes sin cambiar la demo: con `@example.com` (422: la demo tiene `EMAIL_DNS_CHECK=true` y ese dominio no tiene MX) y con el id de `admin-a` supuesto (el de los tests) | La demo valida el DNS del email; `admin-a`, `recepcion-a` y `vet-a` solo se leyeron |
| 11 | Después de la corrida de Playwright, `e2e/support/session.ts` cambió solo en el tipo de `User` (desvío 9); no se repitió | Sin cambios de comportamiento |

**Revisión (2026-11-16).** Sobre `fff201c`, contra el cierre de la 2.1 (`0f7b80b`), con el presupuesto por entrega: sin hallazgos
que cambien el comportamiento (la contraseña nunca mandada sin "Cambiar la contraseña" ni como `null` y fuera de la caché, el
propio usuario sin los campos y con el enlace a `/perfil`, `USER_LAST_ADMIN` y `USER_IS_VETERINARIAN`, la confirmación al quitarse
el rol, la invalidación de `['me']` por el id del usuario de la sesión, la paridad con `StoreUserRequest`, `UpdateUserRequest`,
`UserMessages` y `Password::defaults()`, `PERSON_NAME_PATTERN` igual carácter por carácter a la expresión que tenía clientes y los
permisos). Corregido en `e8ecdeb` (menor): un 422 de `password` al editarse a sí mismo ("Para cambiar tu contraseña usá tu
perfil."; no debería llegar, porque no se manda) caía en un campo que no se dibuja; ahora va a la alerta. Tres tests nuevos y una
comprobación en uno existente: el 422 del alta con la contraseña fuera de la caché de mutaciones (el desvío 4), el 422 de la
contraseña filtrada al cambiar la de otro usuario (en su campo y fuera de la caché), el 422 de `password` del propio usuario (en
la alerta) y editarse sacando la sucursal actual (`['me']` de nuevo e `invalidateBranchQueries`, que no tenía test). Mutaciones: 3
de 3 detectadas; dos de ellas, solo por los tests nuevos: sin `reset()` en la edición (con el 200, la página se desmonta y
`gcTime: 0` borra la mutación igual, así que el test de éxito no la detectaba) y sin `invalidateBranchQueries` al cambiar la
sucursal actual. La tercera, las sucursales inactivas que el usuario ya tiene fuera de las opciones, la detectaba un test que ya
estaba.

**Validación.** El usuario aceptó los 11 desvíos y dio la entrega por validada al pedir su cierre (R-076). Evidencia: Claude Code,
5 de 5 en Edge sin ventana contra `syncvet.test` (el usuario temporal, borrado por la API; desvío 10), con capturas fuera del
repositorio, en la PC de la validación (`%TEMP%\claude\…\scratchpad\capturas-2-2a`: `01-configuracion.png`, `02-listado.png`,
`03-detalle-vet.png`, `04-alta.png`, `05-edicion-cambiar-contrasena.png`, `06-quitarse-el-rol.png` y
`07-edicion-propia-768.png`); Playwright, `e2e/usuarios.spec.ts`, 1 de 1 (en la implementación; la revisión no tocó su caso):
el usuario entra con la contraseña nueva en otro contexto y la anterior ya no sirve; desde
`frontend/`, sobre `e8ecdeb`, `typecheck`, `lint` y `build` sin errores ni avisos.

Corrida de `npm test` del frontend (2026-11-16): 982 tests en 49 archivos, en verde.

### Entrega 2.2b

Estado: **COMPLETADO** el 2026-11-16 (R-077). Usuarios: inactivar, reactivar y baja, en `feature/front-2-2b-usuarios` (desde
`feature/front-2-2a-usuarios`, en el cierre de la 2.2a, `0bf3ed0`), sin integrar a `main` (se integra al cerrar la F2). Commits:
`2cd6dd4` (WIP de la implementación), `3265903` (WIP de la revisión) y el de este cierre (R-077: la documentación).

**Implementación.**

* **Acciones en el detalle** (`features/users/UserActions.tsx`, nuevo): "Inactivar" o "Reactivar" (`users.update`) y "Eliminar"
  (`users.destroy`), ocultos en el propio usuario. `PERMISSIONS` suma `usersDestroy`.
* **Inactivar y reactivar** (`UserStatusDialog`, `tone="primary"`): `PATCH users/{id}` con solo `{ is_active }` (`setUserActive`),
  con el aviso "Se van a cerrar todas sus sesiones y no va a poder ingresar hasta que lo reactives." o "Va a tener que ingresar de
  nuevo."; con el 200, `afterUserChanged` (el detalle toma la respuesta; `['users']` y `['veterinarians']` invalidados).
* **Baja** (`DeleteUserDialog`, `tone="danger"`): `DELETE users/{id}` (`deleteUser`); con el 200, `afterUserGone` (los listados
  vaciados, usuarios y veterinarios invalidados) y al listado, donde el detalle sale de la caché (desvío 1). Con `USER_IN_USE`, el
  mensaje de la API y "Inactivar" (en azul, si está activo y con `users.update`), que cierra este diálogo y abre el de inactivar; en
  un inactivo, el mensaje y "Cerrar".
* **Errores en los diálogos.** `USER_LAST_ADMIN` y `USER_SELF_ACTION` (si llegara, por un detalle viejo de otra pestaña) y un 422,
  el mensaje y solo "Cerrar"; el 404, aviso y vuelta al listado (`useUserGone`); lo demás (el servidor, la red, `FORBIDDEN`), el
  mensaje y el botón para probar de nuevo. Un solo pedido con `useSingleAction` en los dos diálogos.
* **Pruebas.** Vitest: 14 tests nuevos en `UserPages.test.tsx` (13 de la implementación y 1 de la revisión). Playwright:
  `e2e/usuarios-estado.spec.ts` (nuevo), 1 de 1 en 54,5 s: la sesión abierta en otro contexto termina al inactivar, el login
  responde el mensaje de `USER_INACTIVE`, la reactivación deja entrar de nuevo, la baja del usuario y la de `vet-a` (con ficha)
  ofrece "Inactivar" sin confirmarlo. Mutaciones de la implementación: 4 de 4 detectadas (los botones en el propio usuario,
  `USER_IN_USE` sin "Inactivar", la baja sin sacar el detalle de la caché y sin invalidar usuarios y veterinarios al inactivar).

| # | Desvío | Motivo |
|---|---|---|
| 1 | Con el 200 de la baja, el detalle sale de la caché con `removeQueries` al llegar al listado (como la baja del paciente), no con `resetQueries`; los listados, sí con `resetQueries` | Mientras navega, el detalle todavía está a la vista: vaciarlo antes lo volvería a pedir (un 404) |
| 2 | `dialogError` de `UserActions.tsx` repite el de `BranchDialogs.tsx` (no exportado) | Llevarlo a código común es otro tema; el usuario decidió hacerlo en la 2.3 (R-077, anotado en su [diseño](#23-veterinarios)) |
| 3 | En el diálogo de baja con `USER_IN_USE`, el botón dice solo "Inactivar" (el diseño); el del diálogo de inactivar, "Inactivar a …" | El primero solo abre el otro diálogo |
| 4 | Un 422 al inactivar o eliminar (no debería llegar) muestra sus mensajes en el diálogo con "Cerrar" | Como la 2.1 (desvío 8) |
| 5 | Playwright: la sesión del otro contexto, al recargar, termina con "Tu sesión terminó. Ingresá de nuevo." (401: la API revocó sus tokens), y el mensaje de `USER_INACTIVE` aparece al volver a ingresar | Es lo que responde la API; el caso lo comprueba así |
| 6 | Edge: `USER_IN_USE` simulado (el `DELETE` del usuario temporal interceptado con un 409) para la captura y el paso con el teclado al diálogo de inactivar | No se intenta la baja de `vet-a` en la demo; la real está en Playwright contra `syncvet_e2e` |
| 7 | La captura `04-baja-en-uso.png` muestra "Inactivar" en un rojo pálido: es la transición de color de Tabler (0,3 s) tomada a mitad; un test comprueba que el botón es `btn-primary` | Captura tomada apenas llegó el mensaje |
| 8 | Prettier cambió el formato de `e2e/usuarios-estado.spec.ts` después de su corrida; no se repitió | Solo formato |

**Revisión (2026-11-16).** Sobre `2cd6dd4`, contra el cierre de la 2.2a (`0bf3ed0`), con el presupuesto por entrega y
`backend/CLAUDE.md` §6.5: sin hallazgos que cambien el comportamiento (los botones ocultos en el propio usuario y
`USER_SELF_ACTION` bien mostrado si llegara; `USER_LAST_ADMIN`; `USER_IN_USE` con "Inactivar" solo en un activo; el cuerpo
`{ is_active }` y los avisos; la invalidación de usuarios y veterinarios; la baja sin volver a pedir el detalle; un solo pedido;
Escape y el clic afuera no cierran mientras espera; los permisos). Corregido en `3265903` (solo tests, en `UserPages.test.tsx`):

| # | Hallazgo | Corrección |
|---|---|---|
| R1 | El 200 de la baja no comprobaba el desvío 1 (que el detalle no se vuelve a pedir) ni la invalidación de veterinarios | El test cuenta los `GET users/7` y revisa `['veterinarians']` invalidado |
| R2 | El doble clic se probaba solo al inactivar | El mismo test de la baja hace doble clic: un solo `DELETE` |
| R3 | Sin test del foco al pasar de la baja con `USER_IN_USE` al diálogo de inactivar, ni de Escape | Test nuevo: el foco en "Cancelar" del diálogo de inactivar; Escape lo cierra sin pedido y el foco vuelve a "Eliminar" |

Mutaciones de la revisión: 3 detectadas de 4 probadas (los errores de negocio con el botón para probar de nuevo, 4 tests; la baja
sin invalidar `['veterinarians']`, R1; la baja sin `useSingleAction`, R2). No detectada: `resetQueries` del detalle antes de
navegar (lo que el desvío 1 evita): en jsdom el detalle no se vuelve a pedir, así que el conteo de R1 no protege ese caso; queda
anotada, sin otro test (el 404 solo se vería contra la API real).

**Validación.** El usuario aceptó los 8 desvíos y dio la entrega por validada al pedir su cierre (R-077). Evidencia: Claude Code,
5 de 5 en Edge sin ventana contra `syncvet.test` (un usuario temporal, borrado por la API al terminar, `DELETE users/62`, 200;
`USER_IN_USE` simulado, desvío 6), con capturas fuera del repositorio, en la PC de la validación (entre ellas
`04-baja-en-uso.png`); Playwright, `e2e/usuarios-estado.spec.ts`, 1 de 1 (en la implementación; la revisión no tocó su caso);
desde `frontend/`, sobre `3265903`, `typecheck`, `lint` y `build` sin errores ni avisos.

Corrida de `npm test` del frontend (2026-11-16): 996 tests en 49 archivos, en verde.

### Entrega front-02

Estado: **COMPLETADO** el 2026-11-16 (R-078). Soporte del backend: la ficha de veterinario en `GET users`, en
`feature/front-02-usuarios-veterinario` (desde `feature/front-2-2b-usuarios`, en el cierre de la 2.2b, `85ebf3a`), sin integrar a
`main` (se integra al cerrar la F2). Commits: `bf98d8d` (WIP de la implementación), `f0218e6` (el teléfono de ejemplo de
`branches/form.test.ts` y el presupuesto por entrega) y el de este cierre (R-078: la documentación).

**Implementación.**

* `veterinarian` (`id` y `code`, o `null` sin ficha o con la ficha eliminada) pasó de `ShowUserResource` a `UserResource`, al final
  de sus claves (el detalle conserva su orden), y `UserController::RELATIONS` suma `veterinarian`: el listado la carga con el `with`,
  sin N+1. El alta y la edición ya la respondían; `me` (`MeResource`) no se tocó. Sin rutas, permisos, migraciones ni `error_code`
  nuevos (78, 135, 36).
* **Pruebas.** `tests/Feature/Users/UserVeterinarianTest.php` (nuevo): con y sin ficha y con la ficha eliminada; sin N+1 con 1 y 5
  usuarios (después de un pedido que llena la caché de permisos de Spatie, como `AppointmentQueueTest`); las claves del listado, el
  detalle, el alta y la edición. `MeTest`: las claves de `me`. `countQueries` quedó en el trait `Tests\Concerns\InspectsQueries`
  (las tres copias de la deuda 21 siguen: se unifican cuando se toquen esos tests). Mutaciones: 3 de 3 detectadas (sin
  `veterinarian` en el `with`, 6 → 10 consultas; la ficha cambiada en el detalle; la relación con `withTrashed()`). Postman: dos
  aserciones en la colección de usuarios (pedidos 19 y 44).

| # | Desvío | Motivo |
|---|---|---|
| 1 | `FictitiousDocumentsTest` fallaba en la rama desde la 2.1 (`626759b`) por `'1234567'` como teléfono corto en `frontend/src/features/branches/form.test.ts` (7 dígitos, el rango de las cédulas reales). Corregido en esta rama (`f0218e6`) con `'9'.repeat(7)`, armado en el código, sin cambiar lo que prueba | Las entregas del frontend no corrían la suite del backend; desde `f0218e6`, el presupuesto por entrega del `CLAUDE.md` de la raíz les suma ese test (pedido del usuario) |
| 2 | El test sin N+1 hace un pedido antes de contar | El primero llena la caché de permisos de Spatie (2 consultas más) y no es N+1; igual que `AppointmentQueueTest` |
| 3 | `countQueries` quedó como método del trait `Tests\Concerns\InspectsQueries`; las tres copias privadas de la deuda 21 siguen | Se unifican cuando se toquen esos tests |

**Revisión (2026-11-16).** Sobre `bf98d8d` (diff con `85ebf3a`), con el presupuesto por entrega: sin hallazgos. La ficha sale en
el listado con la forma del diseño, cargada con el `with` (`UserResource` se usa solo en `UserController`); la ficha eliminada,
`null` (`Veterinarian` con `SoftDeletes`); el detalle, el alta, la edición y `me`, con las mismas claves en el mismo orden; los
tests fallan con la regla rota (las 3 mutaciones de la implementación). `composer test:fast`: 1467 casos, 1451 pasan y 16
omitidos (solo MySQL), sin fallas.

**Validación.** El usuario dio la entrega por validada (`composer test` en verde, Newman de usuarios y la revisión) y aceptó los 3 desvíos al pedir el cierre (R-078). Evidencia: desde `backend/`, `composer test` en serie
sobre `f0218e6`, 1467 casos, 1451 pasan, 16 omitidos (solo MySQL) y 14303 aserciones; Newman de la colección de usuarios, 54
pedidos y 138 aserciones, sin fallas (en la implementación, sobre `bf98d8d`; el código del backend no cambió después); desde
`frontend/`, sobre `f0218e6`, `typecheck`, `lint` y `build` sin errores ni avisos.

Corrida de `npm test` del frontend (2026-11-16): 996 tests en 49 archivos, en verde.

### Entrega 2.3

Estado: **COMPLETADO** el 2026-11-16 (R-079). Veterinarios: listado (los tres roles), detalle, alta desde el usuario, edición,
inactivar y baja, en `feature/front-2-3-veterinarios` (desde `feature/front-02-usuarios-veterinario`, en el cierre de front-02,
`0ab4dc7`), sin integrar a `main` (se integra al cerrar la F2). Commits: `fcf6667` (`dialogError` a código común), `72975a6` (WIP
de la implementación), `359b2d7` (WIP de la revisión) y el de este cierre (R-079: la documentación).

**Implementación** (el 2026-11-16). Primero, `fcf6667`: `dialogError` pasó a `src/lib/api/dialog-error.ts`, con sus tests;
sucursales y usuarios la importan sin cambiar su comportamiento (sus tests siguieron en verde sin tocarlos).

* **Hecho.** `features/veterinarians` (nuevo): el menú "Veterinarios" (`veterinarians.index`, después de "Especies"), `/veterinarios`
  (búsqueda, `?estado=inactivos|todos` traducido a `status=inactive` o `include_inactive=1`, `?sucursal=`; el enlace al detalle y
  "Nuevo veterinario" solo para el administrador; sin la comisión ni las notas), `/veterinarios/$id` (la comisión "12,5 %", el
  usuario con su enlace, "Usuario inactivo"; Editar, Inactivar o Activar y Eliminar), `/veterinarios/nuevo?usuario=` (el usuario
  entre los activos con el rol veterinario y `veterinarian === null`, de `['users', 'options', { role, include_inactive }]`) y
  `/veterinarios/$id/editar` (el código y el usuario solo para leer; sin `user_id`, `code` ni `status` en el cuerpo). La matrícula
  en mayúsculas y recortada; la comisión con coma o punto, mandada con punto (vacía, `"0"`). La baja con `VETERINARIAN_IN_USE`: el
  mensaje y "Inactivar" (en una inactiva, solo "Cerrar"). Crear, editar, activar o inactivar invalidan `['veterinarians']` y
  `['users']` (el detalle toma la respuesta) y `['me']` si la ficha es del usuario de la sesión; la baja, igual, con los listados
  vaciados y el detalle fuera de la caché al llegar al listado. En usuarios: `User` suma `veterinarian` (front-02); el detalle
  enlaza a la ficha o muestra "Crear la ficha de veterinario" (activo, con el rol y sin ficha); la alerta de `USER_IS_VETERINARIAN`
  enlaza a la ficha. `PERMISSIONS` suma los cinco de `veterinarians.*`.
* **Pruebas.** Vitest: `dialog-error.test.ts` (8), `veterinarians/search.test.ts` y `form.test.ts` (44, cada regla en su borde) y
  `VeterinarianPages.test.tsx` (25: listado de los dos roles, estado y sucursal en la URL y en el pedido, `/sin-permiso`, el
  selector sin los usuarios con ficha y con `?usuario=`, la ficha restaurada con su código, 422 de la matrícula, la edición sin
  `user_id`, inactivar, activar, la baja con el 200 y con `VETERINARIAN_IN_USE`, el detalle del usuario). Mutaciones: 4 de 4
  detectadas (el selector con los usuarios con ficha; `user_id` en la edición; el enlace al detalle para el recepcionista; sin
  invalidar `['users']`). Playwright: `e2e/veterinarios.spec.ts` (nuevo), 1 de 1 en 50,4 s. Desde `backend/`,
  `FictitiousDocumentsTest`, 3 de 3.

| # | Desvío | Motivo |
|---|---|---|
| 1 | El filtro del listado es el del diseño (`?estado=activos\|inactivos\|todos` y `?sucursal=`), no "Incluir los inactivos" (`?inactivos=1`) del pedido de la entrega | Lo decidió el usuario al empezar la entrega ("Todos" incluye a los inactivos) |
| 2 | El estado no es un campo del formulario de edición: se cambia con "Inactivar" o "Activar" en el detalle | El diseño (pregunta 13): activar e inactivar son botones con su confirmación |
| 3 | En el alta con `?usuario=`, "Cancelar" vuelve al detalle de ese usuario (sin `?usuario=`, al listado). Un `?usuario=` que no se puede elegir (con ficha, sin el rol, inactivo) no queda elegido | Se vuelve a donde se vino; el selector solo ofrece lo que la API acepta |
| 4 | El selector del alta pide 100 usuarios (`per_page` máximo de la API): con más de 100 usuarios con el rol veterinario, los que pasan de la primera página no se ofrecen | Una clínica no llega a eso; si hiciera falta, una búsqueda en el selector |
| 5 | La ficha restaurada muestra el mensaje de la API ("Veterinario creado correctamente.", el mismo 201 del alta) y el detalle con su código de siempre; no hay un aviso propio de "restaurada" | La API no distingue la restauración en la respuesta; el mensaje se muestra tal cual |
| 6 | Tests tocados por la entrega (no por el commit de `dialogError`): el menú con "Veterinarios" en `frontend/src/app/permissions.test.tsx` y `frontend/src/lib/auth/permissions.test.ts`; en `UserPages.test.tsx`, el código de la ficha ahora es un enlace (2 tests) | Cambios de comportamiento de la 2.3 |
| 7 | La corrida final de `npm test` dio 6 fallas en `frontend/src/lib/auth/permissions.test.ts` (el menú sin "Veterinarios"); se corrigió y se repitió solo ese archivo, 12 de 12. La cifra total, 1073 tests en 53 archivos (996 más 77 nuevos), sale de la cuenta, no de una corrida completa en verde: queda para la revisión o el cierre | El presupuesto por entrega: se repite solo el paso que falló |
| 8 | Después de la validación en Edge, el selector del usuario sumó `className="mb-3"` (la pista quedaba pegada a "Matrícula", como los selectores del paciente); `typecheck`, `lint` y sus tests relacionados (455) en verde, sin repetir Edge ni el build | Solo un margen |
| 9 | Edge: la primera corrida del control 5 falló al crear el usuario temporal con `@example.com` (422: la demo valida el DNS, como el desvío 10 de la 2.2a), sin cambiar la demo; se repitió solo ese control con `@mailinator.com` (usuario 39, borrado por la API, `DELETE users/39`, 200). `VETERINARIAN_IN_USE` se simuló interceptando el `DELETE` de `vet-a` (nunca llegó a la API); la ficha de `vet-a` solo se leyó (igual antes y después) | La demo queda como estaba; la baja real está en Playwright contra `syncvet_e2e` |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 5 de 5. (1) El listado con
"Todos" y al ancho de tablet, sin desplazamiento horizontal; (2) el detalle de `vet-a` ("0 %"); (3) la edición con la comisión con
coma ("0,00") y el código y el usuario solo para leer, sin guardar; (4) la baja con `VETERINARIAN_IN_USE` simulado: el mensaje y
"Inactivar", sin confirmar; (5) del usuario temporal a "Crear la ficha de veterinario", con el usuario elegido, y "Cancelar" de
vuelta al usuario, sin guardar. Capturas fuera del repositorio, en la PC de la validación
(`%TEMP%\claude\…\scratchpad\capturas-2-3`: `01-listado.png`, `02-listado-tablet.png`, `03-detalle.png`, `04-edicion.png`,
`05-baja-en-uso.png`, `06-usuario-sin-ficha.png`, `07-alta.png`).

**Revisión** (2026-11-16, sobre `fcf6667` y `72975a6`, contra `0ab4dc7`; el usuario aceptó los 9 desvíos). Antes de revisar,
`npm test` completo: 1073 tests en 53 archivos, en verde (la cifra del desvío 7 queda confirmada por una corrida).

* **Revisado, sin hallazgos.** El listado no muestra la comisión ni las notas: el tipo `Veterinarian` no las tiene, las columnas
  son fijas y la caché guarda solo lo que manda `VeterinarianResource` (sin `commission_percentage` ni `notes`, R-037); el único
  `setQueryData` es el del detalle (`afterVeterinarianChanged`), que solo el administrador pide; el recepcionista y el veterinario
  no piden el detalle (`requirePermission` antes del `loader`, con test) y salir de la sesión vacía la caché. La matrícula,
  recortada y en mayúsculas antes de validar y de mandar, como `VeterinarianMessages::normalizeLicense` ("sin espacios" es en los
  extremos, como la API). La comisión, texto de punta a punta (`Number` solo para el rango): coma o punto, `numeric`,
  `min`/`max` y `decimal:0,2` en el orden y con los mensajes de la API, también el exponente (`1e1`: numérico, pero no decimal).
  El alta ofrece solo usuarios activos con el rol y `veterinarian === null`; `?usuario=` que no se puede elegir no queda elegido;
  la restauración muestra el código de siempre. `VETERINARIAN_IN_USE` ofrece "Inactivar" (solo en una activa). La alerta de
  `USER_IS_VETERINARIAN` enlaza a la ficha. Las invalidaciones son las del diseño (`['veterinarians']`, `['users']` y `['me']` si
  es la propia; usuarios y sucursales ya invalidaban `['veterinarians']`). `dialogError` se movió sin cambios (el cuerpo es
  idéntico en las dos copias borradas y en `src/lib/api/dialog-error.ts`). Permisos: las cuatro rutas con su permiso; el enlace al
  detalle, "Nuevo veterinario", Editar, Inactivar y Eliminar con `useCan()`.
* **Corregido (tests que faltaban).** En `VeterinarianPages.test.tsx`, 2 tests: inactivar la ficha propia (un administrador que
  también es veterinario) vuelve a pedir `me`; inactivar una ficha que otra pestaña eliminó (404) avisa y vuelve al listado
  (`useVeterinarianGone`, sin test hasta ahora). En `form.test.ts`, el caso `1e1` (paridad con `decimal:0,2`).
* **Notas, sin cambio.** El test "el recepcionista: … sin la comisión" es débil (la respuesta simulada nunca la trae): la protección
  real es la API, y en el frontend, el tipo y las columnas fijas. Con un 404 en una acción, `useVeterinarianGone` no vuelve a pedir
  `me` si la ficha era la propia; `SessionLayout` lo vuelve a pedir al volver a la pestaña, y el caso (la propia ficha borrada en
  otra pestaña) no cambia permisos.
* **Mutaciones** (3 de 3 detectadas, revertidas con `git checkout`): sin volver a pedir `me` en la ficha propia (lo detecta el test
  nuevo); la matrícula sin pasar a mayúsculas (3 tests); `VETERINARIAN_IN_USE` sin "Inactivar" (1 test).
* **Cifras.** Tests tocados (`vitest run` de los dos archivos): 55 de 55. Corrida final: `typecheck` y `lint` sin errores ni avisos; `npm test`,
  1076 tests en 53 archivos, en verde (1073 más los 3 nuevos); `build` sin errores; desde `backend/`, `FictitiousDocumentsTest`,
  3 de 3. Playwright no se repitió: los cambios son solo de tests de Vitest.

**Validación.** El usuario aceptó los 9 desvíos, pidió la revisión y, con ella, el cierre (R-079). Evidencia: Claude Code, 5 de 5
en Edge sin ventana contra `syncvet.test` (arriba; el usuario temporal, borrado por la API, `DELETE users/39`, 200;
`VETERINARIAN_IN_USE` simulado, desvío 9); Playwright, `e2e/veterinarios.spec.ts`, 1 de 1 (en la implementación; la revisión no
tocó su caso; 26 casos en total), con el recepcionista que ve el listado sin la comisión, sin enlaces ni "Nuevo veterinario"; desde `frontend/`, sobre `359b2d7`, `typecheck`, `lint` y `build` sin errores ni avisos; desde
`backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-16): 1076 tests en 53 archivos, en verde.

### Entrega 2.4

Estado: **COMPLETADO** el 2026-11-17 (R-080). Servicios: listado y detalle (los tres roles), alta, edición, inactivar y baja, con el
precio en guaraníes, en `feature/front-2-4-servicios` (desde `feature/front-2-3-veterinarios`, en el cierre de la 2.3, `0743a72`),
sin integrar a `main` (se integra al cerrar la F2). Commits: `0686f94` (WIP de la implementación), `b0bab2b` (WIP de la revisión) y
el de este cierre (R-080: la documentación).

**Implementación** (el 2026-11-16). Antes, en la misma
rama, el `CLAUDE.md` de la raíz suma al presupuesto por entrega (pedido del usuario): si `npm test` de la corrida final falla en un
archivo, después de corregirlo se repite la suite completa, para que la cifra registrada sea siempre la de una corrida real.

* **Hecho.** `features/services` (nuevo): el menú "Servicios" (`services.index`, después de "Especies"), `/servicios` (búsqueda,
  categoría con las etiquetas de `ServiceCategory`, `?estado=inactivos|todos` traducido a `status=inactive` o `include_inactive=1`;
  el precio con `formatGuaranies`, la duración "30 min" y el IVA "10 %"; el enlace al detalle con `services.show` y "Nuevo
  servicio" con `services.store`), `/servicios/$id` (los tres roles, con `key={id}`; Editar, Inactivar o Activar y Eliminar solo
  para el administrador), `/servicios/nuevo` y `/servicios/$id/editar` (el código solo para leer). El formulario: nombre con
  `ServiceMessages::NAME_REGEX` (acepta `/ , + ( ) . - ' %`, rechaza `//`), categoría, precio (texto con `inputMode="numeric"`:
  "150.000" se manda `"150000"`; la coma, los decimales, el signo, el exponente y más de 12 dígitos, con el mensaje de
  `WholeAmount` y sin pedido; `"150000.00"` de la API vuelve "150.000"), duración (5 a 720, múltiplo de 5; 30 por defecto), IVA
  (`RadioGroup` 0, 5 o 10; 10 por defecto, número en el cuerpo) y descripción. `SERVICE_NAME_NOT_EDITABLE` al campo nombre (con el
  foco); la baja con `SERVICE_IN_USE`: el mensaje y "Inactivar" (en uno inactivo, solo "Cerrar"); los errores de los diálogos con
  `dialogError`; un solo pedido con `useSingleAction`. Crear, editar, activar o inactivar invalidan `['services']` (el detalle toma
  la respuesta); la baja y el 404 vacían los listados y sacan el detalle de la caché al llegar al listado. `PERMISSIONS` suma los
  cinco de `services.*`.
* **Pruebas.** Vitest: `services/form.test.ts` y `search.test.ts` (87: cada regla en su borde, el precio con sus casos del diseño,
  la duración 4, 5, 720 y 722, y las categorías leídas de `app/Enums/ServiceCategory.php`, H2) y `ServicePages.test.tsx` (23:
  listado de los dos roles, categoría y estado en la URL y en el pedido, el detalle sin acciones para el recepcionista,
  `/sin-permiso`, el alta con "150.000" y doble clic, los precios rechazados sin pedido, el nombre con `//`, 422 del nombre,
  la edición con "150.000" y el cuerpo sin `code` ni `status`, `SERVICE_NAME_NOT_EDITABLE`, inactivar, activar, la baja con el
  200, con `SERVICE_IN_USE` en uno activo y en uno inactivo, y el 404 en una acción). Mutaciones: 4 de 4 detectadas (el precio
  mandado como número, 11 fallas; los puntos de miles sin sacar, 10; el IVA como texto, 3; `SERVICE_NAME_NOT_EDITABLE` fuera del
  campo, 1). Playwright: `e2e/servicios.spec.ts` (nuevo), 1 de 1 en 1,5 min (27 casos en total). Corrida final, desde `frontend/`:
  `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1186 de 1186 en 56 archivos, en verde (sin fallas, en la primera
  corrida); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

| # | Desvío | Motivo |
|---|---|---|
| 1 | El filtro del listado es el del diseño (`?estado=activos\|inactivos\|todos`), no "Incluir los inactivos" (`?inactivos=1`) del pedido de la entrega | Lo decidió el usuario al empezar la entrega, como en la 2.3 |
| 2 | El estado no es un campo del formulario: se cambia con "Inactivar" o "Activar" en el detalle y el alta queda `active` | Lo decidió el usuario al empezar la entrega (el diseño, pregunta 13) |
| 3 | `formatGs` del pedido es `formatGuaranies` (`src/lib/format/money.ts`) | Es el nombre de la función común |
| 4 | Los puntos se sacan solo si agrupan miles ("150.000", "12.500.000.000"); "1500.00" se rechaza (no es "150000"). Los ceros de adelante no cuentan para los 12 dígitos y no se mandan ("007" → `"7"`), como `WholeAmount::normalize` | Paridad con la API sin adivinar lo escrito |
| 5 | La duración es un campo de texto con `inputMode="numeric"` (no `type="number"`, como el precio) y acepta el signo como `integer` de Laravel ("+30") | Paridad con la API; el teclado numérico en el celular |
| 6 | Playwright: el caso del pedido (crear con "150.000", el cuerpo con `"150000"`, "Gs. 150.000" después de recargar, editar, el recepcionista sin acciones, inactivar y borrar), con el precio con coma rechazado sin pedido; no el cambio de nombre de un servicio usado | El `DemoSeeder` no crea servicios ni turnos: no hay un servicio usado. `SERVICE_NAME_NOT_EDITABLE` está en Vitest |
| 7 | Tests tocados por la entrega: el menú con "Servicios" en `frontend/src/app/permissions.test.tsx` y `frontend/src/lib/auth/permissions.test.ts` | Cambio de comportamiento de la 2.4 |
| 8 | `FictitiousDocumentsTest` tomó "1.000.000" (7 dígitos) de `form.test.ts` y de un comentario de `form.ts` como una cédula; los ejemplos pasaron a "12.500.000.000" | Ningún número con forma de cédula en el código del frontend |
| 9 | Edge: la primera corrida del script falló al ingresar (la etiqueta "Contraseña" también es la del botón para mostrarla), sin crear nada; se repitió entera con la etiqueta exacta | Un detalle del script, fuera del repositorio |
| 10 | Edge, control 1: el script lo marcó MAL por dos motivos. El campo del precio no tiene el atributo `type` (`null`: un `<input>` sin `type` es de texto, lo que se quería; el criterio del script era estricto). Y a 375 px la barra del menú se sale de la pantalla (la página mide unos 820 px): **hallazgo anterior a la entrega**, el menú horizontal de `SessionLayout` ya medía unos 700 px sin "Servicios"; no se corrigió (es el layout común) | **Decisión del usuario pendiente:** corregirlo en una entrega aparte o registrarlo como deuda |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 4 de 5 según el script, con
el control 1 explicado en el desvío 10. (1) El alta al ancho de un celular: el precio y la duración con `inputmode="numeric"`; el
menú desborda a 375 px; (2) el detalle con "Gs. 999.999.999.999" (el 201 del alta); (3) el listado con el precio largo en una
sola línea; (4) el listado a 768 px sin desplazamiento horizontal de la página; (5) la edición con "999.999.999.999" en el
precio, sin guardar. El servicio temporal (`SRV-000031`) se borró por la API al terminar (`DELETE services/31`, 200); la demo
quedó como estaba. Capturas fuera del repositorio, en la PC de la validación (`%TEMP%\claude\…\scratchpad\capturas-2-4`:
`01-alta-celular.png`, `02-detalle.png`, `03-listado.png`, `04-listado-tablet.png`, `05-edicion.png`).

**Revisión** (2026-11-16, sobre `0686f94`, contra el cierre de la 2.3, `0743a72`). El usuario aceptó los 10 desvíos; el 10 (el
menú que se sale de la pantalla a 375 px) se corrige en la 2.5, junto con el logo en la barra: anotado en el
[diseño de la 2.5](#25-configuración-de-la-clínica-y-logo) y en el handoff (el menú se pliega detrás de un botón en pantallas
chicas, accesible con el teclado y con lector de pantalla, sin el JS de Tabler).

* **Revisado, sin hallazgos que cambien el comportamiento.** El precio: los puntos se sacan solo si agrupan miles; se valida y se
  manda como texto de solo dígitos (`wholeAmount`, sin `Number`, `parseFloat` ni decimales en ningún paso), de 0 a
  999.999.999.999 con los ceros de adelante fuera de la cuenta, como `WholeAmount::normalize`; `"150000.00"` vuelve "150.000" por
  una expresión regular y se muestra con `formatGuaranies` desde el texto. La duración: entero (con el signo de `integer`), de 5 a
  720 y múltiplo de 5, en el orden de la API, mandada como número. El nombre: `SERVICE_NAME_PATTERN` es `ServiceMessages::NAME_REGEX`
  con `u` y `s` (PHP compila `/u` con UCP: `\s`, `\pL` y `\pN` son de Unicode en los dos), de 2 a 120 caracteres sin los espacios de
  los extremos. `SERVICE_NAME_NOT_EDITABLE` va al campo nombre con el foco. `SERVICE_IN_USE` ofrece "Inactivar" solo en uno activo.
  El IVA va como número. Las acciones del detalle y "Nuevo servicio" dependen de `useCan()`; las rutas de alta y edición, de
  `requirePermission`. Los diálogos usan `dialogError` común; las dos rutas del detalle llevan `key={id}`.
* **Corregido (tests que faltaban).** En `ServicePages.test.tsx`, el listado y el detalle sin acciones se probaban solo con el
  recepcionista: ahora con `it.each` del recepcionista y del veterinario (2 tests más).
* **Mutaciones** (3 de 3 detectadas, revertidas con `git checkout`): el nombre sin `(?!.*//)` (2 fallas: `a//b` en `form.test.ts`
  y en el alta); la duración sin el múltiplo de 5 (2 fallas); las acciones del detalle sin mirar el permiso (2 fallas, el
  recepcionista y el veterinario). La primera necesitó tres intentos: dos veces `sed` y `perl` no cambiaron la expresión (las
  barras escapadas) y los tests pasaron sin la mutación aplicada; se aplicó con un script de Node fuera del repositorio y ahí
  falló, como debía.
* **Cifras.** `npm test` completo, una sola corrida, en verde a la primera: 1188 de 1188 en 56 archivos (los 1186 de la
  implementación y los 2 nuevos); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3. Playwright no se repitió (solo cambiaron
  tests de Vitest).

**Validación.** El usuario aceptó los 10 desvíos (el 10, el menú que se sale de la pantalla a 375 px, se corrige en la 2.5), pidió la
revisión y, con ella, el cierre (R-080). Evidencia: Claude Code, 4 de 5 en Edge sin ventana contra `syncvet.test` (arriba; el control
1, explicado en el desvío 10; el servicio temporal, borrado por la API, `DELETE services/31`, 200); Playwright, `e2e/servicios.spec.ts`,
1 de 1 (en la implementación; la revisión no tocó su caso; 27 casos en total: "150.000" se manda `"150000"` y se ve
"Gs. 150.000" después de recargar). Corrida del cierre (2026-11-17, en otra PC, sobre
`b0bab2b`), desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1188 tests en 56 archivos, en verde
(la misma cifra que la revisión); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-17): 1188 tests en 56 archivos, en verde.

### Entrega 2.5

Estado: **COMPLETADO** el 2026-11-17 (R-081). Configuración de la clínica (datos, claves y logo), el logo en la barra para los tres
roles y el menú plegable en pantallas chicas, en `feature/front-2-5-configuracion` (desde `feature/front-2-4-servicios`, en el
cierre de la 2.4, `37ac3cf`), sin integrar a `main` (se integra al cerrar la F2). Commits: `11dabf4` (WIP de la implementación),
`26e4339` (WIP de la revisión) y el de este cierre (R-081: la documentación).

**Implementación** (el 2026-11-17).

* **Hecho.** `src/lib/files/image.ts` (nuevo, H8): el control de una imagen (el tipo por el contenido y el tamaño primero) con el
  límite y los mensajes como parámetros; `features/patients/photo.ts` lo usa con 5 MB y conserva sus exportaciones (sus tests no
  cambiaron). `features/settings` (nuevo): `/configuracion/clinica` (`system-settings.update`) con su tarjeta "Clínica" en
  `/configuracion`; el formulario (nombre, razón social, correo, teléfono, sitio web y pie) con las reglas y los mensajes de
  `UpdateSystemSettingsRequest`, el RUC (`ruc`-`ruc_dv`) solo para leer y fuera del formulario, `PATCH system-settings` con el
  formulario completo (sin `ruc` ni `ruc_dv`, las claves vacías en `null`, el correo en minúsculas, el teléfono sin espacios ni
  guiones), un solo pedido con `useSingleAction`, la respuesta en `['settings']` y `['me']` invalidado (el nombre en la barra). La
  tarjeta del logo, como la foto: vista previa, "Subir el logo", reemplazar y "Quitar el logo" con `ConfirmDialog`; 2 MB justos
  pasan (`logo.ts`, con los mensajes de `UploadClinicLogoRequest`); el 413 o el 422 con `errors.logo`, al campo con el foco; el 413
  sin `errors`, a la alerta; `CLINIC_LOGO_NOT_FOUND` al ver, el lugar vacío; al quitar, aviso y la configuración pedida de nuevo. La
  mutación con `gcTime: 0` y `reset()`. `Brand` muestra el logo (32 px de alto, `object-fit: contain`, el nombre de la clínica en
  `alt`) si `me.clinic.has_logo` y `system-settings.show-logo` (los tres roles), con la clave `['settings', 'logo']` de la
  configuración: subir o quitar lo cambia sin recargar; sin logo, mientras llega o con un error, la marca SyncVet. El menú
  plegable: `src/app/MainMenu.tsx` (nuevo) con `useMediaQuery` (`src/lib/hooks/use-media-query.ts`, nuevo); en pantallas chicas,
  el botón "Menú" con `aria-expanded` y `aria-controls`, la lista con `hidden` y en columna; Enter o Espacio lo abren, Escape lo
  cierra con el foco de vuelta en el botón, elegir un ítem lo cierra. `PERMISSIONS` suma `system-settings.show-logo`,
  `.upload-logo` y `.delete-logo`.
* **Pruebas.** Vitest: `lib/files/image.test.ts` (10: el límite como parámetro en su borde, el tipo por el contenido y el logo de 2
  MB justos y de un byte más), `settings/form.test.ts` (21: cada regla en su borde, el sitio con `http://`, sin protocolo, `ftp://`
  o con un espacio rechazado y `HTTPS://` aceptado, el cuerpo sin `ruc`, las claves vacías en `null` y las claves comparadas con
  `app/Enums/SystemSettingKey.php`), `settings/ClinicSettingsPage.test.tsx` (25: la tarjeta, el recepcionista y el veterinario a
  `/sin-permiso`, el RUC solo para leer, el cuerpo completo y `me` pedido de nuevo, borrar una clave, `http://` sin pedido, el 422 de
  `settings.website` a su campo con el foco, el doble clic; el logo: sin logo sin descarga, subir con la vista previa y el
  multipart con `logo`, 2 MB justos y uno más, un texto con extensión de imagen, el 413 con y sin `errors`, el 422, reemplazar con
  una descarga nueva, quitar sin volver a pedirlo y con `me` pedido de nuevo, quitar con `CLINIC_LOGO_NOT_FOUND`, el 404 al verlo;
  la barra con el logo para los tres roles y con un 500) y `frontend/src/app/MainMenu.test.tsx` (5: ancho sin botón; al ancho de un celular
  plegado con `aria-expanded` y `aria-controls`; abrir con Enter y con Espacio, Escape desde un ítem y desde el botón con el foco de
  vuelta; elegir un ítem; ensanchar la pantalla). Ningún test existente se tocó. Mutaciones: 4 de 4 detectadas, revertidas desde
  una copia fuera del repositorio (los archivos son nuevos): `ruc` mandado en el cuerpo (1 falla); el límite de 5 MB en el logo (1
  falla, la del límite; después, el test de la página usa los 2 MB escritos y no la constante, y también falla); `me` sin
  invalidar después de subir o quitar el logo (1 falla, la de subir; después, el test de quitar también comprueba que `me` se
  vuelve a pedir); Escape sin devolver el foco al botón (1 falla). Playwright: `e2e/configuracion-clinica.spec.ts` (nuevo), 1 de 1
  en 18 s (28 casos en total): el teléfono con espacios y guiones mandado sin ellos y visto después de recargar, el cuerpo sin
  `ruc`, subir `logo-clinica.png`, verlo en la barra después de recargar, reemplazarlo por `logo-clinica.webp` y quitarlo, y el
  menú a 375 px (plegado, abierto con Enter, Escape con el foco de vuelta, elegir un ítem, sin desplazamiento horizontal); lo
  guardado al empezar se restaura por la API. Corrida final, desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos;
  `npm test`, 1249 de 1249 en 60 archivos, en verde (en la segunda corrida completa, ver el desvío 9); desde `backend/`,
  `FictitiousDocumentsTest`, 3 de 3 (en la segunda corrida, ver el desvío 8).

| # | Desvío | Motivo |
|---|---|---|
| 1 | El pedido de la entrega decía que la pantalla "la ven los tres roles; solo el administrador la edita"; se siguió el diseño: `/configuracion/clinica` solo para el administrador (`system-settings.update`). El recepcionista y el veterinario ven el logo en la barra | Lo decidió el usuario al empezar la entrega (la pregunta, con el diseño como recomendación) |
| 2 | El menú se pliega por debajo de 992 px (el `lg` de Tabler), no de 768 px (`md`) | La barra del administrador mide unos 820 px: a 768 px tampoco entra. En Edge, a 768 px, plegado y sin desplazamiento horizontal |
| 3 | Plegado o no lo decide `matchMedia` en React (`useMediaQuery`, nuevo en `src/lib/hooks`), no una clase de CSS; sin `matchMedia`, la barra ancha | Así jsdom lo prueba (el test simula el ancho con `matchMedia`) y el estado de `aria-expanded` sigue al ancho real |
| 4 | Con `has_logo` y el 404 `CLINIC_LOGO_NOT_FOUND` al verlo, el lugar queda vacío pero se sigue ofreciendo "Quitar el logo" (la foto, en ese caso, pasa a "sin foto") | Quitar limpia la referencia en la API (§6.8); si responde el 404, el aviso y la configuración pedida de nuevo |
| 5 | Mientras se descarga el logo, la barra muestra la marca SyncVet (un instante, al entrar o al reemplazarlo) | Nunca un hueco ni una imagen rota; el diseño pide la marca "sin logo o con un error" |
| 6 | Guardar los datos pone la respuesta en `['settings']` (`setQueryData`) en lugar de invalidarla; `['me']` sí se invalida | La respuesta del `PATCH` ya es la configuración completa: es lo mismo sin un pedido más |
| 7 | Los valores del formulario van anidados como el cuerpo (`settings.website`); el teléfono se manda sin espacios ni guiones (`normalizePhone`, como clientes y sucursales), aunque esta API no los quita; el sitio acepta `https://` en mayúsculas (como `url:https` de Laravel) y `new URL()` decide el resto | `applyValidationErrors` lleva cada clave de la API a su campo sin traducirla (una clave que no es un campo, a la alerta); el navegador no rechaza nada que la API acepta |
| 8 | `FictitiousDocumentsTest` tomó "99400001-1", el RUC de ejemplo del test de la página, como un RUC real (el dígito no es el de un 999999xx); los tests usan el de la demo, "99999901-0" | Ningún número con forma de cédula o RUC real en el código del frontend |
| 9 | La primera corrida completa de `npm test` falló en un test de `BranchPages.test.tsx` (el alta de una sucursal, por tiempo con la PC cargada: pasó aislado y la entrega no toca sucursales); después de corregir el desvío 8 se repitió la suite completa, en verde | La regla del presupuesto: la cifra es la de una corrida completa real |
| 10 | La corrida final de `lint` marcó dos detalles (`isLogoMissing` exportado desde un componente y una aserción en `MainMenu.test.tsx`); se corrigieron y se repitieron `lint`, esos tests y `typecheck` | La regla del presupuesto: solo el paso que falló y los tests del archivo tocado |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 5 de 5. (1)
`/configuracion/clinica` en escritorio: el RUC "99999901-0" solo para leer, sin desplazamiento horizontal; (2) un logo ancho (600 ×
100, generado en el navegador) en la barra: 192 × 32, sin deformarse; (3) uno alto (100 × 300): 11 × 32, sin deformarse; (4) a 375
px, con el logo ancho: el menú plegado detrás de "Menú" (`aria-expanded="false"`), abierto con los 7 ítems, Escape con el foco de
vuelta, sin desplazamiento horizontal; (5) a 768 px: el menú plegado, cerrado y abierto, sin desplazamiento horizontal. La consola,
sin errores. La demo quedó como estaba: los datos y las claves guardados al empezar se restauraron por la API (`PATCH`, 200; iguales
a los de antes) y el logo, que no había, se quitó (`DELETE system-settings/logo`, 200). Capturas fuera del repositorio, en la PC de
la validación (`%TEMP%\claude\…\scratchpad\capturas-2-5`: `01-configuracion-clinica.png`, `02-barra-logo-ancho.png`,
`02b-configuracion-con-logo.png`, `03-barra-logo-alto.png`, `04-celular-menu-cerrado.png`, `04b-celular-menu-abierto.png`,
`05-tablet-configuracion.png`, `05b-tablet-menu-abierto.png`). En la captura 04b, el botón "Menú" se ve gris: es el estilo de
foco de `btn-ghost-secondary` de Tabler, no un botón deshabilitado.

**Revisión** (2026-11-17, sobre `11dabf4`, con el presupuesto por entrega). El usuario aceptó los 10 desvíos.

* **Sin cambios.** El RUC nunca va en el pedido (`settingsFormToPayload` arma el cuerpo con `name`, `legal_name` y las cuatro
  claves; el RUC es un campo de solo lectura sin `register`). Las claves vacías van en `null`, y las reglas y los mensajes son los
  de `UpdateSystemSettingsRequest` y `SystemSettingKey::rules()` (los largos con `Array.from` sobre el texto sin espacios en los
  extremos, como `TrimStrings` y `mb_strlen`; el correo en minúsculas antes del largo, como `prepareForValidation`; `https://`
  sin distinguir mayúsculas, como `url:https`). `src/lib/files/image.ts` conserva el comportamiento de la foto: la misma firma de
  bytes, el tamaño antes que el contenido y el límite con `>`; `photo.ts` conserva sus exportaciones y sus tests no cambiaron. El
  logo: 2 MB justos pasan; el 413 o el 422 con `errors.logo`, al campo con el foco; el 413 sin `errors`, a la alerta;
  `CLINIC_LOGO_NOT_FOUND` al verlo, el lugar vacío; al quitarlo, aviso y la configuración pedida de nuevo; `BlobImage` libera la URL
  de objeto. La barra: el logo con `system-settings.show-logo` (los tres roles) y la misma clave de la caché. Los permisos: la ruta
  con `system-settings.update`; subir y quitar, cada uno con su permiso.
* **Hallazgo 1 (menor, corregido): el menú plegado quedaba abierto al navegar sin elegir un ítem** (el enlace de la marca, el
  usuario a `/perfil`, "Atrás"), y al ensanchar la pantalla con el menú abierto y volver a achicarla, reaparecía abierto. Con el foco
  en un ítem de la barra ancha, achicar la ventana dejaba el foco en un elemento oculto. `MainMenu.tsx`: el menú se cierra al
  cambiar la ruta (`useRouterState`) o el ancho (el estado se ajusta durante el render) y, al plegarse con el foco en la lista, el
  foco pasa al botón (`useLayoutEffect`). Dos tests nuevos en `MainMenu.test.tsx`, que fallan con el menú original.
* **Hallazgo 2 (menor, corregido): el test de reemplazar el logo no comprobaba que no se viera el viejo.** La mutación
  "`invalidateQueries` en lugar de `resetQueries` al reemplazarlo" (el viejo se ve mientras baja el nuevo) sobrevivía. El test
  ahora retiene la descarga nueva y comprueba que, mientras tanto, ni la tarjeta ni la barra muestran un logo y que la URL vieja se
  liberó; con la mutación, falla.
* **Desvío 9, la causa (el test era frágil, corregido).** "Los códigos en mayúsculas…; al detalle de la nueva"
  (`BranchPages.test.tsx`) esperaba `findByText('Datos de la sucursal')`, pero el formulario del alta tiene una tarjeta con ese
  mismo título: la espera se cumplía en el acto, antes del `POST`, y el test pasaba solo si el pedido y la navegación terminaban en
  los pocos ciclos que siguen (con la PC cargada, no). No era el reloj ni el orden: era una espera que no esperaba nada. Ahora espera
  la URL del detalle y su encabezado (`h2` con el nombre). Comprobado con el `POST` demorado 500 ms a mano: el test viejo falla
  ("…/nueva") y el nuevo pasa. El archivo, 5 corridas seguidas: 26 de 26 en cada una.
* **Mutaciones** (3, cada una con sus tests relacionados y revertida con `git checkout`): el sitio vacío mandado como `''` en lugar
  de `null` (1 falla); reemplazar el logo con `invalidateQueries` (sobrevivió; detectada después del hallazgo 2, 1 falla); el límite
  justo rechazado (`>=` en `image.ts`: 6 fallas, del logo y de la foto).
* **Corrida final**, desde `frontend/`: `typecheck` y `build` sin errores ni avisos; `lint` marcó una aserción de tipo en
  `MainMenu.test.tsx`, corregida, y se repitieron `lint` y ese archivo (7 de 7); `npm test`, 1251 de 1251 en 60 archivos, en verde;
  desde `backend/`, `FictitiousDocumentsTest`, 3 de 3. Playwright (el menú a 375 px está en su caso):
  `e2e/configuracion-clinica.spec.ts`, 1 de 1.

**Validación.** El usuario aceptó los 10 desvíos, pidió la revisión y, con ella, el cierre (R-081). El desvío 10 de la 2.4 (a 375 px el menú se salía de la pantalla) queda resuelto con el menú plegable, validado en Edge a 375 y 768 px y en Playwright a 375 px. Evidencia: Claude Code, 5 de 5
en Edge sin ventana contra `syncvet.test` (arriba, en la implementación; la demo quedó como estaba); Playwright,
`e2e/configuracion-clinica.spec.ts`, 1 de 1 en la implementación y de nuevo después de la corrección del menú (28 casos en total: el
teléfono visto después de recargar, el cuerpo sin `ruc`, el logo subido, visto en la barra después de recargar, reemplazado y
quitado, y el menú a 375 px). Corrida del cierre (2026-11-17, en la revisión, sobre el código de `26e4339`), desde `frontend/`:
`typecheck` y `build` sin errores ni avisos; `lint`, en verde después de corregir una aserción de un test (repetidos `lint` y ese
archivo); `npm test`, 1251 tests en 60 archivos, en verde; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-17): 1251 tests en 60 archivos, en verde.

### Entrega 2.6

Estado: **COMPLETADO** el 2026-11-17 (R-082). La auditoría (listado con los filtros de fecha en Asunción y el detalle de los
cambios), solo para el administrador y de solo lectura, en `feature/front-2-6-auditoria` (desde `feature/front-2-5-configuracion`,
en el cierre de la 2.5, `1af859b`), sin integrar a `main` (se integra al cerrar la F2). Commits: `120739f` (WIP de la
implementación), `9a17e41` (WIP de la revisión) y el de este cierre (R-082: la documentación).

**Implementación** (el 2026-11-17). Antes de empezar se
comparó el diseño con la API real (`IndexAuditLogRequest`, `ApiDateTime::FILTER_FORMAT`, `AuditLogResource`,
`ShowAuditLogResource`, `AuditEvent`, `AuditLog::AUDITABLE_TYPES`, `AuditLogger::MASKED` y los permisos): coinciden.

* **Hecho.** `features/audit` (nuevo): `/configuracion/auditoria` (`audit-logs.index`) con su tarjeta "Auditoría" en
  `/configuracion`, y `/configuracion/auditoria/$id` (`audit-logs.show`, con `loader`, `notFound()` y `key={id}`); solo lectura.
  El listado: fecha y hora en Asunción (`formatDateTime`, enlace al detalle), el usuario (enlace que filtra por él; "—" sin
  usuario), el evento, el registro ("Cliente n.º 12", enlace que filtra por el tipo y el número) y la IP; los filtros en la URL
  (`?desde=&hasta=&usuario=&evento=&entidad=&registro=&buscar=&pagina=`), validados en `validateAuditListSearch` con todas sus
  claves; `desde` y `hasta` suman a `TEXT_SEARCH_PARAMS`; la búsqueda con `useSearchText` (300 ms); el usuario con
  `GET users?include_inactive=1` y uno que la lista no trae como "Usuario n.º …"; "Esta página no existe." fuera de rango. Las
  fechas (`features/audit/dates.ts`): en la URL, la hora de pared de Asunción (`2026-11-09` o `2026-11-09T13:00`); a la API, en
  UTC con `TZDate` (nunca la zona de la PC): la fecha sola es el día completo ("desde" a las 00:00:00 y "hasta" a las 23:59:59, el
  comienzo del día siguiente menos un segundo, así también en un día de 23 o 25 horas) y con hora, "desde" con :00 y "hasta" con
  :59. "Desde", "Hasta" (con su hora) y el n.º de registro se aplican con "Filtrar" (`filters.ts`: las reglas de la API con sus
  mensajes, en el campo y con el foco, sin cambiar la URL ni pedir con un error). El detalle: la fecha con segundos
  (`formatDateTimeSeconds`, nuevo en `lib/format/dates.ts`), el usuario, el evento, el registro, la IP, el navegador y los cambios
  (el campo con el nombre de la API; "antes" y "después" según los lados que manda la API; `null` y `''`, "(vacío)"; una lista o
  un objeto, su JSON en un `<pre>`; todo como texto). `maskAuditValues` (`features/audit/mask.ts`) cambia por `********` el valor
  de una clave con `password` o `token` y por `"(archivo)"` el de una clave `*_path` o un texto `clinics/{id}/…`, en cualquier
  nivel. `PERMISSIONS` suma `audit-logs.index` y `.show`.
* **Pruebas.** Vitest: `audit/dates.test.ts` (31: el día completo, con hora, "hasta" con :59, el invierno de 2024 en UTC-4, el
  día de 25 horas del 23/03/2024 y el de 23 horas del 01/10/2023, las formas inválidas y la comparación de la hora de pared),
  `audit/mask.test.ts` (8), `audit/search.test.ts` (24: `validateSearch`, el pedido, el formulario con los mensajes comparados con
  `IndexAuditLogRequest.php` y las listas de eventos y tipos comparadas con `AuditEvent.php` y `AuditLog.php`) y
  `audit/AuditPages.test.tsx` (17, con MSW: la tarjeta, el recepcionista y el veterinario a `/sin-permiso` sin pedir la
  auditoría, la fila en Asunción, el pedido con la fecha sola y con hora, "hasta" antes de "desde" sin pedido, el registro sin el
  tipo, los filtros de la URL, los enlaces de la fila, la espera de 299 y 300 ms con `?buscar=1e3`, la página fuera de rango y el
  detalle de `updated`, `created` (con contraseña, token, ruta y HTML que la API dejara pasar), `deleted`, un ingreso, el 404 y la
  vuelta al listado con los filtros). Ningún test existente se tocó. Mutaciones: 4 de 4 detectadas, revertidas desde una copia
  fuera del repositorio (los archivos son nuevos): "hasta" sin :59 (4 fallas); la conversión con la zona de la PC (`new Date`
  local en lugar de `TZDate`: 12 fallas); `maskAuditValues` sin recorrer lo anidado (2 fallas); el detalle sin `maskAuditValues`
  (1 falla). Playwright: `e2e/auditoria.spec.ts` (nuevo), 1 de 1 en 30,5 s (29 casos en total): cambiar las notas de Carlos Duarte,
  filtrar la auditoría por hoy (solo fecha, el pedido en UTC de 03:00:00Z a 02:59:59Z), el tipo cliente y su n.º, abrir el detalle
  con las notas antes y después, volver con los filtros y, con "desde" un minuto después del cambio, la lista vacía; las notas
  quedan como estaban. Corrida final, desde `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1331 de
  1331 en 64 archivos, en verde (en la primera corrida completa); desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

| # | Desvío | Motivo |
|---|---|---|
| 1 | La fecha sola no se manda tal cual (`2026-11-09`): el frontend arma el día completo de Asunción con `TZDate` y lo manda como dos instantes | Lo pidió el usuario al empezar la entrega; la API da el mismo resultado (en Edge, el 16/11: 445 registros en la pantalla y 445 contados por su `created_at` en Asunción) |
| 2 | Las fechas con hora van en UTC (`2026-11-09T16:00:00.000Z`), no con `toApiDateTime` (`-03:00`) | El mismo instante; el pedido de la entrega decía "la conversión a UTC". `toApiDateTime` no cambió |
| 3 | El tipo del registro va en la URL como `entidad`, no como `tipo` | `tipo` ya es de `/clientes` (`persona`, `empresa`) y el router junta las búsquedas de todas las rutas en los enlaces sin `from`: con `tipo`, `typecheck` fallaba en `ClientListPage.tsx`. `entidad` no toca otros módulos |
| 4 | "Desde", "Hasta", sus horas y el n.º de registro se aplican con "Filtrar"; la búsqueda, el usuario, el evento y el tipo cambian la URL en el acto | Una fecha a medio elegir no es un filtro, y así las reglas (la forma, "hasta" antes de "desde", el registro sin el tipo) se muestran antes de cambiar la URL y sin pedir nada |
| 5 | Con la URL editada, "hasta" anterior a "desde" se descarta en `validateSearch` (queda "desde"), sin un mensaje | La regla de los listados: un valor inválido de la URL vuelve al valor por defecto |
| 6 | `maskAuditValues`: `null` queda `null` ("(vacío)"); una clave con `password` o `token` en cualquier parte del nombre y sin distinguir mayúsculas (`api_token`, `password_confirmation`); una lista o un objeto bajo esa clave se tapa entero | Un valor vacío no tiene nada que ocultar; el resto, para tapar también lo que la API no lista hoy |
| 7 | Sumados: "Quitar los filtros" (con algún filtro) y los usuarios inactivos marcados "(inactivo)" en el selector. Los nombres de los tipos en castellano son del frontend (la API manda solo el tipo corto): el test compara con el PHP solo los valores | Comodidad del listado; sin endpoint de tipos (H2) |
| 8 | Playwright no tiene el caso "el alta de un usuario en la auditoría sin su contraseña" del diseño | El pedido de la entrega era un caso; lo cubren el test de la página con MSW (una contraseña, un token y una ruta que la API dejara pasar) y el control 3 en Edge (un registro real con `password` en `********`) |
| 9 | En Edge, el control 1 falló en la primera corrida por el script (bajó los 2000 registros más recientes y eligió un día del que tenía solo una parte: 159 de 1016); se corrigió el script para elegir un día completo y se repitió entero, 8 de 8 | Una falla del script, no de la pantalla |
| 10 | Después de la corrida final, en Edge, "Todos los eventos" se veía cortado a 1280 px: los selectores pasaron a `col-lg-3` y se repitieron Prettier, ESLint y los tests relacionados de `AuditListPage.tsx` (531 de 531 en 25 archivos), más un control en Edge (el 9). A 1280 px, el texto de ejemplo de la búsqueda queda cortado al final ("…o el emai"); la etiqueta accesible está completa | La regla del presupuesto: solo el paso y los tests del archivo tocado |

**Validación en Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID): 9 de 9, de solo lectura. (1)
fecha sola en el borde del día: el 16/11, 445 registros en la API y 445 contados por su `created_at` en Asunción, pedido de
`2026-11-16T03:00:00.000Z` a `2026-11-17T02:59:59.000Z`; (2) con hora: "desde" y "hasta" a las 10:52, el registro de las
10:52:58 incluido (`to` con :59); (3) un detalle con `password` en `********`, sin un hash en la página; (4) un detalle con
`roles` (listas) en `<pre>`, sin desbordes; (5) a 375 px, el listado y el detalle sin desplazamiento horizontal de la página (la
tabla se desplaza dentro de su tarjeta, como los demás listados); (6) la URL con filtros recargada: los campos con lo de la URL;
(7) la consola sin errores; (8) el recepcionista: el listado y el detalle a `/sin-permiso`; (9) los selectores sin cortar a 1280 px,
después del desvío 10. La demo no cambió: no se escribió nada (los ingresos y salidas del script quedan en la auditoría, como en
las validaciones anteriores; su token de la API se revocó); `admin-a`, `recepcion-a` y `vet-a`, sin tocar. Capturas fuera del
repositorio, en la PC de la validación (`%TEMP%\claude\…\scratchpad\capturas-2-6`: `01-listado-fecha-sola.png`,
`01b-listado-escritorio.png`, `02-listado-con-hora.png`, `03-detalle-sensibles.png`, `04-detalle-lista.png`,
`05-celular-listado.png`, `05b-celular-detalle.png`).

**Revisión** (el 2026-11-17, sobre el WIP `120739f`, diff con `1af859b`; el usuario aceptó los 10 desvíos). Se revisaron las
fechas (la fecha sola como día completo de Asunción, "hasta" con :59, los días de 23 y 25 horas, todo con `TZDate` y nunca con la
zona de la PC), que una fecha inválida o "desde" posterior a "hasta" no llegue a la API (ni desde el formulario ni desde la URL),
`maskAuditValues` (claves sensibles en cualquier nivel y en listas, sin distinguir mayúsculas; en los modelos de hoy, ninguna clave
no sensible tiene `password`, `token` o `_path`: las únicas son `password`, `remember_token`, `token`, `photo_path`, `kude_path`,
`pdf_path` y `xml_path`), que todo valor se muestre como texto (React lo escapa; JSON en `<pre>`), los filtros en la URL (recarga,
"Atrás" con el formulario vuelto a montar por `key`, página fuera de rango, la espera de la búsqueda con `useSearchText`, que se
cancela al navegar a otra página), `entidad` sin chocar con `tipo` de `/clientes`, los permisos (las dos rutas con
`requirePermission`, la tarjeta con `audit-logs.index`, el ítem "Configuración" del menú solo para el administrador) y 375 px.
Ningún hallazgo cambia el comportamiento.

* **Corregido.** (1) Desvío 10, el texto de ejemplo de la búsqueda: "Buscá por el nombre o el email del usuario" se cortaba; a
  1280 px el campo deja 147 px de texto, y "Buscá por nombre o email" (163 px) también se cortaba, así que quedó
  **"Nombre o email"** (101 px a 1280 y 115 de 203 a 375; la etiqueta accesible sigue "Buscar en la auditoría"). (2) A 375 px,
  "Filtrar" quedaba alineado con la etiqueta del n.º de registro y no con el campo (el ajuste de `.audit-filter-submit` era solo
  desde 768 px; en un celular comparte la fila con ese campo): ahora vale en todos los anchos (`styles.css`).
* **Mutaciones** (3 de 3 detectadas, revertidas con `git checkout`): las claves sensibles distinguiendo mayúsculas (`mask.test.ts`,
  1 falla); `registro` sin `entidad` aceptado en la URL (`search.test.ts`, 4 fallas); "hasta" con fecha sola como las 23:59:59 de
  ese día en lugar del comienzo del siguiente menos un segundo (`dates.test.ts`, 4 fallas: el día de 25 horas).
* **En Edge** (sin ventana, contra `syncvet.test` con Vite en `:5180`, detenido por su PID; 3 controles): el texto de ejemplo
  medido a 1280 y a 375 px, sin desplazamiento horizontal de la página, y "Filtrar" alineado a 375 px. Cada control ingresó y
  salió como `admin-a` (quedan en la auditoría); el primer intento falló por el script (esperaba visible el nombre de la barra,
  oculto a ese ancho) antes de salir, así que ese token queda hasta su vencimiento (12 horas). Capturas fuera del repositorio
  (`%TEMP%\claude\…\scratchpad`: `revision-buscador-1280.png`, `revision-buscador-375.png`).
* **Pruebas.** `vitest related` de `AuditListPage.tsx` y `styles.css`: 531 de 531 en 25 archivos. Corrida final, desde
  `frontend/`: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test`, 1331 de 1331 en 64 archivos, en verde; desde
  `backend/`, `FictitiousDocumentsTest`, 3 de 3. Playwright no se corrió: `auditoria.spec.ts` no usa el texto de ejemplo y corre a
  ancho de escritorio.

**Validación.** El usuario aceptó los 10 desvíos, pidió la revisión y, con ella, el cierre (R-082). Evidencia: Claude Code, 9 de
9 en Edge sin ventana contra `syncvet.test` en la implementación y 3 controles en la revisión (de solo lectura; solo los ingresos
y las salidas quedan en la auditoría); Playwright, `e2e/auditoria.spec.ts`, 1 de 1 en la implementación (29 casos en total; la revisión no tocó lo que cubre).
Corrida del cierre (2026-11-17, en la revisión, sobre el código de `9a17e41`), desde `frontend/`: `typecheck`, `lint` y `build`
sin errores ni avisos; `npm test`, 1331 tests en 64 archivos, en verde; desde `backend/`, `FictitiousDocumentsTest`, 3 de 3.

Corrida de `npm test` del frontend (2026-11-17): 1331 tests en 64 archivos, en verde.
