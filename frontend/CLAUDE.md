# CLAUDE.md — SyncVet (Frontend)

Reglas propias del frontend. Las comunes (proceso, voseo, Git, documentación por cambio) están en el [`CLAUDE.md`](../CLAUDE.md) de
la raíz; el contrato de la API, en [`backend/CLAUDE.md`](../backend/CLAUDE.md) §4. El diseño de la F0 (con la sesión de la 0.2a y la 0.2b) está
en [`f0-base.md`](../docs/03-plan-desarrollo/fases/frontend/f0-base.md).

## 1. Estado actual

| Dato | Valor |
|---|---|
| Fase | F2 (datos maestros), EN CURSO, con el diseño aprobado (R-074) en [`f2-datos-maestros.md`](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md). Antes, F1 (clientes y pacientes), COMPLETADA el 2026-11-16 (R-072) e integrada a `main`, con front-01 (R-065) y sus entregas 1.1 (R-066), 1.2 (R-067), 1.3a (R-068), 1.3b (R-069), 1.4 (R-070) y 1.5 (R-071) ([cierre](../docs/03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#cierre-de-la-f1)). Antes, F0 (base), en [`f0-base.md`](../docs/03-plan-desarrollo/fases/frontend/f0-base.md): COMPLETADA (R-063) e integrada a `main`, con sus entregas 0.1 (esqueleto, R-060), 0.2a (sesión, R-061) y 0.2b (permisos, sucursal, `/perfil` y Playwright, R-062) ([cierre](../docs/03-plan-desarrollo/fases/frontend/f0-base.md#cierre-de-la-f0)) |
| Entrega | La 2.6 (auditoría), COMPLETADO (R-082) en `feature/front-2-6-auditoria`, sin integrar a `main` (se integra al cerrar la F2): la tarjeta "Auditoría" y `/configuracion/auditoria` con su detalle, solo para el administrador y de solo lectura; los filtros de fecha y hora en Asunción, mandados en UTC (`features/audit/dates.ts`); los valores de los cambios como texto y sin contraseñas, tokens ni rutas (`maskAuditValues`) ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-26)). Antes, la 2.5 (configuración de la clínica y logo), COMPLETADO (R-081) en `feature/front-2-5-configuracion`, sin integrar a `main` (se integra al cerrar la F2): la tarjeta "Clínica" y `/configuracion/clinica`, solo para el administrador (los datos, las claves y el logo; el RUC solo para leer y nunca en el pedido); el logo en la barra para los tres roles; el menú plegado detrás de "Menú" por debajo de 992 px (`src/app/MainMenu.tsx`); el control de una imagen, común a la foto y al logo (`src/lib/files/image.ts`) ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-25)). Antes, la 2.4 (servicios), COMPLETADO (R-080) en `feature/front-2-4-servicios`, sin integrar a `main` (se integra al cerrar la F2): "Servicios" en el menú y `/servicios` con el detalle para los tres roles; el alta, la edición, inactivar o activar y la baja, solo para el administrador; el precio en guaraníes, mandado como texto de solo dígitos ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-24)). Antes, la 2.3 (veterinarios), COMPLETADO (R-079) en `feature/front-2-3-veterinarios`, sin integrar a `main` (se integra al cerrar la F2): "Veterinarios" en el menú y `/veterinarios` para los tres roles, sin la comisión ni las notas; el detalle, el alta desde el usuario, la edición, inactivar o activar y la baja, solo para el administrador; `dialogError` en `src/lib/api/dialog-error.ts` ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-23)). Antes, la 2.2b (usuarios: inactivar, reactivar y baja), COMPLETADO (R-077) en `feature/front-2-2b-usuarios`, sin integrar a `main` (se integra al cerrar la F2): "Inactivar" o "Reactivar" y "Eliminar" en el detalle del usuario, ocultos en el propio, con `USER_IN_USE` que ofrece inactivar ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-22b)). Antes, la 2.2a (usuarios: listado, detalle, alta y edición), COMPLETADO (R-076) en `feature/front-2-2a-usuarios`, sin integrar a `main` (se integra al cerrar la F2): la tarjeta "Usuarios" y `/configuracion/usuarios`, solo para el administrador, con la contraseña de otro usuario detrás de "Cambiar la contraseña" y el propio usuario sin ella ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-22a)). Antes, la 2.1 (sucursales y depósitos, con la tarea 3c), COMPLETADO (R-075) en `feature/front-2-1-sucursales`, sin integrar a `main` (se integra al cerrar la F2): `/configuracion` como página de entrada y `/configuracion/sucursales`, solo para el administrador ([resultado](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#entrega-21)). Antes, `chore/post-f1` (R-073), integrada a `main` en `1daee8c`: la preparación de Playwright que reutiliza `syncvet_e2e` si las migraciones no cambiaron, la espera de la búsqueda que se cancela al navegar (deuda 23) y el límite propio del test de voseo (deuda 24). Antes, la 1.5 (especies y razas: listado con "Incluir las inactivas", alta, cambio de nombre, activar o inactivar y baja), COMPLETADO (R-071): [resultado](../docs/03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#entrega-15). Las de la F1, en su [Resultado](../docs/03-plan-desarrollo/fases/frontend/f1-clientes-y-pacientes.md#resultado) |
| Siguiente | El cierre de la F2, en `feature/front-2-6-auditoria` ([criterio de cierre](../docs/03-plan-desarrollo/fases/frontend/f2-datos-maestros.md#criterio-de-cierre)): Playwright completo, la suite del backend en MySQL y en serie y Newman de todas las colecciones; sin empezar hasta la autorización del usuario |
| Tests | Vitest: 1331 tests en 64 archivos, en verde (corrida del cierre de la 2.6, el 2026-11-17, sobre el código de `9a17e41`, registrada en R-082 y en "Resultado" de `f2-datos-maestros.md`); Playwright: 29 casos contra `syncvet_e2e` (con `auditoria.spec.ts`); la última corrida completa, 22 de 22 en 3,9 min (`chore/post-f1`); la 2.6 corrió su caso nuevo, 1 de 1 |

El estado de cada fase está en el [roadmap](../docs/03-plan-desarrollo/README.md#fases-del-frontend).

## 2. Comandos

Desde `frontend/`, con Node 26 (`.nvmrc`; `engine-strict` rechaza otra versión al instalar):

| Comando | Qué hace |
|---|---|
| `npm ci` | Instala exactamente lo de `package-lock.json` (`npm install <paquete>` para sumar uno, con versión exacta: `save-exact`) |
| `npm run dev` | Servidor de desarrollo en `http://localhost:5173`, con `/api` hacia el backend (`API_PROXY_TARGET`) |
| `npm run build` | `tsc -b` y el build de producción en `dist/` |
| `npm run preview` | Sirve `dist/` (también con el proxy de `/api`) |
| `npm run typecheck` | `tsc -b` (la aplicación, los tests y la configuración) |
| `npm run lint` | ESLint, sin avisos (`--max-warnings 0`) |
| `npm run format` | Prettier sobre todo el proyecto (`format:check` solo revisa) |
| `npm test` | Vitest, una vez (`test:watch`, en modo observación) |
| `npm run test:e2e` | Playwright contra el backend real y la base `syncvet_e2e`, que queda como recién creada en cada corrida: si las migraciones no cambiaron (su huella, en `frontend/e2e/.e2e-migrations`), se vacían sus tablas y se vuelve a sembrar, sin recrearla; `E2E_FRESH=1` la recrea siempre (R-073). Levanta el backend en `:8010` y Vite en `:5174`; la contraseña de los usuarios del `DemoSeeder` se genera en cada corrida y queda en `frontend/e2e/.e2e-credentials` (ignorado por Git), para entrar a mano a `syncvet_e2e`; qué necesita, en [`setup.md`](../docs/04-desarrollo/setup.md#pruebas-de-punta-a-punta-playwright) |

Antes de dar algo por terminado: `typecheck`, `lint`, `test` y `build`, los cuatro sin errores ni avisos. Las dependencias se
verifican en npm antes de instalarlas y se auditan con `npm audit`.

## 3. Stack

Las versiones exactas están en `package.json` (y en [`technology-stack.md`](../docs/01-arquitectura/technology-stack.md)); las
elecciones, con su alternativa descartada, en R-058.

| Tema | Elección |
|---|---|
| Base | React 19, Vite 8 (`@vitejs/plugin-react`), TypeScript 6 estricto (no TS 7) |
| Interfaz | Tabler: los estilos de `@tabler/core` (sin su JS) y `@tabler/icons-react`. Sin Tailwind |
| Rutas | TanStack Router, rutas por archivo (plugin de Vite) |
| Datos del servidor | TanStack Query sobre el cliente propio de `src/lib/api/client.ts` (fetch, sin Axios) |
| Formularios | React Hook Form con Zod (`@hookform/resolvers`) |
| Fechas | date-fns 4 con `@date-fns/tz` |
| Tests | Vitest, Testing Library, MSW y jsdom; Playwright (`@playwright/test`, con el Edge instalado) desde la 0.2b |
| Calidad | ESLint (`typescript-eslint` estricto con tipos, reglas de hooks) y Prettier |

## 4. Estructura

```text
frontend/
├── .nvmrc, .npmrc       Node 26; engine-strict y save-exact
├── .env.example         API_PROXY_TARGET (el backend del proxy de desarrollo)
├── vite.config.ts       React, el plugin de TanStack Router, el proxy de /api y Vitest
├── eslint.config.js     Reglas, con fetch y XMLHttpRequest prohibidos fuera del cliente y sin dangerouslySetInnerHTML
├── playwright.config.ts Playwright: el backend y Vite en sus puertos propios, en serie, la preparación de syncvet_e2e
├── e2e/                 Casos de punta a punta (*.spec.ts), global-setup.ts y support/ (la guarda de la base, con su test de Vitest)
└── src/
    ├── main.tsx         Arranque: estilos de Tabler y <App>
    ├── routeTree.gen.ts Generado por el plugin del router (se versiona; no se edita a mano)
    ├── routes/          Una ruta por archivo; el nombre es la URL en castellano
    ├── app/             Proveedores (App), router (con search-params.ts: la búsqueda escrita como texto), layouts, menú y páginas de error
    ├── lib/api/         client.ts, types.ts (contrato), errors.ts (ApiError y la tabla de error_code), query.ts, form-errors.ts, dialog-error.ts
    ├── lib/auth/        La sesión: storage.ts, channel.ts (entre pestañas), session.ts (SessionManager), redirect.ts, use-session.ts; permissions.ts (can)
    ├── lib/format/      dates.ts (America/Asuncion) y money.ts (guaraníes)
    ├── components/ui/   Capa propia sobre Tabler: Button, TextField, TextareaField, CheckboxField, CheckboxGroupField, RadioGroup, SearchField, SelectField, DateField, TimeField, FileField, BlobImage, Alert, Card, DataTable, ConfirmDialog, ErrorPage, Loading, avisos
    ├── features/<m>/    api.ts, types.ts, componentes y páginas de cada módulo (auth; clients, desde la 1.1; patients, desde la 1.3a, con la foto desde la 1.4; species, desde la 1.5; branches, desde la 2.1; users, desde la 2.2a; veterinarians, desde la 2.3; services, desde la 2.4; settings, desde la 2.5; audit, desde la 2.6)
    └── test/            Configuración de Vitest, el servidor de MSW, respuestas del contrato y el test de voseo
```

Rutas: los archivos con `_` adelante son layouts sin URL propia: `_publico` (`/ingresar`) y `_sesion` (`/`, `/perfil`,
`/configuracion`, solo para el administrador: desde la 2.1, la página de entrada (`src/app/ConfigurationPage.tsx`, una tarjeta por
sección que el usuario puede ver) con sus secciones debajo (`configuracion/index.tsx`, `configuracion/sucursales/…` y, desde la 2.2a, `configuracion/usuarios/…`), `/clientes` y `/clientes/$id`, desde la 1.1, `/clientes/nuevo` y
`/clientes/$id/editar` (archivo `$id_.editar.tsx`: con `_`, no se dibuja dentro del detalle), desde la 1.2, y `/sin-permiso`; exige
la sesión desde la 0.2a). Una URL desconocida muestra la página de no encontrado (`notFoundComponent` de la raíz). Las de los módulos
siguen la misma regla: `/clientes`, `/clientes/$id`, `/pacientes`, `/agenda`, `/turnos`, `/configuracion`.

## 5. Convenciones

* **Textos en español con voseo** ("Ingresá tu email", "Elegí tu sucursal"). El `message` de la API ya viene así: se muestra tal
  cual, nunca se reescribe. `src/test/voseo.test.ts` revisa las cadenas y el texto de JSX de `src/` con las listas de `VoseoTest` del
  backend: un verbo nuevo en un texto se suma a su lista.
* **Decidir por `error_code`**, nunca por el texto ni solo por el HTTP: la [tabla](#6-qué-hace-la-interfaz-con-cada-código-de-error) vive
  en `src/lib/api/errors.ts` (`errorTreatment`, `userMessage`).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`** (`api.get`, `api.post`…, `getPage` para los listados): pone
  `Accept: application/json` y el token y convierte cualquier falla en un `ApiError` (`status`, `errorCode`, `message`, `errors`,
  `retryAfter`). Un 204 (o un 2xx sin cuerpo) es un éxito sin datos: `data` es `null` (el endpoint se tipa con `T = null`); un
  2xx con un cuerpo que no es JSON es `HTTP_ERROR`. Una cancelación (la señal abortada) se propaga tal cual, no es
  `NETWORK_ERROR`. ESLint prohíbe en el resto del código `fetch` (también `window.fetch`, `globalThis.fetch` y `self.fetch`) y
  `XMLHttpRequest`, y en todo el código `dangerouslySetInnerHTML`.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` de cada módulo. `createQueryClient()`
  (`src/lib/api/query.ts`): una lectura se reintenta una vez solo con una falla de red o un 5xx; una mutación, nunca.
* **Claves de las consultas** (`src/lib/api/query-keys.ts`, desde la 0.2b): `['me']` es el usuario de la sesión; una consulta
  cuyos datos dependen de la sucursal actual (la cola del día, los turnos, la caja…) arma su clave con
  `branchKey(idDeLaSucursal, …)` (`['branch', 10, 'appointments', 'queue', { date }]`), así los datos de dos sucursales no se
  mezclan y el cambio de sucursal invalida solo esas (`invalidateBranchQueries`); las demás (catálogos, clientes, pacientes: son
  de la clínica) empiezan con su módulo (`['clients', …]`) y no se tocan al cambiar de sucursal.
* **Listados** (desde la 1.1; el modelo es `/clientes`, `features/clients/ClientListPage.tsx`, y sus tests):
  * **Búsqueda, filtros y página en la URL** (`/clientes?buscar=&tipo=&pagina=`): la ruta los valida en `validateSearch` con Zod
    (`features/clients/search.ts`); un valor inválido vuelve al valor por defecto, y los valores por defecto no van en la URL.
    `validateSearch` devuelve **siempre todas sus claves** (`undefined` si el valor se descarta): el router mezcla lo que
    devuelve sobre la búsqueda cruda, así que una clave que falta deja pasar el valor de la URL.
  * **Lo escrito es siempre texto en la URL**: el router lee cada valor como JSON (`?buscar=1e3` sería el número 1000); las
    claves de `TEXT_SEARCH_PARAMS` (`src/app/search-params.ts`, el `parseSearch` y el `stringifySearch` del router) se leen y
    se escriben tal cual. `buscar` ya está y vale para todos los listados; otro parámetro de texto se suma a esa lista (desde la 2.6, `desde` y
    `hasta` de la auditoría). Un parámetro nuevo no reutiliza el nombre de otro listado con otros valores: el router junta las
    búsquedas de todas las rutas en los enlaces sin `from` (por eso el tipo de la auditoría es `entidad` y no `tipo`, que es de
    `/clientes`).
  * **El campo de búsqueda es local** (`SearchField`, con el hook común `useSearchText` de `src/lib/hooks/use-search-text.ts`, desde
    la 1.3a: lo usan `/clientes` y `/pacientes`) y cambia la URL 300 ms después de la última tecla (`useDebouncedValue`,
    `src/lib/hooks/`, solo con primitivos), sin los espacios de los extremos, con `replace: true` y de vuelta en la página 1; el
    filtro y la página cambian la URL con historial ("Atrás" vuelve). El campo toma lo de la URL **solo si el cambio vino de
    afuera** ("Atrás", un enlace): no si es lo que el campo mandó y todavía no llegó, ni si coincide con lo escrito sin los
    espacios de los extremos (así no se pierden el espacio de "Laura " ni la tecla escrita mientras la URL cambiaba). Si sale una
    navegación a otra página (un clic en un resultado) mientras corre la espera, la espera se cancela y la URL no cambia (deuda 23,
    R-073): `useSearchText` compara la ruta de `router.latestLocation`, que cambia apenas sale la navegación, con la del listado.
  * **La consulta** usa `placeholderData: keepPreviousData` (una respuesta vieja no pisa a la nueva: cada búsqueda tiene su
    clave) y 20 por página, fijo. Mientras llega, `SearchField` recibe `busy`: el indicador y "Buscando…" en su región
    `role="status"`, que está siempre en el DOM.
  * **Página fuera de rango** (`current_page > last_page`, por una URL editada o después de borrar): `DataTable` muestra "Esta
    página no existe." con el `firstPageLink` del listado (un `<Link>` con la misma búsqueda, sin `pagina`), no el vacío.
  * **Tests**: la espera con relojes falsos y los 300 ms escritos en el test (299 ms sin URL ni pedido, 300 ms con uno); el
    espacio final ("Laura ", pausa, "M"), la tecla durante la navegación, la respuesta fuera de orden, "Buscando…", la página
    fuera de rango, `?buscar=1e3` y el clic en un resultado durante la espera (R-073).
  * Un **detalle** valida su id en el `loader` (sin llamar a la API si no es un entero positivo) y convierte el 404 de la API en
    `notFound()`, con el `notFoundComponent` de la ruta, dentro del layout.
* **Contraseñas fuera de la caché de mutaciones** (0.2b): una mutación que manda una contraseña (`/ingresar`, `/perfil`) va con
  `gcTime: 0` y `mutation.reset()` en `onSettled`, para que sus variables no queden en la caché de Query. `reset()` descarta los
  callbacks de `mutate()`: los errores se tratan en las opciones de `useMutation`, con lo que necesitan (el formulario) en las
  variables (desde la 2.2a, `features/users/UserFormPages.tsx`); un test del error comprueba que la contraseña no quedó en la
  caché (con un éxito que navega, la página se desmonta y la mutación se borra igual, así que no alcanza).
* **Tipos escritos a mano** en el `types.ts` del módulo, con los nombres de la API (`snake_case`, también `meta.pagination`:
  `per_page`, `current_page`, `last_page`); no se traducen a `camelCase`.
* **Formularios**: React Hook Form con Zod; un 422 se vuelca con `applyValidationErrors` (`src/lib/api/form-errors.ts`): cada clave
  de `errors` a su campo con `setError` y foco en el primero; las demás, a una `Alert` arriba del formulario. Las claves con índice
  de Laravel (`branch_ids.0`) van al campo padre declarado (`branch_ids`), o al de un patrón con `*` (`hours.*.starts_at` recibe
  `hours.1.starts_at`); varias claves de un mismo campo juntan sus mensajes. Desde la 1.2 (el modelo es `features/clients/form.ts`,
  `ClientForm.tsx` y `ClientFormPages.tsx`):
  * **Las reglas de forma de la API, con sus mensajes**, en una función pura con sus tests (cada límite en su borde: pasa en el
    límite y falla pasándolo), comparadas con el Form Request; el largo en caracteres (`Array.from`, como `mb_strlen`). El
    navegador no rechaza nada que la API acepta: el email, solo la forma mínima (`^[^\s@]+@[^\s@]+$`); el formato completo y el
    DNS, la API.
  * **Un solo envío**: Guardar se deshabilita con `formState.isSubmitting` y con la mutación pendiente, y una `ref` deja pasar un
    solo `mutate` a la vez (se suelta en `onSettled`): un doble clic o dos envíos del formulario mandan un solo pedido. Esa `ref`
    es el hook común `useSingleAction` (`src/lib/hooks/use-single-action.ts`, desde la 2.1): `tryStart()` antes de `mutate` y
    `release()` en `onSettled`; también en los diálogos con una acción.
  * **El teléfono** (desde la 2.1): la regla de clientes es común (`src/lib/phone.ts`: `PHONE_PATTERN`, `PHONE_MESSAGE` y
    `normalizePhone`, que quita los espacios y guiones escritos antes de validar y mandar); la usan clientes y sucursales.
  * **Los nombres de personas** (desde la 2.2a): `PersonName` de la API es común (`src/lib/person-name.ts`:
    `PERSON_NAME_PATTERN`); la usan clientes y usuarios.
  * **El largo en caracteres, como la API** (desde la 1.3a): los textos que admiten letras fuera del plano básico (`\p{L}`, o sin
    regla de caracteres) no llevan `maxLength` nativo, que cuenta unidades UTF-16 (un emoji es 2); el largo lo controla Zod con el
    mensaje de la API. Lo conservan los campos de solo caracteres de una unidad (documento, email, microchip).
  * **Una fecha a medio escribir** (`DateField`, desde la 1.3a): el `<input type="date">` da `''` con `validity.badInput`; al
    enviar se lee del campo y se muestra el error de formato de la API, sin mandar (nunca se borra una fecha guardada sin aviso).
  * **Los opcionales vacíos van en `null`** y la edición manda el formulario completo; guardar y "Cancelar" reemplazan la entrada
    del formulario en el historial (`replace`).
* **Componentes de Tabler a través de `src/components/ui`**: las páginas no repiten el marcado de Tabler. Sin Tailwind ni otra
  biblioteca de componentes; sin el JS de Tabler (menús, diálogos y avisos son componentes de React).
* **Confirmación antes de borrar** (desde la 1.2): `ConfirmDialog` (`role="dialog"`, `aria-modal`), con el foco en "Cancelar" al
  abrir. Mientras está abierto escucha las teclas en `document` (Escape cierra; Tab y Shift+Tab recorren solo el diálogo), devuelve
  el foco a "Cancelar" si sale del diálogo o al terminar la espera y bloquea el desplazamiento del fondo; al cerrar, el foco vuelve
  al botón que lo abrió. Los botones inactivos del diálogo usan `aria-disabled` (`Button` con `focusableWhenInactive`): con
  `disabled`, el botón suelta el foco. El error de negocio (`*_IN_USE`) va dentro del diálogo, con la acción que corresponda o solo
  "Cerrar" (`canConfirm={false}`). Desde la 2.3, `dialogError` (`src/lib/api/dialog-error.ts`) decide qué hace un diálogo con el error de su acción: el 404, aviso y al listado; un 422 o un error de negocio, el mensaje y solo "Cerrar"; lo demás, el mensaje y el botón para probar de nuevo (lo usan sucursales, usuarios y veterinarios). Desde la 1.3b: `tone` (`danger` por defecto; `primary` para lo que no borra), `size` (`md`, con
  campos o una lista) y `confirmDisabled` (falta elegir algo); al terminar la espera con un error, el foco va al primer campo con
  error del diálogo (`aria-invalid`) o, si no hay, a "Cancelar".
* **Caché después de borrar o mover un registro hijo** (desde la 1.2 y la 1.3b): los listados y los detalles que lo mostraban y no
  están a la vista se vacían con `resetQueries` (no se invalidan), así al entrar se espera la lista nueva y no se ve un momento la
  vieja: la baja de un paciente vacía el detalle de su dueño, y el cambio de dueño, los de los dos clientes (`resetClientDetails`).
* **Fechas y dinero**: la API manda las fechas con hora en UTC (`Z`); se muestran con `formatDate`, `formatDateTime` y `formatTime`
  (`src/lib/format/dates.ts`, siempre en `America/Asuncion`, nunca con la zona de la PC); se mandan con `toApiDateTime` (la hora de
  pared de Asunción con `TZDate(...).toISOString()`, con su desplazamiento `-03:00`); "hoy" es `todayInAppZone()`. Con segundos, `formatDateTimeSeconds` (desde la 2.6). Los filtros de fecha de la
  auditoría (desde la 2.6, `features/audit/dates.ts`): en la URL, la hora de pared de Asunción sin zona (`2026-11-09` o
  `2026-11-09T13:00`); a la API, en UTC con `TZDate`: la fecha sola es el día completo ("hasta", el comienzo del día siguiente
  menos un segundo, también en un día de 23 o 25 horas) y "hasta" con hora lleva los segundos en 59; "hasta" antes de "desde" se
  compara como texto de la hora de pared, sin pasar por `Date`. Una fecha sin
  hora (`birth_date`, `Y-m-d`) no es un instante: `formatDateOnly` solo cambia el formato del texto, sin pasar por `Date`
  (`formatDate` la reconoce y hace lo mismo). Guaraníes con `formatGuaranies` (`Intl.NumberFormat('es-PY')`, PYG, sin decimales):
  el texto decimal de la API se valida con una expresión regular y se formatea tal cual, sin pasar por un número de coma flotante.
  Un valor vacío (`null`, `undefined`, `''`) o inválido se muestra vacío (`''`), en las fechas y en el dinero (nunca "Gs. 0" ni
  un error que rompa el render).
* **Sesión** (0.2a, R-061; el diseño, en [Sesión (0.2)](../docs/03-plan-desarrollo/fases/frontend/f0-base.md#sesión-02)): el
  token y `expires_at` solo en `sessionStorage` (`syncvet.session`), nunca en `localStorage`, la URL ni el log; `me` en la caché de
  Query (`['me']`). Todo pasa por el `SessionManager` (`src/lib/auth/session.ts`), creado una vez por pestaña en `main.tsx`: los
  componentes lo leen con `useSessionManager()` y `useSessionState()`. Entre pestañas, `BroadcastChannel('syncvet-sesion')`
  (`channel.ts`), con cada mensaje validado completo (`isSessionMessage`): una pestaña nueva pide la sesión (`session-request`, 1000
  ms); "Salir" cierra todas (`logout` con `all: true`) y manda el `POST logout` después, sin esperarlo (5 s de límite); el
  vencimiento y un error de sesión (401, `USER_INACTIVE`, `CLINIC_NOT_AVAILABLE`) cierran solo las pestañas con ese token, y solo
  si el pedido salió con el token actual. El vencimiento se revisa con temporizadores, al volver a la pestaña (`visibilitychange`,
  `focus`, `pageshow`) y cada 15 s; el aviso previo, 10 minutos antes, con los minutos que quedan. `volver` pasa siempre por
  `safeRedirect` (solo rutas internas). Un pedido con un token explícito (`token: null` en el login, un texto en el `logout`) no toca
  la sesión.
* **Permisos** (0.2b): `can(me, permiso)` (`src/lib/auth/permissions.ts`, con los nombres de la API en `PERMISSIONS`); en los
  componentes, `useCan()` y `<RequirePermission permission>` (`src/features/auth`). Cada ruta con permiso lo declara con
  `beforeLoad: requirePermission(permiso)`: sin él, `/sin-permiso` (dentro del layout con sesión). El menú (`src/app/menu.ts`)
  lista solo los módulos hechos, con su permiso, y `visibleMenu` lo filtra. La API vuelve a controlar todo (un botón oculto no
  protege): un 403 `FORBIDDEN` al cargar una página muestra la de sin permiso con el `message` de la API
  (`defaultErrorComponent`, `RouteErrorPage`); en una acción, la alerta con el `message`. Los tests usan los permisos reales de
  los tres roles, leídos de `backend/tests/Fixtures/clinic-roles.json` (`src/test/roles.ts`, `meFor(rol)`), nunca inventados.
* **Sucursal actual** (0.2b): el selector de la barra (`BranchSelector`) con `me.branches`; `PATCH me/current-branch` y, con el
  200, `applyBranchChange` (`me` se vuelve a pedir al servidor, no se toma la respuesta del `PATCH`, y se invalidan solo las
  consultas de la sucursal, `invalidateBranchQueries`) y `branch-changed` en el canal (las demás pestañas invalidan `['me']` y
  las consultas de la sucursal). Al volver a la pestaña (`focus`, `visibilitychange`), `SessionLayout` vuelve a pedir `me`: la
  sucursal pudo cambiar en otra PC o un administrador pudo inactivarla. Sin sucursal actual, un aviso pide elegir una.
* **Descargas con token** (desde la 1.4): `api.getBlob(path, { expectedType })` (`src/lib/api/client.ts`) arma el pedido como los
  demás (el token en la cabecera, nunca en la URL) y devuelve el `Blob`; los errores JSON de la API son el `ApiError` de siempre (el
  401 cierra la sesión) y un 2xx en JSON, una página HTML o un tipo distinto de `expectedType` (la foto pide `image/`) es
  `HTTP_ERROR`. Se muestran con `BlobImage` (`src/components/ui`): la ref del `<img>`, con limpieza (React 19), crea la URL de
  objeto al montar y la libera (`revokeObjectURL`) al cambiar el `Blob` o al desmontar, sin estado. La caché de un archivo
  (`['patients', 'photo', id]`: `staleTime: Infinity`, `gcTime` de 5 minutos) se vacía al reemplazarlo (`resetQueries`: se espera
  el nuevo, nunca se ve el viejo) o al borrarlo (`Query.reset()`, sin volver a pedirlo); salir de la sesión la borra con el resto
  (`queryClient.clear()`).
* **Subir un archivo** (desde la 1.4; el modelo es `features/patients/PatientPhoto.tsx` y `photo.ts`): `FileField`, vista previa
  con `BlobImage` y un botón para mandar (`FormData`). El navegador controla como la API, con sus mensajes: desde la 2.5, con el
  control común de `src/lib/files/image.ts` (`imageFileError`, con el límite y los mensajes de cada uso: la foto, 5 MB; el logo, 2
  MB). El tamaño primero (el límite justo pasa, como `max` de Laravel en kilobytes) y el tipo **por el contenido** (los primeros bytes de un JPG, un PNG o un WEBP;
  nunca por la extensión ni el tipo declarado, que la API ignora). Un 422 o 413 con `errors.<campo>` va al campo (con el foco al
  terminar la espera, por un pedido de foco propio y no por la vuelta de `isPending`); un 413 sin `errors`, a una alerta. La
  mutación lleva `gcTime: 0` y `reset()` en `onSettled`: el archivo no queda en la caché de mutaciones. En los tests, el `FormData`
  y el `File` de Node (el `fetch` de Node no lee el `File` de jsdom).
* **Una pantalla por registro** (desde la 1.4): la ruta de un detalle dibuja su página con `key={id}`
  (`<PatientDetailPage key={id} id={id} />`): sin ella, el router reutiliza el componente al cambiar solo el `$id` y el estado local
  (un archivo elegido, un diálogo abierto, un pedido en curso) pasaría de un registro al otro. Toda pantalla de detalle nueva la
  lleva.
* **Tests**: Vitest con MSW (un pedido sin handler hace fallar el test) y Testing Library. Corren en UTC, para que mostrar una fecha
  con la zona de la PC falle. Los `.test.ts` (lógica) corren en `node` y los `.test.tsx` (componentes), en jsdom; un `.test.ts` que
  necesita el DOM lo pide con `// @vitest-environment jsdom` en la primera línea (`client.test.ts`). Se permiten `.each` y los
  bucles. Límites (desde la 1.1): `findBy*` y `waitFor` esperan hasta 3 s (`configure({ asyncUtilTimeout: 3000 })` en
  `src/test/setup.ts`) y cada test de jsdom, hasta 15 s (`testTimeout` del proyecto `dom`): con la PC cargada, el primer render
  con el layout pasaba el segundo. Una espera de tiempo se prueba con relojes falsos (`vi.useFakeTimers()` después de cargar la
  página y `vi.useRealTimers()` en `afterEach`), no con `setTimeout` reales. La cifra de tests del frontend sale de la última corrida de `npm test`, registrada en "Resultado" de la fase con la
  línea ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.``: `/validar-docs` la compara con la
  documentación (no cuenta los tests en el código). En las páginas con el layout, consultar por texto con selector: `*ByRole`
  tarda segundos en jsdom con los íconos SVG. Los textos de JSX y las cadenas de una palabra con mayúscula inicial también pasan
  por el test de voseo.

## 6. Qué hace la interfaz con cada código de error

Se muestra el `message` de la API y se decide qué hacer por el código. `NETWORK_ERROR` es el único código propio del frontend.

| `error_code` | HTTP | Trato (`errorTreatment`) | Qué hace la interfaz |
|---|---|---|---|
| `UNAUTHENTICATED` | 401 | `session-expired` | Cierra la sesión en las pestañas con ese token y lleva a `/ingresar?volver=…` con "Tu sesión terminó. Ingresá de nuevo." (también por vencimiento o un token revocado) |
| `USER_INACTIVE` | 403 | `user-inactive` | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `CLINIC_NOT_AVAILABLE` | 403 | `clinic-unavailable` | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `FORBIDDEN` | 403 | `forbidden` | Al cargar una página, `/sin-permiso`; en una acción, aviso con el mensaje y el formulario queda como estaba |
| `RESOURCE_NOT_FOUND` | 404 | `not-found` | Al cargar un detalle, la página de no encontrado; en una acción, aviso e invalidar la lista |
| `VALIDATION_ERROR` | 422 | `validation` | Cada clave de `errors` a su campo (`applyValidationErrors`); las demás, a una alerta arriba; foco en el primer campo |
| `INVALID_CREDENTIALS` | 422 | `invalid-credentials` | Mensaje en el formulario de ingreso; se borra la contraseña |
| `AUTH_TOO_MANY_ATTEMPTS`, `TOO_MANY_REQUESTS` | 429 | `throttled` | Mensaje y el botón deshabilitado con la cuenta regresiva de `Retry-After` (`retryAfter`; sin la cabecera, 60 s) |
| `CONFLICT` | 409 | `conflict` | Aviso "Alguien cambió estos datos mientras guardabas. Revisalos y probá de nuevo." con el `message`; se vuelven a pedir los datos |
| `PAYLOAD_TOO_LARGE` | 413 | `payload-too-large` | El error en el campo del archivo (`errors.photo`, `errors.logo`); sin `errors`, aviso |
| Códigos de negocio (`*_IN_USE`, `*_NOT_EDITABLE`, `USER_LAST_ADMIN`, `APPOINTMENT_*`, …) | 409, 422, 403 | `business` | Alerta con el `message` en el formulario o el diálogo; cada módulo puede sumar su trato (ofrecer inactivar, el sobreturno…) |
| `INTERNAL_SERVER_ERROR`, `SERVICE_UNAVAILABLE` | 500, 503 | `server` | Aviso "No se pudo completar. Probá de nuevo en unos minutos."; una lectura se reintenta una vez |
| `NETWORK_ERROR` (propio) | — | `network` | Aviso "No hay conexión con el servidor."; una lectura se reintenta una vez |
| `BAD_REQUEST`, `METHOD_NOT_ALLOWED`, `NOT_ACCEPTABLE`, `UNSUPPORTED_MEDIA_TYPE`, `HTTP_ERROR` | 400, 405, 406, 415, otros | `programming` | Aviso genérico y el detalle en la consola en desarrollo |
| Un código que la tabla no conoce | cualquiera | `business` | Como un código de negocio: alerta con el `message` |

Una respuesta fuera del contrato (por ejemplo, el proxy sin backend) se convierte en `INTERNAL_SERVER_ERROR` si es un 5xx y en
`HTTP_ERROR` si no.

## 7. Deuda técnica abierta

| # | Deuda | Prioridad |
|---|---|---|
| 19 | `npm audit`: 1 aviso moderado de `minimatch` 10.2.6, que traen `eslint` y `typescript-eslint` (npm 11 lo cuenta una vez por paquete: 9 al cerrar la F1); solo en las herramientas de desarrollo. Se resuelve cuando `typescript-eslint` traiga `minimatch` 11 | Baja |
| 20 | `/validar-docs`: `frontendRuns()` de `check-docs.php` no ignora los bloques de código; un `#` adentro de "Resultado" cierra la sección y se pierde la corrida registrada. Se corrige en una entrega del validador | Baja |

El detalle, en la [deuda técnica](../docs/03-plan-desarrollo/deuda-tecnica.md). Lo que quedó de la 0.1 (los desvíos 8 y 9), en su
[resultado](../docs/03-plan-desarrollo/fases/frontend/f0-base.md#entrega-01).

## 8. Velocidad

Reglas para que una entrega no se vaya en esperas (la F0.1 tardó más de 50 minutos, casi la mitad en npm y en verificaciones
repetidas):

* **Versiones fijas en el diseño y un solo `npm ci`.** Las versiones exactas de los paquetes nuevos se deciden y se anotan en el
  diseño de la entrega; la implementación las instala de una vez (`npm install <a>@x <b>@y …`) y después solo `npm ci`. Nada de
  consultar paquete por paquete con `npm view`.
* **`environment: node` para los tests sin DOM.** Preparar jsdom es la mayor parte del tiempo de la suite: los `.test.ts` corren
  en `node` (proyecto `node` de `vite.config.ts`) y solo los `.test.tsx`, o un `.test.ts` que lo pide, en jsdom.
* **Manda el [Presupuesto por entrega](../CLAUDE.md#presupuesto-por-entrega)** del `CLAUDE.md` de la raíz (Playwright, Newman,
  navegador, mutaciones y revisión).
* **Durante el trabajo, `npx vitest related <archivos tocados> --run`**, o el archivo de test puntual. La suite completa,
  `typecheck`, `lint` y `build`, una sola vez al final de la entrega.
* **Playwright**: en una entrega, solo los casos nuevos o tocados (`npx playwright test e2e/<archivo>.spec.ts`); `npm run test:e2e`
  completo, al cerrar la fase. **Navegador** (Edge sin ventana): hasta 10 controles, con capturas solo si cambió una pantalla.
* **Prettier solo sobre los archivos cambiados**: `npx prettier --write <archivos>` (por ejemplo, los de
  `git diff --name-only -- frontend`), no `npm run format` sobre todo el proyecto en cada vuelta.
* **Una entrega, un tema.** El validador de la documentación, las skills y la documentación general (roadmap, handoff,
  `technology-stack.md`) van en entregas o cierres separados, no mezclados con el código del frontend.
* En Windows, excluir `frontend/node_modules` del antivirus (los pasos, en
  [`setup.md`](../docs/04-desarrollo/setup.md#antivirus-de-windows)): sin eso, una instalación tarda varios minutos más.
* **Un Vite levantado para una prueba se detiene por el PID del proceso `vite.js`**, no deteniendo la tarea que lo lanzó
  (`npx` y `timeout` son envoltorios: cortarlos deja vivo a Vite escuchando en su puerto). El PID se busca por el puerto
  (`netstat -ano`) y se confirma con su línea de comando (`vite.js --port <puerto>`) antes de cerrarlo; después se comprueba
  que el puerto quedó libre. Así quedaron vivos el de `:5180` de la 1.2 y, probablemente, el de `:5173` de la 1.1 (revisión
  de la 1.2).
