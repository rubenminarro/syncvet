# F0 — Base del frontend

Etapa: Base. Estado: **COMPLETADO** el 2026-11-12 (R-063; [cierre](f0-resultados.md#cierre-de-la-f0)) e integrado a `main`. Diseño aprobado
por el usuario el 2026-11-12 (R-058), en `docs/frontend-analisis`, con las decisiones del análisis y las
[preguntas del diseño](#preguntas-del-diseño) respondidas. El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Dejar `frontend/` listo para construir pantallas: el proyecto con sus herramientas y convenciones (0.1, sin backend) y la sesión
contra la API real (0.2, dividida en 0.2a y 0.2b por decisión del usuario, R-060): ingresar, salir, la sucursal actual y el menú
según los permisos. Ninguna pantalla de negocio: las de
clientes y pacientes empiezan en la F1.

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 0.1 | Esqueleto: proyecto, herramientas, rutas, capa de la API con la tabla de errores, componentes base y tests; sin llamar a la API real | No | `feature/front-0-1-esqueleto`, desde `feature/front-00-me-sucursales` (R-059) | COMPLETADO (R-060, [resultado](f0-resultados.md#entrega-01)) |
| 0.2a | Sesión: ingresar, `me`, salir, 401 y 403 con sesión, vencimiento con su aviso, cierre en todas las pestañas y la sesión pedida desde una pestaña nueva | Sí: la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | `feature/front-0-2a-sesion`, desde `feature/front-0-1-esqueleto` (R-060) | COMPLETADO (R-061, [resultado](f0-resultados.md#entrega-02a)) |
| 0.2b | Permisos: menú y rutas según los permisos, selector de sucursal, `/perfil` con la contraseña propia y Playwright contra `syncvet_e2e` | Sí, como la 0.2a | `feature/front-0-2b-permisos`, desde `feature/front-0-2a-sesion` | COMPLETADO (R-062, [resultado](f0-resultados.md#entrega-02b)) |

El orden es F0 → F1 → F2 → F3 y después la FASE 4 del backend (R-058, decisión D2); las tres últimas fases del frontend van sobre la API ya cerrada de las fases 1 a 3 del
backend. Cada entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md) y deja los tests en verde.

### Orden con la entrega del backend

```text
main ── feature/front-00-me-sucursales (B1–B3, cerrada, R-059)
           └─ feature/front-0-1-esqueleto (0.1, cerrada, R-060)
                └─ feature/front-0-2a-sesion (0.2a, cerrada, R-061)
                     └─ feature/front-0-2b-permisos (0.2b, cerrada, R-062) ── merge a main al cerrar la F0 (R-063)
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
| Aviso previo | 10 minutos antes de `expires_at`, un aviso que queda visible con los minutos que quedan, actualizados: "Tu sesión vence en N minutos. Guardá los cambios pendientes." (P2; el texto, desvío 1 de la 0.2a, R-061) |
| Vencimiento | Un temporizador hasta `expires_at` (los tokens duran 12 horas y la API no los renueva) cierra la sesión con "Tu sesión terminó. Ingresá de nuevo." en las pestañas con ese token (R-061); además se revisa al volver a la pestaña (`visibilitychange`, `focus` y `pageshow`) y cada 15 s, porque los temporizadores se frenan con la PC suspendida |
| Pestaña nueva | Sin sesión propia, publica `session-request` en `BroadcastChannel('syncvet-sesion')` y espera la respuesta un instante (1000 ms; 300 ms hasta la revisión de la 0.2b); una pestaña con sesión responde `session-share` con el token y `expires_at`, y la nueva lo guarda en su `sessionStorage`. Sin respuesta, va a `/ingresar` (P1). Todas las pestañas comparten así un mismo token |
| Salir | Primero se borra la sesión local, se limpia la caché de Query y se publica `logout` a todas las pestañas; después, `POST /api/logout` sin esperarlo, con un tiempo límite de 5 s (si falla o no responde, la sesión ya se cerró; R-061) |
| Todas las pestañas | El mensaje `logout` lleva el id del token (la parte anterior a `\|` del token de Sanctum, que no es secreta) y `all`. Con "Salir" (`all: true`), cada pestaña borra su sesión y su caché y va a `/ingresar`, y una con otro token (dos ingresos simultáneos) también hace su propio `POST /api/logout`. Con el vencimiento o un error de sesión (`all: false`), solo cierran las pestañas con ese mismo token (R-061). Los mensajes con otro formato se descartan. Al cambiar de sucursal, `branch-changed`: las demás pestañas vuelven a pedir `me` e invalidan sus consultas |
| 401 | En un pedido con el token de la sesión (si el token ya cambió, se ignora): se cierra la sesión en las pestañas con ese token (`logout` en el canal, sin `POST /api/logout`) y se va a `/ingresar?volver=…` con el aviso de sesión vencida (P1, R-061). `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE` con sesión, igual, con el mensaje de la API |
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
| `UNAUTHENTICATED` | 401 | Cierra la sesión en las pestañas con ese token y lleva a `/ingresar?volver=…` con "Tu sesión terminó. Ingresá de nuevo." (R-061: "terminó", no "venció") |
| `USER_INACTIVE` | 403 | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `CLINIC_NOT_AVAILABLE` | 403 | En el login, el mensaje en el formulario; con sesión, como 401 pero con el mensaje de la API |
| `FORBIDDEN` | 403 | Al cargar una página: `/sin-permiso`. En una acción: aviso con el mensaje; el formulario queda como estaba |
| `RESOURCE_NOT_FOUND` | 404 | Al cargar una página de detalle: la página de no encontrado. En una acción: aviso con el mensaje e invalidar la lista |
| `VALIDATION_ERROR` | 422 | Cada clave de `errors` va a su campo (React Hook Form `setError`); las que no son de un campo del formulario, a una alerta arriba. Foco en el primer campo con error |
| `INVALID_CREDENTIALS` | 422 | Mensaje en el formulario de ingreso; se borra la contraseña |
| `AUTH_TOO_MANY_ATTEMPTS`, `TOO_MANY_REQUESTS` | 429 | Mensaje en el formulario y el botón deshabilitado con la cuenta regresiva de `Retry-After` (sin la cabecera, 60 s; R-061) |
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

Vacío: la entrega se cerró (R-060) y todo lo que había acá pasó a su [resultado](f0-resultados.md#entrega-01).

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
| `src/lib/auth/channel.ts` | `SessionChannel` (`post`, `subscribe`, `close`) sobre `BroadcastChannel('syncvet-sesion')`; sin `BroadcastChannel`, uno que no hace nada. Mensajes: `session-request`, `session-share` (con `token` y `expires_at`) y `logout` (con `token_id`, el `notice` para `/ingresar`, o `null` si fue un "Salir", y `all`, desde R-061), validados por `isSessionMessage` |
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
   deja el `notice` "Tu sesión terminó. Ingresá de nuevo." (R-061). El `beforeLoad` de `_sesion`: sin sesión propia, `session-request` y
   espera 1000 ms (300 ms hasta la revisión de la 0.2b); sin respuesta, `redirect` a `/ingresar?volver=…` (sin `volver` si la ruta era `/`). Con sesión,
   `ensureQueryData(meQueryOptions())`; un error de sesión hace `redirect` a `/ingresar` (el `SessionManager` ya la cerró). Mientras
   tanto, el `pendingComponent` del layout ("Cargando tu sesión…", `pendingMs: 0`): nunca se ve el formulario de ingreso.
3. **Salir** (actualizado en el cierre, R-061). Primero cierra: borra el almacenamiento, publica `logout` con `all: true` y limpia la
   caché de Query; el layout va a `/ingresar` sin `volver`. Después, `POST logout` con el token, sin esperarlo, con
   `AbortSignal.timeout` de 5 s (si falla o no responde, la sesión ya está cerrada).
4. **Error de sesión en un pedido con token** (actualizado en el cierre, R-061). Solo si el pedido salió con el token actual de la
   sesión (una respuesta tardía de un token viejo se ignora): cierra con `notice` = "Tu sesión terminó. Ingresá de nuevo." (401; R-061) o el
   `message` de la API (`USER_INACTIVE`, `CLINIC_NOT_AVAILABLE`) y publica `logout` con `all: false`; el layout va a
   `/ingresar?volver=…` y la página muestra el `notice` como alerta de aviso. Un pedido con un token explícito (el `logout`) no
   toca la sesión.
5. **Vencimiento** (actualizado en el cierre, R-061). Un temporizador a `expires_at - 10 min` enciende el aviso previo (si ya pasó,
   al instante) y otro a `expires_at` cierra como un 401. Un intervalo de 15 s compara la hora con `expires_at`: cierra si venció y
   actualiza los minutos del aviso. Al volver a la pestaña (`visibilitychange`, `focus` y `pageshow`), se relee el almacenamiento: si
   `expires_at` cambió se reprograma (así se prueba a mano desde DevTools), y si venció o no está, se cierra.
6. **Pestañas** (actualizado en el cierre, R-061). Cada mensaje se valida completo (campos exactos y sus tipos) y uno con otro formato
   se descarta. `session-request`: responde `session-share` la que tiene una sesión sin vencer; la nueva descarta una sesión vencida
   o de un token cuyo `logout` ya recibió. `logout`: con el mismo `token_id`, la pestaña cierra sin publicar de nuevo; con otro
   `token_id`, solo si `all` (el usuario salió), y entonces también revoca su token con su propio `POST logout`. Una pestaña sin
   sesión solo borra su almacenamiento.

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

Vacío: la entrega se cerró (R-061) y todo lo que había acá pasó a su [resultado](f0-resultados.md#entrega-02a).

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
   `DemoSeeder` corre solo en `local` y `testing`). **La preparación se niega a correr si la base no es `syncvet_e2e`** (pedido
del usuario al implementar: el nombre exacto, más estricto que "termina en `_e2e`").
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

**Decisión del usuario (2026-11-12), al empezar el diseño detallado.** En la F0 no hay ningún módulo con permiso (Inicio y
`/perfil` son de todos), así que los tres roles verían el mismo menú y Playwright no tendría una ruta real sin permiso. Se suma
**`/configuracion` provisoria**: una página "En construcción" con el permiso `system-settings.update` (solo `administrador`, según
`backend/tests/Fixtures/clinic-roles.json`); el administrador la ve en el menú y en el inicio, recepción y veterinario no, y al
entrar por la URL van a `/sin-permiso`. La F2 la reemplaza por la pantalla real.

**Diseño detallado (2026-11-12, escrito con la implementación que pidió el usuario; sin revisión previa).**

*Versiones.* Un solo paquete nuevo, de desarrollo: `@playwright/test` **1.56.1** (`npm install -D @playwright/test@1.56.1`, sin
consultar npm, `frontend/CLAUDE.md` §8). Sin descargar navegadores: Playwright usa el Edge instalado (`channel: 'msedge'`; en otra PC
se cambia con `E2E_BROWSER_CHANNEL`, por ejemplo `chrome`).

*Archivos.*

| Archivo | Contenido |
|---|---|
| `src/lib/auth/permissions.ts` | `can(me, permiso)`: `true` solo si `me.permissions` lo incluye (sin `me`, `false`); `PERMISSIONS`, las constantes de los permisos que usa el frontend (en la F0, `settingsUpdate = 'system-settings.update'`) |
| `src/features/auth/permissions.tsx` | `useCan()` (devuelve `can(permiso)` sobre el `me` de la caché), `<RequirePermission permission>` (muestra sus hijos solo con el permiso; `fallback` opcional) y `requirePermission(permiso)`, el `beforeLoad` de una ruta: sin el permiso, `redirect` a `/sin-permiso` (con `replace`) |
| `src/app/menu.ts` | El menú: `{ to, label, icon, permission? }`; en la F0, Inicio (sin permiso) y Configuración (`system-settings.update`). `visibleMenu(items, can)` filtra por permiso |
| `src/app/SessionLayout.tsx` | El menú filtrado; el selector de sucursal en la barra; el nombre del usuario lleva a `/perfil`; sin sucursal actual, el aviso para elegir una (o, sin sucursales asignadas, el de pedírselas al administrador) arriba del contenido |
| `src/features/auth/BranchSelector.tsx` | Un `select` con `me.branches` (con una sola sucursal, que ya es la actual, solo el nombre). Al cambiar: `PATCH me/current-branch`; con el 200, `['me']` con la respuesta, se invalidan las consultas que no son `['me']`, `branch-changed` en el canal y un aviso de éxito; con un error, el aviso con el mensaje (un 422 vuelve a pedir `me`: la lista pudo cambiar) |
| `src/features/auth/api.ts` | `updateCurrentBranch(branchId)`, `applyBranchChange(queryClient)` (vuelve a pedir `me` al servidor e invalida las consultas de la sucursal, B5 y B6) y `updateOwnPassword(form)` (`PATCH me/password`, sin la repetición) |
| `src/lib/auth/channel.ts`, `session.ts` | Mensaje `branch-changed` (sin más campos); `SessionManager.publishBranchChanged()`. Una pestaña con sesión que lo recibe invalida `['me']` (que se vuelve a pedir) y las consultas de la sucursal (B6) |
| `src/lib/api/query-keys.ts` | La convención de claves (B6 de la revisión): `branchKey(sucursal, …)` para las consultas que dependen de la sucursal e `invalidateBranchQueries` |
| `src/features/auth/ProfilePage.tsx`, `schemas.ts` | `/perfil`: "Tus datos" (nombre, email, clínica, roles, sucursal actual y sucursales, y la ficha de veterinario si la hay) y "Cambiar la contraseña" (`current_password`, `password` y `password_confirmation`, los tres con `PasswordField`; la repetición no va a la API). 422 a sus campos (`applyValidationErrors`; el resto, a la alerta); 429 con el mensaje y el botón con la cuenta regresiva (60 s sin `Retry-After`); con el 200, el formulario se vacía, un aviso de éxito y la sesión sigue (la API conserva el token del pedido y revoca los demás) |
| `src/app/ConfigurationPage.tsx`, `src/routes/_sesion/configuracion.tsx` | La página provisoria, con `beforeLoad: requirePermission(PERMISSIONS.settingsUpdate)` |
| `src/routes/_sesion/perfil.tsx` | `/perfil` (sin permiso: es de todos) |
| `src/app/RouteErrorPage.tsx`, `router.ts` | `defaultErrorComponent`: un 403 `FORBIDDEN` de la API al cargar una página muestra la página de sin permiso **con el `message` de la API**; otro error, la página de error con `userMessage` |
| `src/app/ForbiddenPage.tsx` | `message` opcional (el de la API); sin él, el texto de siempre |
| `src/app/HomePage.tsx` | La sucursal actual (o el pedido de elegir una) y el acceso a Configuración dentro de `<RequirePermission>` |
| `playwright.config.ts` | `testDir: 'e2e'`, `testMatch: '*.spec.ts'`, un solo *worker* y en serie (la base es compartida); `globalSetup`; dos `webServer`: el backend (`php artisan serve --no-reload` en `127.0.0.1:8010`, desde `backend/`, con `DB_DATABASE=syncvet_e2e`; si ya hay uno levantado en ese puerto, lo usa) y Vite en `5174` con `API_PROXY_TARGET` hacia el backend |
| `e2e/support/database.ts` | `E2E_DATABASE = 'syncvet_e2e'` y `assertE2eDatabase(nombre)`: lanza un error con cualquier otro nombre (también con mayúsculas, espacios o un sufijo). Con su test de Vitest (`src/test/e2e-database.test.ts`, desvío 14) |
| `e2e/global-setup.ts` | La preparación, en orden: (1) `assertE2eDatabase` sobre la base pedida; (2) pregunta a Laravel qué base resuelve con ese entorno (`artisan tinker --execute`) y vuelve a controlarla (cubre una configuración cacheada); (3) `migrate --force` (crea la base si falta), `migrate:fresh --seed --force` y `db:seed --class=DemoSeeder --force`, con `DEMO_PASSWORD` **generada al azar en cada corrida**; (4) ingresa como `admin-a` por la API del backend de `:8010` con esa contraseña: si no puede, el backend no usa la base recién preparada y se niega a seguir; (5) por la API, crea la sucursal `NOR` "Sucursal Norte" y se la asigna a `admin-a` (el `DemoSeeder` da una sola sucursal por usuario). La contraseña llega a los tests por `process.env` |
| `e2e/support/session.ts` | `login(page, usuario)`, `logout(page)`, `openSecondTab(context, usuario)` (espera, con su tiempo límite, a que la sesión llegue a la pestaña nueva), los usuarios del `DemoSeeder` y la contraseña de la corrida |
| `e2e/support/prepare.ts` | Los pasos de la preparación, con dependencias inyectadas (los prueba `src/test/e2e-prepare.test.ts`); al final, la contraseña de la corrida en `e2e/.e2e-credentials`, ignorado por Git (desvío 2) |
| `e2e/*.spec.ts` | Los casos de la tabla de abajo |
| `package.json` | `test:e2e`: `playwright test` |
| `tsconfig.e2e.json` | `playwright.config.ts` y `e2e/`, con los tipos de Node |

*Flujos.*

1. **Permisos.** El `beforeLoad` de `_sesion` ya dejó `me` en la caché; el de la ruta hija (`requirePermission`) lo lee con
   `queryClient.getQueryData` y, sin el permiso, `redirect` a `/sin-permiso`. El menú y los botones usan `useCan()`. Un 403 de la
   API manda igual: al cargar, la página de sin permiso con el `message`; en una acción, la alerta o el aviso con el `message`.
2. **Sucursal.** El selector es controlado por `me.current_branch`: mientras el `PATCH` está en curso queda deshabilitado; si falla,
   vuelve solo a la actual. Con el 200, `me` se vuelve a pedir al servidor (no se toma la respuesta del `PATCH`: con dos pestañas
   cambiando a la vez, manda la que quedó guardada) y se invalidan solo las consultas de la sucursal (`src/lib/api/query-keys.ts`).
   Las demás pestañas reciben `branch-changed`, vuelven a pedir `me` e invalidan las de la sucursal (los permisos no cambian con la
   sucursal: son de la clínica). Al volver a la pestaña, `me` se vuelve a pedir: la sucursal pudo cambiar en otra PC (B5, B6 y M2
   de la revisión).
3. **Contraseña.** La actual, la nueva y "Repetí la contraseña nueva" (desvío 7, decisión del usuario en la revisión): la
   repetición se controla solo en el navegador (la API no la pide, `UpdateOwnPasswordRequest`) y, distinta, marca su campo sin llamar
   a la API. La regla de la contraseña nueva (12 caracteres, mayúsculas, minúsculas, números, símbolos y no filtrada) la valida la
   API; el formulario solo pide que los campos no estén vacíos. Las contraseñas no quedan en la caché de mutaciones (M1).
4. **Playwright.** `npm run test:e2e` desde `frontend/`, con MySQL andando (Laragon) y PHP en el `PATH`: levanta el backend y Vite
   en sus puertos propios (o usa el backend que ya esté en `:8010`, que tiene que usar `syncvet_e2e`) y prepara la base cada vez.

*Tests de Vitest.*

| Qué | Cómo |
|---|---|
| `can` y `visibleMenu` | Con los permisos de los tres roles de `backend/tests/Fixtures/clinic-roles.json` (importado tal cual, no copiado): el administrador ve Configuración; recepción y veterinario, no |
| Rutas | Con cada rol: `/configuracion` muestra la página o lleva a `/sin-permiso`; `/perfil` se ve con los tres; el botón del inicio, solo con el permiso; `/sin-permiso` dentro del layout (la barra se ve) |
| Error de la API | `RouteErrorPage` con un 403 `FORBIDDEN` muestra el `message` de la API |
| Sucursal | El selector lista `me.branches`; el cambio manda `branch_id`, actualiza la barra, invalida una consulta de otra clave (vuelve a pedirse) y publica `branch-changed`; otra pestaña con ese mensaje vuelve a pedir `me`; sin sucursal actual, el aviso; un 422, el aviso con el mensaje |
| Perfil | Los datos; `PATCH me/password` con la actual y la nueva (la repetición distinta se marca sin llamar a la API); 422 en `current_password` y en `password` a sus campos; 429 con la cuenta regresiva; con el 200, la sesión sigue y el formulario se vacía; el botón muestra y oculta cada campo |
| Preparación de Playwright | `assertE2eDatabase` acepta solo `syncvet_e2e` |

*Casos de Playwright* (contra `syncvet_e2e`): el ingreso de cada rol y su menú; una ruta sin permiso (`recepcion-a` en
`/configuracion`); una ruta protegida sin sesión lleva a `/ingresar` y vuelve después de ingresar; el cambio de sucursal, que otra
pestaña también ve (y vuelve a la original al final); el cambio de contraseña, con la actual incorrecta en su campo, la sesión que
sigue y el ingreso con la nueva (y vuelve a la original al final); salir en una pestaña cierra la otra; credenciales incorrectas; el vencimiento en las dos pestañas con el mismo token (los dos últimos,
desvío 10).

#### Pendiente para el cierre (0.2b)

Vacío: la entrega se cerró (R-062) y todo lo que había acá pasó a su [resultado](f0-resultados.md#entrega-02b).

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

> El resultado de cada entrega y el cierre de la fase están en [`f0-resultados.md`](f0-resultados.md).
