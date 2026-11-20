# CLAUDE.md — SyncVet (Frontend)

Reglas propias del frontend. Las comunes (proceso, voseo, Git, documentación por cambio) están en el [`CLAUDE.md`](../CLAUDE.md) de
la raíz; el contrato de la API, en [`contrato-api.md`](../docs/01-arquitectura/contrato-api.md). El diseño de la F0 (con la sesión de la 0.2a y la 0.2b) está
en [`f0-base.md`](../docs/03-plan-desarrollo/fases/frontend/f0-base.md).

## 1. Estado actual

| Dato | Valor |
|---|---|
| Fase | F3 (agenda y turnos), EN CURSO: diseño aprobado (R-085) en [`f3-agenda.md`](../docs/03-plan-desarrollo/fases/frontend/f3-agenda.md); cerradas la 3.1a, la 3.1b, front-03, la 3.2, demo-02, la 3.3, la 3.4 y la 3.5 (R-086 a R-090 y R-092 a R-094), sin integrar a `main` (se integran al cerrar la F3). F0, F1 y F2, COMPLETADAS e integradas a `main` (R-063, R-072 y R-083) |
| Entrega | La 3.5 (los estados del turno), cerrada en `feature/front-3-5-estados` (R-094); sigue la 3.6 (las series, [diseño](../docs/03-plan-desarrollo/fases/frontend/f3-agenda.md#36-las-series)), sin código hasta la autorización del usuario |
| Tests | Vitest: 1721 tests en 82 archivos, en verde (cierre de la 3.5, R-094, en [`f3-resultados.md`](../docs/03-plan-desarrollo/fases/frontend/f3-resultados.md#entrega-35)); Playwright: 41 casos en 20 archivos; la corrida completa, al cerrar la F3 |

El punto de continuidad, en el [handoff](../docs/03-plan-desarrollo/handoff.md); el estado de cada fase, en el
[roadmap](../docs/03-plan-desarrollo/README.md#fases-del-frontend); el resultado de cada entrega, en el `fN-resultados.md` de su fase;
esta sección como estaba hasta R-090, en [`historial-handoff.md`](../docs/03-plan-desarrollo/historial-handoff.md#estado-del-frontend-frontendclaudemd-1-hasta-r-090).

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
    ├── features/<m>/    api.ts, types.ts, componentes y páginas de cada módulo (auth; clients, desde la 1.1; patients, desde la 1.3a, con la foto desde la 1.4; species, desde la 1.5; branches, desde la 2.1; users, desde la 2.2a; veterinarians, desde la 2.3; services, desde la 2.4; settings, desde la 2.5; audit, desde la 2.6; schedules, desde la 3.1a de la F3; appointments, desde la 3.2)
    └── test/            Configuración de Vitest, el servidor de MSW, respuestas del contrato y el test de voseo
```

Rutas: los archivos con `_` adelante son layouts sin URL propia: `_publico` (`/ingresar`) y `_sesion` (`/`, `/perfil`,
`/configuracion`, solo para el administrador: desde la 2.1, la página de entrada (`src/app/ConfigurationPage.tsx`, una tarjeta por
sección que el usuario puede ver) con sus secciones debajo (`configuracion/index.tsx`, `configuracion/sucursales/…` y, desde la 2.2a, `configuracion/usuarios/…`), `/clientes` y `/clientes/$id`, desde la 1.1, `/clientes/nuevo` y
`/clientes/$id/editar` (archivo `$id_.editar.tsx`: con `_`, no se dibuja dentro del detalle), desde la 1.2, y `/sin-permiso`; exige
la sesión desde la 0.2a). Una URL desconocida muestra la página de no encontrado (`notFoundComponent` de la raíz). Las de los módulos
siguen la misma regla: `/clientes`, `/clientes/$id`, `/pacientes`, `/agenda`, `/turnos`, `/configuracion`.

## 5. Convenciones

Las reglas duras, una por línea; el detalle y los ejemplos de cada una, en
[`frontend-convenciones.md`](../docs/04-desarrollo/frontend-convenciones.md). **Antes de escribir código, leer ahí la de lo que se toca.**

* **Textos en español con voseo**; el `message` de la API se muestra tal cual (`src/test/voseo.test.ts`, con las listas de `VoseoTest`).
* **Decidir por `error_code`**, nunca por el texto ni solo por el HTTP ([§6](#6-qué-hace-la-interfaz-con-cada-código-de-error), `src/lib/api/errors.ts`).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`**: ni `fetch` ni `XMLHttpRequest` fuera de él, y nunca `dangerouslySetInnerHTML`.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` del módulo; lo que depende de la sucursal, con `branchKey`.
* **Listados**: búsqueda, filtros y página en la URL (`validateSearch` devuelve todas sus claves); lo escrito, siempre texto (`TEXT_SEARCH_PARAMS`); una lista, separada por comas (`LIST_SEARCH_PARAMS`) y a la API como `clave[]`; el modelo es `/clientes`.
* **Contraseñas y archivos fuera de la caché de mutaciones**: `gcTime: 0` y `reset()` en `onSettled`.
* **Tipos escritos a mano**, con los nombres de la API en `snake_case`.
* **Formularios**: React Hook Form con Zod, las reglas de la API con sus mensajes, un solo envío (`useSingleAction`) y el 422 con `applyValidationErrors`.
* **Tabler solo a través de `src/components/ui`**; sin Tailwind ni el JS de Tabler.
* **Confirmación antes de borrar** con `ConfirmDialog`; el error de su acción, con `dialogError`.
* **Después de borrar o mover un registro hijo**, `resetQueries` de lo que lo mostraba (no invalidar).
* **Fechas siempre en `America/Asuncion`** (`src/lib/format/dates.ts`, `toApiDateTime`); guaraníes con `formatGuaranies`; un valor vacío o inválido se muestra vacío.
* **La agenda**: las fechas en `features/appointments/dates.ts`, la grilla pura en `grid.ts`, la fecha siempre en la URL y la actualización con la pausa común.
* **Los estados del turno**: las acciones salen de `features/appointments/transitions.ts` (espejo de `AppointmentTransition`, con su test contra el backend; deuda 27) y se dibujan con `AppointmentActions`; el estado se muestra siempre con `StatusLabel` (ícono y texto, nunca solo color).
* **Textos contra las reglas de largo de la API**: `trimLikeApi` y `apiLength` (`src/lib/format/text.ts`, el recorte de `TrimStrings`), no `trim()` (deuda 28).
* **Sesión**: el token solo en `sessionStorage`; todo pasa por el `SessionManager`.
* **Permisos**: `can` y `useCan`, `requirePermission` en cada ruta con permiso; la API vuelve a controlar todo; los tests, con los permisos reales de los roles.
* **Sucursal actual**: `applyBranchChange` vuelve a pedir `me` e invalida solo las consultas de la sucursal.
* **Archivos**: las descargas, con `api.getBlob` y `BlobImage`; las subidas, controladas como la API (el tipo, por el contenido).
* **Una pantalla por registro**: la ruta de un detalle dibuja su página con `key={id}`.
* **Tests**: Vitest con MSW, en UTC; los `.test.ts` en `node`; las esperas, con relojes falsos; la cifra, de la corrida registrada.

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
| Códigos de negocio (`*_IN_USE`, `*_NOT_EDITABLE`, `USER_LAST_ADMIN`, `APPOINTMENT_*`, …) | 409, 422, 403 | `business` | Alerta con el `message` en el formulario o el diálogo; cada módulo puede sumar su trato (ofrecer inactivar, el sobreturno…). No son códigos de negocio (R-085, D2 del diseño de la F3): la agenda inactiva o con el veterinario inactivo (`schedule_id`), el servicio inactivo (`service_id`) y un día que ya pasó (`starts_at`) llegan como 422 `VALIDATION_ERROR` en su campo |
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
| 27 | Estados del turno: las reglas de `transitions.ts` (turno ajeno, el día, la hora del ausente, el paciente, deshacer el ausente) copian las del backend sin un test que falle si cambian (la tabla, las rutas y los permisos sí lo tienen). Se resuelve al cerrar la F3 | Media |
| 28 | Formularios: las reglas de largo y de obligatorio recortan con `trim()`, que deja invisibles que la API recorta (U+200B, U+3164…); solo el motivo de la cancelación usa `trimLikeApi`. Se pasan al cerrar la F3, con la 27 | Baja |

El detalle, en la [deuda técnica](../docs/03-plan-desarrollo/deuda-tecnica.md). Lo que quedó de la 0.1 (los desvíos 8 y 9), en su
[resultado](../docs/03-plan-desarrollo/fases/frontend/f0-resultados.md#entrega-01).

## 8. Velocidad

Lo esencial; el detalle y el motivo de cada regla, en [`testing.md`](../docs/04-desarrollo/testing.md#velocidad-del-frontend).

* **Manda el [Presupuesto por entrega](../CLAUDE.md#presupuesto-por-entrega)** del `CLAUDE.md` de la raíz.
* **Versiones fijas en el diseño y un solo `npm ci`**; nada de `npm view` paquete por paquete.
* **`environment: node` para los tests sin DOM**; jsdom, solo los `.test.tsx` o el `.test.ts` que lo pide.
* **Durante el trabajo, `npx vitest related <archivos tocados> --run`**; la suite completa, `typecheck`, `lint` y `build`, una vez al final.
* **Playwright**: solo los casos nuevos o tocados, más `e2e/permisos.spec.ts` si cambia el menú o el permiso de una ruta; completo, al cerrar la fase.
* **Controles en Edge**: hasta 10, numerados, cada uno con el rol que usa y el dato que busca él mismo. El script recibe qué
  controles correr (`node controles.mjs 5` o `5,6`; sin argumentos, todos) e **ingresa solo con los roles de esos controles**, una
  vez por rol, con los tokens revocados en un `finally`. Si un control falla (el script, el dato o un arreglo del código), se
  corrige y se corre **solo ese control** (`node controles.mjs 5`), nunca el script entero; si a la demo le falta el dato, primero
  `php artisan demo:agenda`. Cada corrida anota en "Pendiente para el cierre" qué controles corrió y cuántos ingresos hizo (en la
  3.5, el script sin esa opción entraba siempre con los dos roles: 4 corridas, 8 ingresos, desvío 10).
* **El script de Edge reutiliza los selectores y ayudantes de Playwright que ya pasaron** (el spec en verde o `e2e/support`): no
  los reescribe, para no fallar por el script (3.6, desvíos 4 y 6).
* **Las mutaciones se revierten con el editor**, nunca con `git checkout`, `git restore` ni `git stash` (regla común del `CLAUDE.md` de la raíz).
* **El backend de Playwright corre sin Xdebug** (`XDEBUG_MODE: 'off'` en su `webServer`).
* **Prettier solo sobre los archivos cambiados.**
* **Una entrega, un tema**: el validador, las skills y la documentación general, aparte del código.
* **Un Vite levantado para una prueba se detiene por el PID de `vite.js`**, confirmado por su puerto y su línea de comando.
* En Windows, `frontend/node_modules` fuera del antivirus ([`setup.md`](../docs/04-desarrollo/setup.md#antivirus-de-windows)).
