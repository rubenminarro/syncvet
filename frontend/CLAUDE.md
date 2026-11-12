# CLAUDE.md — SyncVet (Frontend)

Reglas propias del frontend. Las comunes (proceso, voseo, Git, documentación por cambio) están en el [`CLAUDE.md`](../CLAUDE.md) de
la raíz; el contrato de la API, en [`backend/CLAUDE.md`](../backend/CLAUDE.md) §4. El diseño de la F0 (con la sesión de la 0.2) está
en [`f0-base.md`](../docs/03-plan-desarrollo/fases/frontend/f0-base.md).

## 1. Estado actual

| Dato | Valor |
|---|---|
| Fase | F0 (base), en [`f0-base.md`](../docs/03-plan-desarrollo/fases/frontend/f0-base.md) |
| Entrega | 0.1, esqueleto sin backend: EN CURSO en `feature/front-0-1-esqueleto` (WIP, sin validar todavía por el usuario) |
| Siguiente | 0.2, la sesión contra la API real, en `feature/front-0-2-sesion` |
| Tests | Vitest: 61 tests en 9 archivos, en verde |

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
| `npm run test:e2e` | Playwright, desde la 0.2 (hoy avisa y sale con error) |

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
| Tests | Vitest, Testing Library, MSW y jsdom; Playwright desde la 0.2 |
| Calidad | ESLint (`typescript-eslint` estricto con tipos, reglas de hooks) y Prettier |

## 4. Estructura

```text
frontend/
├── .nvmrc, .npmrc       Node 26; engine-strict y save-exact
├── .env.example         API_PROXY_TARGET (el backend del proxy de desarrollo)
├── vite.config.ts       React, el plugin de TanStack Router, el proxy de /api y Vitest
├── eslint.config.js     Reglas, con fetch prohibido fuera del cliente y un it() por caso en los tests
└── src/
    ├── main.tsx         Arranque: estilos de Tabler y <App>
    ├── routeTree.gen.ts Generado por el plugin del router (se versiona; no se edita a mano)
    ├── routes/          Una ruta por archivo; el nombre es la URL en castellano
    ├── app/             Proveedores (App), router, layouts, menú y páginas de error
    ├── lib/api/         client.ts, types.ts (contrato), errors.ts (ApiError y la tabla de error_code), query.ts, form-errors.ts
    ├── lib/format/      dates.ts (America/Asuncion) y money.ts (guaraníes)
    ├── components/ui/   Capa propia sobre Tabler: Button, TextField, Alert, Card, DataTable, ErrorPage, Loading, avisos
    ├── features/<m>/    api.ts, types.ts, componentes y páginas de cada módulo (en la F0, auth)
    └── test/            Configuración de Vitest, el servidor de MSW, respuestas del contrato y el test de voseo
```

Rutas: los archivos con `_` adelante son layouts sin URL propia: `_publico` (`/ingresar`) y `_sesion` (`/` y `/sin-permiso`; en la
0.2 exige la sesión). Una URL desconocida muestra la página de no encontrado (`notFoundComponent` de la raíz). Las de los módulos
siguen la misma regla: `/clientes`, `/clientes/$id`, `/pacientes`, `/agenda`, `/turnos`, `/configuracion`.

## 5. Convenciones

* **Textos en español con voseo** ("Ingresá tu email", "Elegí tu sucursal"). El `message` de la API ya viene así: se muestra tal
  cual, nunca se reescribe. `src/test/voseo.test.ts` revisa las cadenas y el texto de JSX de `src/` con las listas de `VoseoTest` del
  backend: un verbo nuevo en un texto se suma a su lista.
* **Decidir por `error_code`**, nunca por el texto ni solo por el HTTP: la [tabla](#6-qué-hace-la-interfaz-con-cada-código-de-error) vive
  en `src/lib/api/errors.ts` (`errorTreatment`, `userMessage`).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`** (`api.get`, `api.post`…, `getPage` para los listados): pone
  `Accept: application/json` y el token y convierte cualquier falla en un `ApiError` (`status`, `errorCode`, `message`, `errors`,
  `retryAfter`). ESLint prohíbe `fetch` en el resto del código.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` de cada módulo. `createQueryClient()`
  (`src/lib/api/query.ts`): una lectura se reintenta una vez solo con una falla de red o un 5xx; una mutación, nunca.
* **Tipos escritos a mano** en el `types.ts` del módulo, con los nombres de la API (`snake_case`, también `meta.pagination`:
  `per_page`, `current_page`, `last_page`); no se traducen a `camelCase`.
* **Formularios**: React Hook Form con Zod; un 422 se vuelca con `applyValidationErrors` (`src/lib/api/form-errors.ts`): cada clave
  de `errors` a su campo con `setError` y foco en el primero; las demás, a una `Alert` arriba del formulario.
* **Componentes de Tabler a través de `src/components/ui`**: las páginas no repiten el marcado de Tabler. Sin Tailwind ni otra
  biblioteca de componentes; sin el JS de Tabler (menús, diálogos y avisos son componentes de React).
* **Fechas y dinero**: la API manda las fechas con hora en UTC (`Z`); se muestran con `formatDate`, `formatDateTime` y `formatTime`
  (`src/lib/format/dates.ts`, siempre en `America/Asuncion`, nunca con la zona de la PC); se mandan con `toApiDateTime` (la hora de
  pared de Asunción con `TZDate(...).toISOString()`, con su desplazamiento `-03:00`); "hoy" es `todayInAppZone()`. Guaraníes con
  `formatGuaranies` (`Intl.NumberFormat('es-PY')`, PYG, sin decimales).
* **Permisos** (0.2): el menú y los botones según `me.permissions`; la API vuelve a controlar todo (un botón oculto no protege).
* **Descargas con token** (desde la F1): se piden con el cliente como `blob` y se muestran con una URL de objeto.
* **Tests**: Vitest con MSW (un pedido sin handler hace fallar el test) y Testing Library. Corren en UTC, para que mostrar una fecha
  con la zona de la PC falle. **Un `it()` por caso, escrito**: sin `.each` ni `it()` dentro de bucles (ESLint lo controla), porque
  `/validar-docs` cuenta los tests leyendo los `it(` y `test(`. Para casos repetidos, una función auxiliar llamada desde cada `it()`.
  En las páginas con el layout, consultar por texto con selector: `*ByRole` tarda segundos en jsdom con los íconos SVG.

## 6. Qué hace la interfaz con cada código de error

Se muestra el `message` de la API y se decide qué hacer por el código. `NETWORK_ERROR` es el único código propio del frontend.

| `error_code` | HTTP | Trato (`errorTreatment`) | Qué hace la interfaz |
|---|---|---|---|
| `UNAUTHENTICATED` | 401 | `session-expired` | Cierra la sesión en todas las pestañas y lleva a `/ingresar?volver=…` con "Tu sesión venció. Ingresá de nuevo." |
| `USER_INACTIVE` | 403 | `user-inactive` | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `CLINIC_NOT_AVAILABLE` | 403 | `clinic-unavailable` | En el login, el mensaje en el formulario; con sesión, borra la sesión y muestra el mensaje en `/ingresar` |
| `FORBIDDEN` | 403 | `forbidden` | Al cargar una página, `/sin-permiso`; en una acción, aviso con el mensaje y el formulario queda como estaba |
| `RESOURCE_NOT_FOUND` | 404 | `not-found` | Al cargar un detalle, la página de no encontrado; en una acción, aviso e invalidar la lista |
| `VALIDATION_ERROR` | 422 | `validation` | Cada clave de `errors` a su campo (`applyValidationErrors`); las demás, a una alerta arriba; foco en el primer campo |
| `INVALID_CREDENTIALS` | 422 | `invalid-credentials` | Mensaje en el formulario de ingreso; se borra la contraseña |
| `AUTH_TOO_MANY_ATTEMPTS`, `TOO_MANY_REQUESTS` | 429 | `throttled` | Mensaje y el botón deshabilitado con la cuenta regresiva de `Retry-After` (`retryAfter`) |
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

Ninguna registrada. Lo pendiente de la 0.1 está en "Pendiente para el cierre (0.1)" de
[`f0-base.md`](../docs/03-plan-desarrollo/fases/frontend/f0-base.md#pendiente-para-el-cierre-01).
