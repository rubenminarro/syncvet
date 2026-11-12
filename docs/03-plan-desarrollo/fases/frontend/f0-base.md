# F0 — Base del frontend

Etapa: Base. Estado: **DISEÑO APROBADO** por el usuario el 2026-11-12 (R-058), en `docs/frontend-analisis`, con las decisiones
del análisis y las [preguntas del diseño](#preguntas-del-diseño) respondidas. El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Dejar `frontend/` listo para construir pantallas: el proyecto con sus herramientas y convenciones (0.1, sin backend) y la sesión
contra la API real (0.2, dividida en 0.2a y 0.2b por decisión del usuario, R-060): ingresar, salir, la sucursal actual y el menú
según los permisos. Ninguna pantalla de negocio: las de
clientes y pacientes empiezan en la F1.

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 0.1 | Esqueleto: proyecto, herramientas, rutas, capa de la API con la tabla de errores, componentes base y tests; sin llamar a la API real | No | `feature/front-0-1-esqueleto`, desde `feature/front-00-me-sucursales` (R-059) | COMPLETADO (R-060, [resultado](#entrega-01)) |
| 0.2a | Sesión: ingresar, `me`, salir, 401 y 403 con sesión, vencimiento con su aviso, cierre en todas las pestañas y la sesión pedida desde una pestaña nueva | Sí: la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | `feature/front-0-2a-sesion`, desde `feature/front-0-1-esqueleto` (R-060) | PENDIENTE |
| 0.2b | Permisos: menú y rutas según los permisos, selector de sucursal, `/perfil` con la contraseña propia y Playwright contra `syncvet_e2e` | Sí, como la 0.2a | `feature/front-0-2b-permisos`, desde `feature/front-0-2a-sesion` | PENDIENTE |

El orden es F0 → F1 → F2 → F3 y después la FASE 4 del backend (R-058, decisión D2); las tres últimas fases del frontend van sobre la API ya cerrada de las fases 1 a 3 del
backend. Cada entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md) y deja los tests en verde.

### Orden con la entrega del backend

```text
main ── feature/front-00-me-sucursales (B1–B3, cerrada, R-059)
           └─ feature/front-0-1-esqueleto (0.1, cerrada, R-060)
                └─ feature/front-0-2a-sesion (0.2a)
                     └─ feature/front-0-2b-permisos (0.2b) ── merge a main al cerrar la F0
```

**Decisión del usuario (R-059).** La entrega del backend se cerró primero y no se integra a `main` todavía: la 0.1 sale de su rama y
todo se integra a `main` al cerrar la F0. Reemplaza la propuesta de R-058: dos ramas desde `main`, integradas antes de la 0.2.

La 0.1 no llama a la API real (los tests usan MSW con el contrato ya en `snake_case`, como queda después de B3), así que **puede ir
antes o en paralelo** con la entrega del backend: tocan carpetas distintas (`frontend/` y `backend/`) y no se pisan. La 0.2 necesita
las dos (en esta cadena de ramas, R-059). Propuesta: primero la entrega del backend (es corta y cierra el contrato que la 0.1 tipa), después la 0.1; si
se prefiere avanzar en el frontend, la 0.1 primero no cambia nada, porque sus tipos ya nacen con `per_page`, `current_page` y
`last_page`.

## Stack

El aprobado en el informe de análisis del 2026-11-11 (R-058, con la alternativa descartada de cada elección). Las versiones exactas se fijan en la 0.1 y quedan en el `package.json` del frontend y en
[`technology-stack.md`](../../../01-arquitectura/technology-stack.md).

| Tema | Elección |
|---|---|
| Entorno | Node 26 LTS en todas las PC, con `frontend/.nvmrc` y `engines` en `package.json`; npm 11 con `package-lock.json` |
| Base | React 19, Vite 8 con `@vitejs/plugin-react`, TypeScript 6 en modo estricto (no TS 7) |
| Interfaz | Tabler oficial: `@tabler/core` (solo sus estilos, sin el JS de Tabler) y `@tabler/icons-react`, con componentes propios en `src/components/ui`. Sin Tailwind |
| Rutas | TanStack Router, con las URL en castellano |
| Datos del servidor | TanStack Query (reintentos solo ante fallas de red o 5xx) sobre un cliente HTTP propio sobre `fetch`, sin Axios |
| Formularios | React Hook Form con Zod; el 422 se vuelca a los campos con `setError` |
| Fechas | date-fns 4 con `@date-fns/tz` (`TZDate` en `America/Asuncion`) |
| Tipos de la API | Escritos a mano en cada módulo, con el contrato común en `src/lib/api/types.ts`; sin generarlos (Scramble descartado por ahora) |
| Tests | Vitest con Testing Library, MSW y jsdom; Playwright para punta a punta contra la base `syncvet_e2e` con el `DemoSeeder` |
| Calidad | ESLint (con `typescript-eslint` y las reglas de hooks) y Prettier; `tsc` sin errores |
| Desarrollo | El proxy de Vite manda `/api` al backend: mismo origen, sin CORS en desarrollo |

## Estructura

```text
frontend/
├── .nvmrc                  26
├── .env.example            API_PROXY_TARGET (el backend del proxy de desarrollo; sin VITE_: no llega al navegador)
├── package.json            engines.node, scripts: dev, build, preview, lint, typecheck, test, test:e2e
├── vite.config.ts          React, el plugin de TanStack Router y el proxy de /api
├── playwright.config.ts    (0.2)
├── e2e/                    (0.2) pruebas de punta a punta y su preparación de la base
└── src/
    ├── main.tsx            Arranque: estilos de Tabler, QueryClient y router
    ├── routeTree.gen.ts    Generado por el plugin de TanStack Router (se versiona)
    ├── routes/             Una ruta por archivo; el nombre del archivo es la URL en castellano
    ├── app/                Proveedores, layouts (público y con sesión), menú y páginas de error
    ├── lib/
    │   ├── api/            client.ts (sobre fetch), types.ts (contrato), errors.ts (ApiError y la tabla de error_code)
    │   ├── auth/           (0.2) sesión, vencimiento, canal entre pestañas y permisos
    │   └── format/         Fechas en la zona de Asunción y guaraníes
    ├── components/ui/      Capa propia sobre Tabler: botón, campo, alerta, aviso, tabla, paginación, página de error
    ├── features/<módulo>/  api.ts, types.ts, componentes y páginas de cada módulo (desde la F1; en la F0, auth)
    └── test/               Configuración de Vitest y los handlers de MSW
```

Rutas de la F0: `/ingresar` (pública), `/` (inicio, con sesión), `/perfil` (0.2), `/sin-permiso` y la página de no encontrado. Las de los módulos
siguen la misma regla: `/clientes`, `/clientes/$id`, `/pacientes`, `/agenda`, `/turnos`, `/configuracion`.

## Convenciones

Se escriben en [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) en la 0.1; este es su contenido.

* **Textos en español con voseo**, como la API ("Ingresá tu email", "Elegí tu sucursal"). El `message` de la API ya viene en
  español y con voseo: se muestra tal cual, nunca se reescribe.
* **Decidir por `error_code`, nunca por el texto ni solo por el código HTTP** (ver la [tabla](#qué-hace-la-interfaz-con-cada-código-de-error)).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`**: pone el token, el `Accept: application/json` y convierte cualquier
  error en un `ApiError` (`status`, `errorCode`, `message`, `errors`, `retryAfter`). Ningún componente llama a `fetch` directo.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` de cada módulo. Una mutación no se reintenta
  nunca; una lectura se reintenta una vez solo si falló la red o hubo 5xx, nunca con un 4xx.
* **Tipos escritos a mano** en el `types.ts` del módulo, con los nombres de campo de la API (`snake_case`): no se traducen a
  `camelCase` en el cliente.
* **Componentes de Tabler a través de `src/components/ui`**: las páginas no repiten el marcado de Tabler. Sin Tailwind ni otra
  biblioteca de componentes; sin el JS de Tabler (los menús, diálogos y avisos son componentes propios de React).
* **Fechas y dinero**: la API manda fechas con hora en UTC (`Z`); se muestran en `America/Asuncion` con date-fns y `TZDate`, nunca con
  la zona del navegador. Guaraníes sin decimales, con separador de miles.
* **Permisos**: el menú y los botones se muestran según `me.permissions`; la API vuelve a controlar todo (un botón oculto no es una
  protección).
* **Descargas con token** (foto, logo; desde la F1): se piden con el cliente como `blob` y se muestran con una URL de objeto, porque un
  `<img src>` no manda el token.

## Sesión (0.2)

| Tema | Diseño |
|---|---|
| Dónde vive el token | `sessionStorage` (`syncvet.session`: `token` y `expires_at`); el perfil, en la caché de TanStack Query (`['me']`). Nada en `localStorage` ni en cookies (las cookies de Sanctum quedan para la FASE 10, R-058) |
| Ingresar | `POST /api/login`; con el 200 se guarda la sesión, se pone `data.user` en `['me']` y se vuelve a la ruta pedida (`/ingresar?volver=…`, solo rutas internas) o a `/` |
| Al abrir la aplicación | Si hay una sesión guardada sin vencer, `GET /api/me` la valida; si venció, se borra sin llamar a la API |
| Aviso previo | 10 minutos antes de `expires_at`, un aviso que queda visible: "Tu sesión vence en 10 minutos. Guardá lo que estés haciendo." (P2) |
| Vencimiento | Un temporizador hasta `expires_at` (los tokens duran 12 horas y la API no los renueva) cierra la sesión en todas las pestañas con "Tu sesión venció. Ingresá de nuevo."; además se revisa al volver a la pestaña (`visibilitychange`), porque los temporizadores se frenan con la PC suspendida |
| Pestaña nueva | Sin sesión propia, publica `session-request` en `BroadcastChannel('syncvet-sesion')` y espera la respuesta un instante (alrededor de 300 ms); una pestaña con sesión responde `session-share` con el token y `expires_at`, y la nueva lo guarda en su `sessionStorage`. Sin respuesta, va a `/ingresar` (P1). Todas las pestañas comparten así un mismo token |
| Salir | `POST /api/logout` (si falla, igual se borra la sesión local), se limpia la caché de Query y se publica `logout` |
| Todas las pestañas | Al recibir `logout`, cada pestaña borra su sesión y su caché y va a `/ingresar`. El mensaje lleva el id del token (la parte anterior a `\|` del token de Sanctum, que no es secreta): si una pestaña tiene otro token (dos ingresos simultáneos), hace también su propio `POST /api/logout`. Al cambiar de sucursal, `branch-changed`: las demás pestañas vuelven a pedir `me` e invalidan sus consultas |
| 401 | En cualquier pedido con sesión: como el token es compartido, se cierra la sesión en todas las pestañas (`logout` en el canal, sin `POST /api/logout`) y se va a `/ingresar?volver=…` con el aviso de sesión vencida (P1) |
| Perfil | `/perfil`: los datos del usuario y el cambio de la contraseña propia (`PATCH /api/me/password`, con la actual y la nueva; 422 a los campos, 429 con la cuenta regresiva). La API conserva el token en uso y revoca los demás del usuario (P3) |
| Sucursal actual | Selector en la barra superior con `me.branches` (B1); `PATCH /api/me/current-branch`, actualiza `['me']` e invalida el resto de las consultas (los datos dependen de la sucursal). Sin sucursal actual, el inicio pide elegir una |
| Rutas protegidas | El layout con sesión exige una sesión válida (`beforeLoad`); cada ruta declara el permiso que necesita y, sin él, muestra `/sin-permiso` |
| Menú | Solo los módulos ya hechos y permitidos por `me.permissions`; en la F0, Inicio |

## Qué hace la interfaz con cada código de error

La regla general: **se muestra el `message` de la API** y se decide qué hacer por el `error_code`. `NETWORK_ERROR` es el único código
propio del frontend (sin respuesta del servidor). La tabla vive en `src/lib/api/errors.ts` y la usan el cliente y los formularios;
cada módulo puede sumar el trato especial de sus códigos (columna "Más adelante").

| `error_code` | HTTP | Qué hace la interfaz |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Cierra la sesión en todas las pestañas y lleva a `/ingresar?volver=…` con "Tu sesión venció. Ingresá de nuevo." |
| `USER_INACTIVE` | 403 | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `CLINIC_NOT_AVAILABLE` | 403 | En el login, el mensaje en el formulario; con sesión, borra la sesión y muestra el mensaje en `/ingresar` |
| `FORBIDDEN` | 403 | Al cargar una página: `/sin-permiso`. En una acción: aviso con el mensaje; el formulario queda como estaba |
| `RESOURCE_NOT_FOUND` | 404 | Al cargar una página de detalle: la página de no encontrado. En una acción: aviso con el mensaje e invalidar la lista |
| `VALIDATION_ERROR` | 422 | Cada clave de `errors` va a su campo (React Hook Form `setError`); las que no son de un campo del formulario, a una alerta arriba. Foco en el primer campo con error |
| `INVALID_CREDENTIALS` | 422 | Mensaje en el formulario de ingreso; se borra la contraseña |
| `AUTH_TOO_MANY_ATTEMPTS`, `TOO_MANY_REQUESTS` | 429 | Mensaje en el formulario y el botón deshabilitado con la cuenta regresiva de `Retry-After` |
| `CONFLICT` | 409 | Aviso "Alguien cambió estos datos mientras guardabas. Revisalos y probá de nuevo." con el `message`; se vuelven a pedir los datos |
| `PAYLOAD_TOO_LARGE` | 413 | El error en el campo del archivo (`errors.photo`, `errors.logo`); sin `errors`, aviso |
| Códigos de negocio (`*_IN_USE`, `*_NOT_EDITABLE`, `USER_LAST_ADMIN`, `APPOINTMENT_*`, …) | 409, 422, 403 | Por defecto, alerta con el `message` en el formulario o el diálogo de la acción. Más adelante: `*_IN_USE` ofrece inactivar; `APPOINTMENT_OVERLAP` ofrece el sobreturno si el usuario tiene `appointments.overbook`; `APPOINTMENT_SERIES_CONFLICT` lista `errors.conflicts`; `APPOINTMENT_QUEUE_NO_BRANCH` abre el selector de sucursal |
| `INTERNAL_SERVER_ERROR`, `SERVICE_UNAVAILABLE` | 500, 503 | Aviso "No se pudo completar. Probá de nuevo en unos minutos."; una lectura se reintenta una vez |
| `NETWORK_ERROR` (propio) | — | Aviso "No hay conexión con el servidor."; una lectura se reintenta una vez |
| `BAD_REQUEST`, `METHOD_NOT_ALLOWED`, `NOT_ACCEPTABLE`, `UNSUPPORTED_MEDIA_TYPE`, `HTTP_ERROR` | 400, 405, 406, 415, otros | Son errores de programación: aviso genérico y `console.error` con el detalle en desarrollo |
| Un código que la tabla no conoce | cualquiera | Como un código de negocio: alerta con el `message` |

## Diseño por entrega

### 0.1 Esqueleto

**Alcance.**

1. Proyecto Vite 8 con `@vitejs/plugin-react`, React 19 y TypeScript 6 estricto en `frontend/`; `.nvmrc` con `26` y `engines`
   (`node` `>=26 <27`, `npm` `>=11`).
2. Tabler (los estilos de `@tabler/core`, sin su JS, y `@tabler/icons-react`) y la capa `src/components/ui` con lo que usan la F0 y la F1: botón, campo de texto
   con su error, alerta, avisos (*toasts*), tarjeta, tabla con paginación (`meta.pagination` en `snake_case`), página de error y
   cargando.
3. TanStack Router (plugin de Vite, rutas por archivo): layout público y layout con sesión (sin la sesión real todavía), `/ingresar`
   como maqueta, `/`, `/sin-permiso` y no encontrado.
4. `src/lib/api`: el cliente propio sobre `fetch` (base `/api`, `Accept: application/json`, el token si hay sesión), los tipos del contrato
   (`ApiSuccess<T>`, `ApiFailure`, `Pagination`) y `ApiError` con la tabla de `error_code`. TanStack Query con los reintentos de las
   [convenciones](#convenciones).
5. `src/lib/format`: fechas en `America/Asuncion` con date-fns 4 y `@date-fns/tz` (`TZDate`) y guaraníes.
6. El proxy de Vite (`/api` → `API_PROXY_TARGET`, por defecto `http://syncvet.test`).
7. Vitest con Testing Library, MSW y jsdom; ESLint y Prettier; scripts `dev`, `build`, `preview`, `lint`, `format`, `typecheck`,
   `test`.
8. Documentación: `frontend/CLAUDE.md` (estado, comandos, estructura y convenciones) y `frontend/README.md` (instalación con Node 26);
   corregir "repositorio aparte" en [`overview.md`](../../../00-vision/overview.md) y
   [`technology-stack.md`](../../../01-arquitectura/technology-stack.md) (R-057 lo dejó pendiente para la F0); el stack con sus
   versiones en `technology-stack.md`; la instalación del frontend en [`setup.md`](../../../04-desarrollo/setup.md).

**Fuera de alcance.** Llamadas a la API real, la sesión, Playwright.

**Tests.**

| Qué | Cómo |
|---|---|
| El cliente convierte cada respuesta de error del contrato en `ApiError` con `status`, `errorCode`, `message`, `errors` y `retryAfter` | Vitest con MSW: 401, 403, 404, 409, 413, 422 con `errors`, 429 con `Retry-After`, 500, y sin respuesta (`NETWORK_ERROR`) |
| La tabla de `error_code` | Un caso por fila; un código desconocido cae en el trato de negocio |
| Reintentos | Una lectura con 500 o sin red se reintenta una vez; con 4xx, ninguna; una mutación, nunca |
| Errores de validación a los campos | Un formulario de prueba con un 422: cada clave a su campo y la que no es de un campo, a la alerta |
| Formato | Una fecha UTC de las 02:00 se muestra el día anterior a las 23:00 en Asunción; guaraníes con miles y sin decimales |
| Rutas | Una URL desconocida muestra no encontrado; `/sin-permiso` se ve |
| Paginación | La tabla lee `per_page`, `current_page` y `last_page` |

**Mutaciones (las protecciones críticas).** Sacar la conversión a `ApiError` en el cliente; reintentar los 4xx; ignorar el campo en
`setError`; formatear con la zona del navegador; leer `perPage` en lugar de `per_page`. Cada una tiene que hacer fallar un test.

**Qué revisar en el navegador.** `npm run dev` desde `frontend/`: la maqueta de `/ingresar` con Tabler, una URL inexistente muestra no
encontrado, `/sin-permiso` se ve, el ancho de tablet (768 px) no rompe el layout y la consola no tiene errores ni avisos de React.
`npm run build` sin errores y `npm run preview` sirve lo mismo.

#### Pendiente para el cierre (0.1)

Vacío: la entrega se cerró (R-060) y todo lo que había acá pasó a su [resultado](#entrega-01).

### 0.2a Sesión

**Requisito.** La entrega del backend (B1 a B3) cerrada; en esta cadena de ramas, sin integrar a `main` hasta cerrar la F0 (R-059).
**Rama.** `feature/front-0-2a-sesion`, desde `feature/front-0-1-esqueleto` (R-060).

**Alcance.**

1. `src/lib/auth`: la sesión en `sessionStorage`, el aviso previo y el temporizador de vencimiento, y el canal `syncvet-sesion`
   (`logout` y la sesión compartida a las pestañas nuevas con `session-request` y `session-share`), según la [sesión](#sesión-02).
2. `/ingresar` real (email y contraseña, con los errores de la tabla), con un botón para mostrar la contraseña (sugerencia del
   usuario, R-060); salir desde el menú del usuario.
3. Con sesión, según la [tabla de errores](#qué-hace-la-interfaz-con-cada-código-de-error): el 401 cierra todas las pestañas y lleva a
   `/ingresar?volver=…`; el 403 `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE`, como dice la tabla.
4. El layout con sesión exige una sesión válida (`beforeLoad`): barra con la clínica, el usuario y sus roles; `me` en la caché de
   TanStack Query (`['me']`).
5. `@tabler/core` 1.7.5 (desvío 2 de la 0.1), con la versión fijada en el diseño de la entrega (`frontend/CLAUDE.md` §8).
6. Documentación: `frontend/CLAUDE.md` (la sesión).

**Pregunta para el diseño de la 0.2a (sugerencia del usuario, R-060).** ¿"Correo electrónico" en lugar de "Email" en `/ingresar` (y en el
resto de la interfaz)? La API dice "email" en sus mensajes y campos; el cambio sería solo de los textos del frontend. **Respuesta del
usuario (2026-11-12): queda "Email"**, coherente con los mensajes de la API.

**Diseño detallado (2026-11-12, escrito con la implementación que pidió el usuario; sin revisión previa).**

*Versiones.* Solo cambia `@tabler/core`: `1.7.4` → `1.7.5` (`npm install @tabler/core@1.7.5`). Ningún paquete nuevo: el canal entre
pestañas es el `BroadcastChannel` del navegador.

*Archivos.*

| Archivo | Contenido |
|---|---|
| `src/lib/auth/storage.ts` | `syncvet.session` en `sessionStorage` (`token`, `expires_at`): leer (un JSON roto, sin `token` o con `expires_at` inválido se borra y cuenta como sin sesión), guardar, borrar; `tokenId(token)`, la parte anterior a `\|` |
| `src/lib/auth/channel.ts` | `SessionChannel` (`post`, `subscribe`, `close`) sobre `BroadcastChannel('syncvet-sesion')`; sin `BroadcastChannel`, uno que no hace nada. Mensajes: `session-request`, `session-share` (con `token` y `expires_at`) y `logout` (con `token_id` y el `notice` para `/ingresar`, o `null` si fue un "Salir") |
| `src/lib/auth/session.ts` | `SessionManager`: la sesión en memoria y en el almacenamiento, el aviso previo y el vencimiento (temporizadores y `visibilitychange`), el canal, el token para el cliente y lo que hace con un error de sesión. Su estado (sesión, aviso previo, `notice`) se lee con `useSyncExternalStore` |
| `src/lib/auth/redirect.ts` | `safeRedirect(volver)`: solo una ruta interna (empieza con una sola `/`, mismo origen, no `/ingresar`); si no, `/` |
| `src/lib/auth/use-session.ts` | El contexto de React con el `SessionManager` y `useSession()` |
| `src/lib/api/client.ts` | `RequestOptions.token`: sin indicar, el de la sesión; `null`, sin token (el login); un texto, ese token (el `logout` de otra pestaña). `setSessionErrorHandler`: recibe un `ApiError` con trato `session-expired`, `user-inactive` o `clinic-unavailable` **solo si el pedido llevó el token de la sesión** (el login no lo lleva: sus errores van al formulario) |
| `src/features/auth/types.ts`, `api.ts` | `Me` (el `MeResource`), `LoginResponse`; `meQueryOptions()` (`['me']`, `staleTime: Infinity`), `login()` y `logout(token)` |
| `src/features/auth/LoginPage.tsx` | El formulario real, el `notice` de la sesión arriba, los errores por trato y el botón de la contraseña |
| `src/components/ui/TextField.tsx`, `PasswordField.tsx` | `TextField` suma `addon` (un `input-group-flat` de Tabler); `PasswordField`, el campo con el botón para mostrar u ocultar |
| `src/app/SessionLayout.tsx` | Barra con el usuario, la clínica, la sucursal actual (solo se muestra; el selector es de la 0.2b) y "Salir"; el aviso previo arriba del contenido; sin sesión, va a `/ingresar` |
| `src/routes/_sesion.tsx`, `_publico/ingresar.tsx` | `beforeLoad` del layout con sesión (sesión propia o pedida a otra pestaña, después `me`); `/ingresar` valida `volver` y, con una sesión guardada, va a `/` |
| `src/app/App.tsx`, `router.ts`, `main.tsx` | El `SessionManager` y el `QueryClient` se crean en `main.tsx` y llegan al contexto del router (`queryClient`, `session`) |

*Flujos.*

1. **Ingresar.** `POST login` sin token. Con el 200: `session.start({ token, expires_at })` (guarda, programa los temporizadores,
   borra el `notice`), `['me']` con `data.user` y `navigate({ href: safeRedirect(volver) })`. Errores por trato:
   `validation` a los campos (`applyValidationErrors`, el resto a la alerta); `invalid-credentials`, alerta y se borra la
   contraseña; `throttled`, alerta y el botón deshabilitado con "Esperá N segundos" hasta que pasen los segundos de `retryAfter`;
   `user-inactive`, `clinic-unavailable` y el resto, alerta con `userMessage(error)`.
2. **Al abrir o recargar.** El `SessionManager` lee el almacenamiento al crearse: una sesión vencida se borra sin llamar a la API y
   deja el `notice` "Tu sesión venció. Ingresá de nuevo.". El `beforeLoad` de `_sesion`: sin sesión propia, `session-request` y
   espera 300 ms; sin respuesta, `redirect` a `/ingresar?volver=…` (sin `volver` si la ruta era `/`). Con sesión,
   `ensureQueryData(meQueryOptions())`; un error de sesión hace `redirect` a `/ingresar` (el `SessionManager` ya la cerró). Mientras
   tanto, el `pendingComponent` del layout ("Cargando tu sesión…", `pendingMs: 0`): nunca se ve el formulario de ingreso.
3. **Salir.** `POST logout` con el token (si falla, igual sigue), después `end(notice = null)`: borra el almacenamiento, publica
   `logout`, limpia la caché de Query; el layout va a `/ingresar` sin `volver`.
4. **Error de sesión en un pedido con token.** `end(notice)` con `notice` = "Tu sesión venció. Ingresá de nuevo." (401) o el `message`
   de la API (`USER_INACTIVE`, `CLINIC_NOT_AVAILABLE`); se publica `logout` con el mismo `notice`; el layout va a
   `/ingresar?volver=…` y la página muestra el `notice` como alerta de aviso.
5. **Vencimiento.** Un temporizador a `expires_at - 10 min` enciende el aviso previo (si ya pasó, al instante) y otro a `expires_at`
   cierra como un 401. Al volver a la pestaña (`visibilitychange`), se relee el almacenamiento: si `expires_at` cambió se
   reprograma (así se prueba a mano desde DevTools), y si venció o no está, se cierra.
6. **Pestañas.** `session-request`: responde `session-share` la que tiene una sesión sin vencer. `logout`: la que tiene sesión la
   borra sin publicar de nuevo; si su token es otro (`token_id` distinto), primero hace su propio `POST logout` con su token. Una
   pestaña que estaba en `/ingresar` solo borra su almacenamiento.

*Tests.* `storage.test.ts`, `redirect.test.ts` (node); `session.test.ts` (jsdom, por el cliente con MSW: dos `SessionManager` con
almacenamientos propios y un canal en memoria, como dos pestañas; temporizadores falsos); `LoginPage.test.tsx` y
`SessionLayout.test.tsx` (la aplicación entera con MSW). Además de la tabla de abajo: nada en `localStorage` ni el token en la URL.

**Tests.**

| Qué | Cómo |
|---|---|
| Sesión guardada | Se guarda y se lee; una sesión vencida al abrir se borra sin llamar a la API |
| Vencimiento | Con temporizadores falsos: 10 minutos antes, el aviso; al llegar a `expires_at` se cierra la sesión en todas las pestañas; al volver a la pestaña con la hora pasada, también |
| Entre pestañas | Una pestaña nueva recibe la sesión de una abierta (`session-request` y `session-share`) y, sin respuesta, va a `/ingresar`; al salir se publica `logout` y las demás borran su sesión sin otro `POST logout`, salvo la que tiene otro token |
| 401 y 403 | Un 401 en cualquier pedido cierra la sesión en todas las pestañas y lleva a `/ingresar?volver=…`; `volver` con una URL externa se ignora; `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE` con sesión, como la tabla |
| Ingresar | 422 `INVALID_CREDENTIALS`, 422 `VALIDATION_ERROR` en los campos, 429 con cuenta regresiva, 403 `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE`: cada uno con su mensaje; el botón muestra y oculta la contraseña |

**Mutaciones.** No cerrar todas las pestañas con un 401; no responder `session-share`; no publicar `logout` en el canal; no
programar el temporizador; aceptar un `volver` externo.

**Qué revisar en el navegador.** Con el backend en `syncvet.test` y `npm run dev`: ingresar con `admin-a` del `DemoSeeder`;
credenciales incorrectas cinco veces y la cuenta regresiva; mostrar y ocultar la contraseña; abrir una pestaña nueva y ver que entra
sin ingresar; salir en una pestaña y ver que la otra vuelve a `/ingresar`; borrar `expires_at` a mano en el almacenamiento de la
sesión (DevTools) y ver el cierre; adelantar `expires_at` a menos de 10 minutos y ver el aviso previo; inactivar al usuario desde otra
sesión de administrador y ver que el siguiente pedido lleva a `/ingresar`; la consola sin errores.

#### Pendiente para el cierre (0.2a)

Implementación del 2026-11-12 en `feature/front-0-2a-sesion` (commit WIP). Falta: la validación del usuario en el navegador, la
revisión y el cierre (con `frontend/CLAUDE.md`, el roadmap y el handoff, que esta entrega no tocó a propósito).

**Desvíos y decisiones de la implementación.**

| # | Desvío o decisión | Motivo | Estado |
|---|---|---|---|
| 1 | El aviso previo dice "Tu sesión vence en N minutos. Guardá los cambios pendientes.", con los minutos que quedan, y no el texto fijo del diseño ("…en 10 minutos. Guardá lo que estés haciendo.") | "estés" está en la lista de formas de tú del test de voseo (`TU_OR_USTED_WORDS`). Y una sesión abierta con menos de 10 minutos, o una PC que vuelve de la suspensión, no tiene 10 minutos: el aviso dice los que quedan ("1 minuto" en singular) | Sin decisión del usuario |
| 2 | Un `CLINIC_NOT_AVAILABLE` con sesión también cierra todas las pestañas (publica `logout`), como el 401 y `USER_INACTIVE` | La tabla solo dice "borra la sesión"; con el token compartido, las demás pestañas fallarían igual en su siguiente pedido | Sin decisión del usuario |
| 3 | El `POST logout` está en el `SessionManager` (`src/lib/auth/session.ts`), no como `logout(token)` en `features/auth/api.ts`; el hook es `useSessionManager()` y `useSessionState()`, no `useSession()` | `src/lib` no importa de `features/`: el `SessionManager` lo usa para salir y para el logout de una pestaña con otro token | Informativo |
| 4 | El `beforeLoad` de `_sesion` usa `queryClient.query(meQueryOptions())`, no `ensureQueryData`, y manda a `/ingresar` si la sesión quedó cerrada, sin mirar el error | `ensureQueryData` está deprecado en TanStack Query 5.107 (`no-deprecated` de ESLint). El cierre limpia la caché y cancela el `me` en curso: el error que llega es la cancelación, no el `ApiError` (lo encontró un test) | Informativo |
| 5 | ESLint: `only-throw-error` permite el `Redirect` de `@tanstack/router-core` | TanStack Router corta una navegación con `throw redirect(...)`, que no es un `Error`; sin esto haría falta un comentario por cada `redirect` | Informativo |
| 6 | Fuera del alcance escrito: `/ingresar` con una sesión abierta va a `/`; una falla de red o un 5xx al cargar `me` muestra "No se pudo cargar tu sesión" con "Reintentar" y no cierra la sesión | Sin lo primero, el botón "Atrás" después de ingresar mostraba el formulario con la sesión abierta; sin lo segundo, se veía el error genérico del router | Sin decisión del usuario |
| 7 | `App` recibe `queryClient` y `session` (obligatorios); `main.tsx` los crea una vez por pestaña | La sesión escucha el canal y los temporizadores fuera de React; cada test crea los suyos y los descarta (`src/test/session.tsx`) | Informativo |
| 8 | `frontend/.gitignore` suma `.tanstack` | El plugin del router dejó una carpeta temporal (`.tanstack/tmp`) al generar las rutas | Informativo |
| 9 | La suite tardó 34,9 s (155 tests en 14 archivos), contra 9,55 s de la 0.1 con 93 | Los tests nuevos de componentes montan la aplicación entera (jsdom) y el del 429 espera 1 s real la cuenta regresiva. No se midió aparte qué parte es de la PC (la primera corrida en frío de la 0.1 tardó 32,9 s) | A revisar en el cierre |
| 10 | `typecheck` y `lint` corrieron dos veces al final, no una | El primer `lint` encontró los `throw redirect(...)` y `ensureQueryData` (desvíos 4 y 5); después del arreglo se repitieron, junto con la suite y el build | Informativo |
| 11 | El desvío 8 de la 0.1 (`toApiDateTime` con `-03:00`) sigue PENDIENTE DE VERIFICACIÓN | La 0.2a no manda fechas a la API | Pasa a la F3 |

**Mutaciones**, cada una de a una con `vitest related` sobre `src/lib/auth`, `SessionLayout.test.tsx` y `LoginPage.test.tsx` (62
tests): no publicar `logout` con un 401 (falla 1 test); no responder `session-share` (2); no publicar `logout` al cerrar (4); sin el
temporizador de vencimiento (1); aceptar un `volver` externo (9). Todas detectadas.

**Corrida final, desde `frontend/` (2026-11-12).** `npm run typecheck` y `npm run lint` sin errores ni avisos; `npm run build` sin
avisos; `npm test`, 155 tests en 14 archivos, en verde (34,9 s). Después, solo el texto en singular de la cuenta regresiva
("Esperá 1 segundo"), con `vitest related`, `tsc` y `eslint` de esos archivos.

### 0.2b Permisos, sucursal, perfil y Playwright

**Requisito.** La 0.2a cerrada. **Rama.** `feature/front-0-2b-permisos`, desde `feature/front-0-2a-sesion` (R-060).

**Alcance.**

1. `can(permiso)` en `src/lib/auth`; el menú según `me.permissions` (en la F0, Inicio); cada ruta protegida declara su permiso y,
   sin él, muestra `/sin-permiso` (con la decisión pendiente del desvío 9 de la 0.1: `/sin-permiso` dentro del layout con sesión).
2. Selector de la sucursal actual en la barra con `me.branches` (B1): `PATCH me/current-branch`, actualiza `['me']`, invalida el
   resto de las consultas y publica `branch-changed` en el canal; el inicio con la sucursal actual o el pedido de elegir una.
3. `/perfil`: los datos del usuario y el cambio de la contraseña propia, con el botón para mostrar la contraseña (sugerencia del
   usuario, R-060).
4. Playwright: `e2e/` y `playwright.config.ts`; antes de correr, la base `syncvet_e2e` se recrea con `migrate:fresh` y el
   `DemoSeeder`, con el backend levantado desde `backend/` en un puerto propio y las variables de la base pasadas por el entorno (el
   `DemoSeeder` corre solo en `local` y `testing`). **La preparación se niega a correr si la base no termina en `_e2e`.**
5. Documentación: `frontend/CLAUDE.md` (permisos, comandos de Playwright), la base `syncvet_e2e` en `setup.md` y en
   [`testing.md`](../../../04-desarrollo/testing.md).

**Tests.**

| Qué | Cómo |
|---|---|
| Permisos | Una ruta sin el permiso muestra `/sin-permiso`; el menú oculta lo no permitido |
| Sucursal | El selector lista `me.branches`; el cambio llama a `PATCH me/current-branch`, actualiza `me` e invalida las consultas; `branch-changed` hace que las demás pestañas vuelvan a pedir `me` |
| Perfil | El cambio de contraseña llama a `PATCH me/password`; 422 en `current_password` y en `password` van a sus campos; 429 con la cuenta regresiva; con el 200, la sesión sigue; el botón muestra y oculta la contraseña |
| Punta a punta (Playwright) | Ingresar con un usuario del `DemoSeeder` y ver el inicio; credenciales incorrectas; una ruta protegida sin sesión lleva a `/ingresar` y, después de ingresar, vuelve; cambiar de sucursal; una pestaña nueva toma la sesión de la abierta; salir en una pestaña cierra la otra; cambiar la contraseña propia |

**Mutaciones.** El menú sin filtrar por permisos; una ruta sin el control de su permiso; no invalidar las consultas al cambiar de
sucursal; la preparación de Playwright sin el control del nombre de la base.

**Qué revisar en el navegador.** Con el backend en `syncvet.test` y `npm run dev`: ingresar con `admin-a`, `recepcion-a` y
`vet-a` del `DemoSeeder` y ver el menú de cada uno; cambiar de sucursal y ver el cambio en otra pestaña; cambiar la contraseña en
`/perfil` y mostrarla con el botón; el ancho de tablet (768 px); la consola sin errores.

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| 0.1 | `frontend/CLAUDE.md`, `frontend/README.md`, `technology-stack.md`, `overview.md`, `setup.md`, este documento (Resultado), el roadmap, `modulos.md` si corresponde y el handoff |
| 0.2a | `frontend/CLAUDE.md` (la sesión), este documento, el roadmap y el handoff |
| 0.2b | `frontend/CLAUDE.md`, `setup.md` y `testing.md` (`syncvet_e2e` y Playwright), este documento, el roadmap y el handoff |

## Preguntas del diseño

Respondidas por el usuario el 2026-11-12 (R-058).

| # | Pregunta | Respuesta |
|---|---|---|
| 1 | Con `sessionStorage`, una pestaña nueva (no duplicada) no tiene sesión: ¿pide ingresar o le pide la sesión a una abierta? | Se la pide a una pestaña abierta por el `BroadcastChannel`; con el token compartido, un 401 cierra todas las pestañas |
| 2 | ¿Un aviso antes de que venza la sesión? | Sí, 10 minutos antes |
| 3 | ¿La contraseña propia va en la 0.2 o en la F2? | En la 0.2 (la 0.2b desde R-060), en `/perfil` |
| 4 | ¿"Correo electrónico" en lugar de "Email"? (sugerencia del usuario al validar la 0.1, R-060) | Queda "Email", coherente con los mensajes de la API (usuario, 2026-11-12, en el diseño de la 0.2a) |

## Criterio de cierre

* La 0.1, la 0.2a y la 0.2b cerradas, con los tests de Vitest y de Playwright en verde, `lint`, `typecheck` y `build` sin errores.
* Con la base `syncvet_e2e`: ingresar, cambiar de sucursal, salir en todas las pestañas y el vencimiento, probados de punta a punta.
* Las colecciones de Postman de la entrega del backend, en verde (R-057: todas, al cerrar la fase).

## Resultado

### Entrega 0.1

**COMPLETADA el 2026-11-12 (R-060)**, en `feature/front-0-1-esqueleto`: la implementación en `72e75bc` (WIP), las correcciones de
la revisión en `b5d3ecf` (WIP) y el commit de cierre (solo documentación). Sin merge a `main`: todo se integra al cerrar la F0
(R-059).

**Qué se hizo.** `frontend/` con el stack de R-058 (Node 26, Vite 8, React 19, TypeScript 6 estricto, TanStack Router y Query,
React Hook Form con Zod, date-fns 4 con `@date-fns/tz`, Tabler sin su JS, Vitest con MSW, ESLint y Prettier): el cliente sobre
`fetch` con `ApiError` y la tabla de `error_code`, las fechas en `America/Asuncion` (con las fechas sin hora aparte), los guaraníes,
la capa de componentes sobre Tabler, `/ingresar` como maqueta, el layout, `/sin-permiso` y la página de no encontrado, el test de
voseo con las listas del backend y las cifras del frontend en `/validar-docs`. Las reglas quedaron en
[`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md).

**Validación del usuario (2026-11-12, en el navegador con `npm run dev`).** `/ingresar` se ve bien con Tabler (logo, título
"Ingresá a tu cuenta", campos obligatorios, botón con ícono) y una URL inventada muestra la página de no encontrado en voseo con
"Volver al inicio". El resto de "Qué revisar en el navegador" (`/sin-permiso`, el ancho de tablet, la consola sin avisos y
`npm run preview`) no figura en la validación: PENDIENTE DE VERIFICACIÓN (el ancho de tablet y la consola vuelven a revisarse en la
0.2b).

**Sugerencias del usuario para la 0.2.** Un botón para mostrar la contraseña en `/ingresar` (0.2a) y en `/perfil` (0.2b); evaluar
"Correo electrónico" en lugar de "Email" (pregunta 4, en el diseño de la 0.2a).

**Cifras.** La última corrida, después de las correcciones de la revisión y sobre el mismo código del cierre (desde `b5d3ecf` solo
cambió la documentación), desde `frontend/`: `npm run typecheck`, `npm run lint` y `npm run build` sin errores ni avisos, y:

Corrida de `npm test` del frontend (2026-11-12): 93 tests en 9 archivos, en verde.

`/validar-docs`: sin hallazgos; sus propios tests, 55 de 55. El backend no cambió en esta entrega (ningún archivo de `backend/`): su
suite no se volvió a correr.

**Tiempo de la implementación.** Medido en el registro de la sesión de Claude Code: 66 minutos desde el inicio (con el renombrado
de la rama del paso 0), unos 124 mil tokens de salida y 19 millones leídos del contexto en caché.

| Rubro | Tiempo aproximado | Detalle |
|---|---|---|
| npm (red e instalación) | ~22 min | Una instalación de las dependencias de la aplicación, 13,9 min; la de desarrollo, 4,7 min; un intento con 404 (`@tabler/core` 1.7.5), 2,6 min; consultar las versiones paquete por paquete, 2,8 min |
| Escribir los archivos | ~19 min | 53 archivos nuevos y 37 ediciones |
| Verificaciones repetidas | ~22 min | `prettier --write .`, `tsc`, `lint` y la suite completa, muchas veces; preparar jsdom era el 63 % de cada corrida de la suite |
| El validador y las skills | ~5 min | Otro tema, en la misma entrega |

De ahí salen las reglas de la sección [Velocidad](../../../../frontend/CLAUDE.md#8-velocidad) de `frontend/CLAUDE.md` (versiones
fijas en el diseño y un solo `npm ci`; `environment: node` para los tests sin DOM; `vitest related` durante el trabajo y la suite,
`typecheck` y `lint` una vez al final; Prettier solo sobre los archivos cambiados; validador, skills y documentación general en
entregas o cierres aparte) y la exclusión de `frontend/node_modules` y `backend/vendor` del antivirus en
[`setup.md`](../../../04-desarrollo/setup.md#antivirus-de-windows). La división de la suite en `node` y jsdom ahorró poco (9,97 s
contra 9,55 s con las mismas 93 pruebas): casi todo el tiempo de jsdom es de los tests de componentes.

**Desvíos y decisiones de la implementación.** Las versiones instaladas están en
[`technology-stack.md`](../../../01-arquitectura/technology-stack.md#frontend).

| # | Desvío o decisión | Motivo | Estado al cerrar |
|---|---|---|---|
| 1 | La variable del proxy es `API_PROXY_TARGET`, no `VITE_API_PROXY_TARGET` | Pedido de la implementación. Sin el prefijo `VITE_`, Vite no la expone al código del navegador: solo la lee `vite.config.ts` (la revisión comprobó que no llega a `dist/`) | Aceptado (usuario) |
| 2 | `@tabler/core` 1.7.4, no la 1.7.5 (la última) | La 1.7.5 se publicó el mismo día y npm respondía 404 al bajar su paquete; la 1.7.4 es del 2026-11-08. El resto, la última versión de cada paquete dentro de lo pedido (TypeScript 6.0.3, porque la última es la 7) | Aceptado (usuario). Pendiente para la 0.2a: subir a la 1.7.5 |
| 3 | `npm audit`: 1 aviso moderado, `minimatch` 10.2.6 (GHSA-g3hv-75wg-m249), que traen `eslint` 10 y `typescript-eslint` 8.72.2 | Solo en las herramientas de desarrollo (ESLint sobre el código propio), no llega al build. No hay arreglo compatible: `npm audit fix --force` baja ESLint a la 9 | Registrado como [deuda 19](../../deuda-tecnica.md), prioridad baja (usuario) |
| 4 | Un `it()` por caso, sin `.each` ni `it()` dentro de bucles, controlado por ESLint (así quedó la implementación) | Cambiado por el usuario en la revisión: se permiten `.each` y los bucles, y la cifra de tests del frontend sale de la última corrida de `npm test` registrada en "Resultado" de la fase (H13) | Resuelto (H13) |
| 5 | Los tests corren con `TZ=UTC` y sin la división del código de las rutas | En UTC, mostrar una fecha con la zona de la PC hace fallar los tests aunque la PC esté en hora de Paraguay (esta lo está: sin eso, la mutación de la zona sobrevivía). Sin dividir el código, el primer render de las rutas no espera la carga de los pedazos, que con la suite en paralelo pasaba los 5 segundos | Aceptado (usuario) |
| 6 | `tsconfig.test.json` aparte, con los tipos de Node | El código de la aplicación no ve `process` ni `node:fs`; los tests (el de voseo lee `src/`) sí | Informativo |
| 7 | `frontend/.editorconfig` (sangría de 2) y `frontend/.gitattributes` (fin de línea LF) | Prettier toma la sangría de 4 del `.editorconfig` de la raíz (la del backend); con LF fijo, `prettier --check` da lo mismo en todas las PC | Informativo |
| 8 | `toApiDateTime` manda la hora con el desplazamiento (`2026-07-01T09:00:00.000-03:00`), como da `TZDate(...).toISOString()` | Lo pedido. Que la API lo acepte en cada campo de fecha y hora se verifica con la primera llamada real (0.2a o F3) | PENDIENTE DE VERIFICACIÓN |
| 9 | `/sin-permiso` va dentro del layout con sesión; la página de no encontrado, fuera de todo layout | El usuario sin permiso ya tiene sesión: ve la barra y el menú. Una URL desconocida puede no tener sesión | Sin decisión del usuario: se confirma en la 0.2b, con las rutas por permisos |
| 10 | Scripts además de los del diseño: `format:check` y `test:watch`; `test:e2e` avisa que llega en la 0.2 (0.2b) y sale con error | Preparar la 0.2 sin que un `test:e2e` vacío parezca en verde | Informativo |
| 11 | El validador de la documentación (`check-docs.php`) separa las cifras: las de una cláusula que nombra al frontend o de un documento de `frontend/` se comparan con los tests del frontend; la cifra de tests de un archivo de test citado (entre comillas invertidas, con la cifra entre paréntesis) se compara con ese archivo; el resumen dice "Backend:" y "Frontend:". Una cifra del frontend que no es de tests (de rutas, por ejemplo) se excluye y se lista en `--detalle` | Pedido de la entrega; antes toda cifra del frontend se excluía como "de otro proyecto" | Informativo |

Mutaciones de la implementación: las cinco del diseño, cada una detectada por sus tests (la conversión a `ApiError`, reintentar
los 4xx, ignorar el campo en `setError`, formatear con la zona de la PC y leer `perPage`).

**Revisión del 2026-11-12 y correcciones (autorizadas por el usuario), en `b5d3ecf`.** Un revisor que no escribió la entrega encontró 15
hallazgos; todos se corrigieron en la misma rama, cada uno con su test:

| # | Hallazgo | Corrección | Estado |
|---|---|---|---|
| H1 | `formatDate('2020-05-10')` mostraba `09/05/2020`: una fecha sin hora (`birth_date`) pasaba por `Date` como medianoche UTC; un valor inválido rompía el render (`RangeError`) | `formatDateOnly` solo cambia el formato del texto (valida año, mes y día, con los bisiestos); `formatDate` la reconoce. Fechas vacías o inválidas: `''` | Corregido |
| H2 | Un 422 con `branch_ids.0` no llegaba al campo `branch_ids`: `applyValidationErrors` buscaba la clave exacta | La clave va al campo con el mismo nombre, al de un patrón con `*` (`hours.*.starts_at`) o al campo padre; las de un mismo campo juntan sus mensajes | Corregido |
| H3 | Un 204 (o un 2xx sin cuerpo) terminaba en `HTTP_ERROR` | Decisión del usuario: es un éxito sin datos (`data = null`). Un 2xx con un cuerpo que no es JSON sigue siendo `HTTP_ERROR` | Corregido |
| H4 | ESLint no frenaba `window.fetch`, `globalThis.fetch`, `self.fetch` ni `XMLHttpRequest` | `no-restricted-properties` y `XMLHttpRequest` en `no-restricted-globals` | Corregido |
| H5 | El test de voseo no revisaba los textos de una palabra ("Ingrese" en un botón, `'Elija'`) | Revisa todo texto de JSX y las cadenas de una palabra con mayúscula inicial; no da falsos positivos en `src/` | Corregido |
| H6 | `formatGuaranies` convertía el texto con `Number` (coma flotante): `''` mostraba "Gs. 0" y `'12345678901234567'`, `…568` | Decisión del usuario: el texto se valida con `/^-?\d+(?:\.\d+)?$/` y va tal cual a `Intl.NumberFormat`. Vacío o inválido (`null`, `''`, `' '`, `'abc'`, `'12abc'`, `'1e3'`, `NaN`, `Infinity`): `''` | Corregido |
| H7 | Sin test: una cancelación convertida en `NETWORK_ERROR` | El cliente mira la señal (`signal.aborted`), no el tipo del error (`fetch` rechaza con el motivo de `abort()`); test | Corregido |
| H8 | Sin test: un 2xx con `success: false` | Test | Corregido |
| H9 | Sin test que distinga `last_page` de `total` en "Siguiente"; una página fuera de rango mostraba "Mostrando 61 a 32" | Test de la última página con `total` mayor que `last_page`; fuera de rango, "Mostrando 0 a 0 de 32" | Corregido |
| H10 | Sin test: los parámetros `null`, `undefined` o vacíos no van en la URL | Test | Corregido |
| H11 | En el informe figuraba como "`Retry-After` con `parseInt`", pero la mutación que sobrevivió era otra: guaraníes con `parseFloat` (acepta `"12abc"`) | La cubre H6 (`'12abc'` da `''`) | Corregido (por H6) |
| H12 | Ninguna regla prohibía `dangerouslySetInnerHTML` (no se usa) | `no-restricted-syntax` sobre el atributo de JSX | Corregido |
| H13 | Desvío 4 | Decisión del usuario: se permiten `.each` y los bucles (sin las reglas de ESLint). La cifra de tests del frontend sale de la última corrida de `npm test` registrada en "Resultado" de la fase, con la línea de formato fijo ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.``. `/validar-docs` toma la de fecha más reciente; informa si la cantidad de archivos de test no coincide o si no hay corrida; ya no compara las cifras de un archivo de test. `check-docs-test.php`, casos 7 y 8 (55 de 55) | Corregido |
| H14 | Desvío 3 | [Deuda 19](../../deuda-tecnica.md), prioridad baja | Registrado |
| H15 | Desvíos 1, 2 y 5 | Aceptados por el usuario; `@tabler/core` 1.7.5, pendiente para la 0.2a | Registrado |

Además, a pedido del usuario: la sección **Velocidad** de [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md#8-velocidad)
(versiones fijas en el diseño y un solo `npm ci`; `environment: node` para los tests sin DOM; `vitest related` durante el trabajo y
la suite, `typecheck` y `lint` una vez al final; Prettier solo sobre los archivos cambiados; validador, skills y documentación general
en entregas aparte) y cómo excluir `frontend/node_modules` y `backend/vendor` del antivirus en
[`setup.md`](../../../04-desarrollo/setup.md#antivirus-de-windows). Vitest se divide en dos proyectos: `node` (los `.test.ts`) y
`dom` (los `.test.tsx`, jsdom); `client.test.ts` pide jsdom con `// @vitest-environment jsdom`.

Mutaciones de la revisión, cada una detectada por sus tests (solo los relacionados): la fecha sin hora por `Date`; sin el campo
padre (`branch_ids.0`); el 204 como error; la cancelación como `NETWORK_ERROR`; los guaraníes con `Number()`; el voseo sin las
palabras sueltas; "Siguiente" por `total`; un 2xx con `success: false` como éxito; los parámetros vacíos en la URL.

Corrida final, una vez, el 2026-11-12: `typecheck`, `lint` y `build` sin errores ni avisos; `npm test` del frontend, 93 tests en 9 archivos, en
verde, en 9,55 s. Tiempo de la suite según el entorno: antes de la revisión (61 tests, todo en jsdom), 9,35 s (32,9 s en la primera
corrida de la revisión, en frío); con las 93 pruebas todo en jsdom, 9,97 s (preparar el entorno, 46 %); con `node` para los
`.test.ts`, 9,55 s (40 %). La ganancia es chica (4 %): casi todo el tiempo de jsdom es de los `.test.tsx`, que lo necesitan.
