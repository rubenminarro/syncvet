# F0 — Base del frontend

Etapa: Base. Estado: **DISEÑO**, en `docs/frontend-analisis`, con las decisiones del análisis tomadas por el usuario el 2026-11-12
(R-058) y las [preguntas del diseño](#preguntas-del-diseño) abiertas. El estado vigente de cada fase está en
[`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

Dejar `frontend/` listo para construir pantallas: el proyecto con sus herramientas y convenciones (0.1, sin backend) y la sesión
contra la API real (0.2): ingresar, salir, la sucursal actual y el menú según los permisos. Ninguna pantalla de negocio: las de
clientes y pacientes empiezan en la F1.

## Entregas

| Entrega | Contenido | Necesita el backend | Rama | Estado |
|---|---|---|---|---|
| 0.1 | Esqueleto: proyecto, herramientas, rutas, capa de la API con la tabla de errores, componentes base y tests; sin llamar a la API real | No | `feature/frontend-f0-01-esqueleto` | PENDIENTE |
| 0.2 | Sesión: ingresar, salir, `me`, sucursal actual, cierre en todas las pestañas, vencimiento, menú por permisos y pruebas de punta a punta | Sí: la [entrega de soporte del backend](../backend/front-00-soporte-del-frontend.md) (B1 a B3) | `feature/frontend-f0-02-sesion` | PENDIENTE |

El orden de las fases del frontend es F0 → F1 → F2 → F3 (R-058), las tres últimas sobre la API ya cerrada de las fases 1 a 3 del
backend. Cada entrega recorre los pasos 4 a 7 de [`prompts/README.md`](../../prompts/README.md) y deja los tests en verde.

### Orden con la entrega del backend

```text
main ─┬─ feature/front-00-me-sucursales (B1–B3) ── merge ─┐
      │                                                    ├─ feature/frontend-f0-02-sesion
      └─ feature/frontend-f0-01-esqueleto ─────── merge ──┘
```

La 0.1 no llama a la API real (los tests usan MSW con el contrato ya en `snake_case`, como queda después de B3), así que **puede ir
antes o en paralelo** con la entrega del backend: tocan carpetas distintas (`frontend/` y `backend/`) y no se pisan. La 0.2 necesita
las dos integradas. Propuesta: primero la entrega del backend (es corta y cierra el contrato que la 0.1 tipa), después la 0.1; si
se prefiere avanzar en el frontend, la 0.1 primero no cambia nada, porque sus tipos ya nacen con `per_page`, `current_page` y
`last_page`.

## Stack

Aceptado por el usuario (R-058). Las versiones exactas se fijan en la 0.1 y quedan en el `package.json` del frontend y en
[`technology-stack.md`](../../../01-arquitectura/technology-stack.md).

| Tema | Elección |
|---|---|
| Entorno | Node 26 LTS en todas las PC, con `frontend/.nvmrc` y `engines` en `package.json`; npm con `package-lock.json` |
| Base | React 19, TypeScript en modo estricto, Vite |
| Interfaz | Tabler oficial (`@tabler/core` sobre Bootstrap 5) y `@tabler/icons-react`; una capa propia en `src/components/ui`. Sin Tailwind |
| Rutas | TanStack Router, con las URL en castellano |
| Datos del servidor | TanStack Query sobre un cliente Axios único |
| Formularios | React Hook Form con Zod; los errores de la API (`errors`) van a sus campos |
| Tipos de la API | Escritos a mano en cada módulo, con el contrato común en `src/lib/api/types.ts`; sin generarlos (Scramble descartado por ahora) |
| Tests | Vitest con Testing Library y MSW; Playwright para punta a punta contra la base `syncvet_e2e` con el `DemoSeeder` |
| Calidad | ESLint con `typescript-eslint` y las reglas de hooks; `tsc` sin errores |
| Desarrollo | El proxy de Vite manda `/api` al backend: mismo origen, sin CORS en desarrollo |

## Estructura

```text
frontend/
├── .nvmrc                  26
├── .env.example            VITE_API_PROXY_TARGET (el backend del proxy de desarrollo)
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
    │   ├── api/            client.ts (Axios), types.ts (contrato), errors.ts (ApiError y la tabla de error_code)
    │   ├── auth/           (0.2) sesión, vencimiento, canal entre pestañas y permisos
    │   └── format/         Fechas en la zona de Asunción y guaraníes
    ├── components/ui/      Capa propia sobre Tabler: botón, campo, alerta, aviso, tabla, paginación, página de error
    ├── features/<módulo>/  api.ts, types.ts, componentes y páginas de cada módulo (desde la F1; en la F0, auth)
    └── test/               Configuración de Vitest y los handlers de MSW
```

Rutas de la F0: `/ingresar` (pública), `/` (inicio, con sesión), `/sin-permiso` y la página de no encontrado. Las de los módulos
siguen la misma regla: `/clientes`, `/clientes/$id`, `/pacientes`, `/agenda`, `/turnos`, `/configuracion`.

## Convenciones

Se escriben en [`frontend/CLAUDE.md`](../../../../frontend/CLAUDE.md) en la 0.1; este es su contenido.

* **Textos en español con voseo**, como la API ("Ingresá tu email", "Elegí tu sucursal"). El `message` de la API ya viene en
  español y con voseo: se muestra tal cual, nunca se reescribe.
* **Decidir por `error_code`, nunca por el texto ni solo por el código HTTP** (ver la [tabla](#qué-hace-la-interfaz-con-cada-código-de-error)).
* **Toda llamada a la API pasa por `src/lib/api/client.ts`**: pone el token, el `Accept: application/json` y convierte cualquier
  error en un `ApiError` (`status`, `errorCode`, `message`, `errors`, `retryAfter`). Ningún componente usa Axios ni `fetch` directo.
* **Lecturas con `useQuery` y cambios con `useMutation`**, con las claves en el `api.ts` de cada módulo. Una mutación no se reintenta
  nunca; una lectura se reintenta una vez solo si falló la red o hubo 5xx, nunca con un 4xx.
* **Tipos escritos a mano** en el `types.ts` del módulo, con los nombres de campo de la API (`snake_case`): no se traducen a
  `camelCase` en el cliente.
* **Componentes de Tabler a través de `src/components/ui`**: las páginas no repiten el marcado de Tabler. Sin Tailwind ni otra
  biblioteca de componentes.
* **Fechas y dinero**: la API manda fechas con hora en UTC (`Z`); se muestran en `America/Asuncion` con `Intl` (`es-PY`), nunca con la
  zona del navegador. Guaraníes sin decimales, con separador de miles.
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
| Vencimiento | Un temporizador hasta `expires_at` (los tokens duran 12 horas y la API no los renueva) cierra la sesión local con "Tu sesión venció. Ingresá de nuevo."; además se revisa al volver a la pestaña (`visibilitychange`), porque los temporizadores se frenan con la PC suspendida |
| Salir | `POST /api/logout` (si falla, igual se borra la sesión local), se limpia la caché de Query y se avisa a las demás pestañas |
| Todas las pestañas | `BroadcastChannel('syncvet-sesion')`: al salir o vencer, `logout`; cada pestaña que lo recibe hace su propio `POST /api/logout` con su token (si ya estaba revocado, el 401 se ignora), borra lo suyo y va a `/ingresar`. Al cambiar de sucursal, `branch-changed`: las demás pestañas vuelven a pedir `me` e invalidan sus consultas. Pestaña nueva: ver la [pregunta 1](#preguntas-del-diseño) |
| 401 | En cualquier pedido con sesión: se borra la sesión de esa pestaña y se va a `/ingresar?volver=…` con el aviso de sesión vencida |
| Sucursal actual | Selector en la barra superior con `me.branches` (B1); `PATCH /api/me/current-branch`, actualiza `['me']` e invalida el resto de las consultas (los datos dependen de la sucursal). Sin sucursal actual, el inicio pide elegir una |
| Rutas protegidas | El layout con sesión exige una sesión válida (`beforeLoad`); cada ruta declara el permiso que necesita y, sin él, muestra `/sin-permiso` |
| Menú | Solo los módulos ya hechos y permitidos por `me.permissions`; en la F0, Inicio |

## Qué hace la interfaz con cada código de error

La regla general: **se muestra el `message` de la API** y se decide qué hacer por el `error_code`. `NETWORK_ERROR` es el único código
propio del frontend (sin respuesta del servidor). La tabla vive en `src/lib/api/errors.ts` y la usan el cliente y los formularios;
cada módulo puede sumar el trato especial de sus códigos (columna "Más adelante").

| `error_code` | HTTP | Qué hace la interfaz |
|---|---|---|
| `UNAUTHENTICATED` | 401 | Borra la sesión de la pestaña y lleva a `/ingresar?volver=…` con "Tu sesión venció. Ingresá de nuevo." |
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

1. Proyecto Vite con React 19 y TypeScript estricto en `frontend/`; `.nvmrc` con `26` y `engines.node` `>=26 <27`; npm.
2. Tabler (`@tabler/core`, `@tabler/icons-react`) y la capa `src/components/ui` con lo que usan la F0 y la F1: botón, campo de texto
   con su error, alerta, avisos (*toasts*), tarjeta, tabla con paginación (`meta.pagination` en `snake_case`), página de error y
   cargando.
3. TanStack Router (plugin de Vite, rutas por archivo): layout público y layout con sesión (sin la sesión real todavía), `/ingresar`
   como maqueta, `/`, `/sin-permiso` y no encontrado.
4. `src/lib/api`: el cliente Axios (base `/api`, `Accept: application/json`, el token si hay sesión), los tipos del contrato
   (`ApiSuccess<T>`, `ApiFailure`, `Pagination`) y `ApiError` con la tabla de `error_code`. TanStack Query con los reintentos de las
   [convenciones](#convenciones).
5. `src/lib/format`: fechas en `America/Asuncion` y guaraníes.
6. El proxy de Vite (`/api` → `VITE_API_PROXY_TARGET`, por defecto `http://syncvet.test`).
7. Vitest con Testing Library y MSW; ESLint; scripts `dev`, `build`, `preview`, `lint`, `typecheck`, `test`.
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

### 0.2 Sesión

**Requisito.** La entrega del backend (B1 a B3) integrada a `main`.

**Alcance.**

1. `src/lib/auth`: la sesión en `sessionStorage`, el temporizador de vencimiento, el canal `syncvet-sesion` y `can(permiso)`, según la
   [sesión](#sesión-02).
2. `/ingresar` real (email y contraseña, con los errores de la tabla); salir desde el menú del usuario.
3. Layout con sesión: barra con la clínica, el usuario, sus roles y el selector de sucursal actual (B1); menú por permisos; inicio con
   la sucursal actual o el pedido de elegir una.
4. Las rutas protegidas con su permiso y `/sin-permiso`.
5. Playwright: `e2e/` y `playwright.config.ts`; antes de correr, la base `syncvet_e2e` se recrea con `migrate:fresh` y el
   `DemoSeeder`, con el backend levantado desde `backend/` en un puerto propio y las variables de la base pasadas por el entorno (el
   `DemoSeeder` corre solo en `local` y `testing`). **La preparación se niega a correr si la base no termina en `_e2e`.**
6. Documentación: `frontend/CLAUDE.md` (sesión, comandos de Playwright), la base `syncvet_e2e` en `setup.md` y en
   [`testing.md`](../../../04-desarrollo/testing.md).

**Tests.**

| Qué | Cómo |
|---|---|
| Sesión guardada | Se guarda y se lee; una sesión vencida al abrir se borra sin llamar a la API |
| Vencimiento | Con temporizadores falsos: al llegar a `expires_at` se cierra la sesión con el aviso; al volver a la pestaña con la hora pasada, también |
| Entre pestañas | Al salir se publica `logout`; al recibirlo se hace `POST logout` con el token propio, se borra la sesión y se va a `/ingresar`; `branch-changed` invalida las consultas |
| 401 | Un 401 en cualquier pedido borra la sesión y lleva a `/ingresar?volver=…`; `volver` con una URL externa se ignora |
| Ingresar | 422 `INVALID_CREDENTIALS`, 422 `VALIDATION_ERROR` en los campos, 429 con cuenta regresiva, 403 `USER_INACTIVE` y `CLINIC_NOT_AVAILABLE`: cada uno con su mensaje |
| Permisos | Una ruta sin el permiso muestra `/sin-permiso`; el menú oculta lo no permitido |
| Sucursal | El selector lista `me.branches`; el cambio llama a `PATCH me/current-branch`, actualiza `me` e invalida las consultas |
| Punta a punta (Playwright) | Ingresar con un usuario del `DemoSeeder` y ver el inicio; credenciales incorrectas; una ruta protegida sin sesión lleva a `/ingresar` y, después de ingresar, vuelve; cambiar de sucursal; salir en una pestaña cierra la otra |

**Mutaciones.** No borrar la sesión con un 401; no publicar `logout` en el canal; no programar el temporizador; no invalidar las
consultas al cambiar de sucursal; la preparación de Playwright sin el control del nombre de la base.

**Qué revisar en el navegador.** Con el backend en `syncvet.test` y `npm run dev`: ingresar con `admin-a`, `recepcion-a` y `vet-a` del
`DemoSeeder` y ver el menú de cada uno; credenciales incorrectas cinco veces y la cuenta regresiva; cambiar de sucursal y ver el cambio
en otra pestaña; salir en una pestaña y ver que la otra vuelve a `/ingresar`; borrar `expires_at` a mano en el almacenamiento de la
sesión (DevTools) y ver el aviso; inactivar al usuario desde otra sesión de administrador y ver que el siguiente pedido lleva a
`/ingresar`; la consola sin errores.

## Documentación por entrega

| Entrega | Actualizar |
|---|---|
| 0.1 | `frontend/CLAUDE.md`, `frontend/README.md`, `technology-stack.md`, `overview.md`, `setup.md`, este documento (Resultado), el roadmap, `modulos.md` si corresponde y el handoff |
| 0.2 | `frontend/CLAUDE.md`, `setup.md` y `testing.md` (`syncvet_e2e` y Playwright), este documento, el roadmap y el handoff |

## Preguntas del diseño

| # | Pregunta | Recomendación |
|---|---|---|
| 1 | Con `sessionStorage`, una pestaña nueva (no duplicada) no tiene sesión. ¿Pide ingresar de nuevo, o le pide la sesión a una pestaña abierta por el `BroadcastChannel`? | Pedírsela a una pestaña abierta: la recepción trabaja con varias pestañas, y el token compartido no expone más que `sessionStorage` (un XSS lee los dos). Con el token compartido, un 401 cierra todas las pestañas, no solo la que lo recibió |
| 2 | ¿Un aviso antes de que venza la sesión? La API no renueva tokens: al vencer, hay que volver a ingresar | Sí: 10 minutos antes, "Tu sesión vence en 10 minutos. Guardá lo que estés haciendo." |
| 3 | La contraseña propia (`PATCH me/password`) ¿va en la 0.2 (una página `/perfil`) o en la F2 con los usuarios? | En la 0.2: la API ya existe y es lo primero que hace un usuario nuevo |

## Criterio de cierre

* La 0.1 y la 0.2 cerradas, con los tests de Vitest y de Playwright en verde, `lint`, `typecheck` y `build` sin errores.
* Con la base `syncvet_e2e`: ingresar, cambiar de sucursal, salir en todas las pestañas y el vencimiento, probados de punta a punta.
* Las colecciones de Postman de la entrega del backend, en verde (R-057: todas, al cerrar la fase).

## Resultado

Sin entregas todavía.
