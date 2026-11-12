# SyncVet: frontend

React 19 + TypeScript 6 + Vite 8 + Tabler. Consume la API del [backend](../backend/README.md). Las reglas, el stack y las
convenciones están en [`CLAUDE.md`](CLAUDE.md); el plan, en
[`docs/03-plan-desarrollo/fases/frontend/`](../docs/03-plan-desarrollo/fases/frontend/README.md).

## Requisitos

* **Node 26 LTS** (con npm 11). La versión está en `.nvmrc`; con `engine-strict`, `npm ci` se niega a instalar con otra. Con nvm
  para Windows: `nvm install 26` y `nvm use 26`.
* Para usar la API (desde la F0.2a): el backend levantado, por defecto en `http://syncvet.test` (Laragon), según su
  [`README.md`](../backend/README.md).

## Instalación

```bash
cd frontend
npm ci
cp .env.example .env    # opcional: solo si el backend no está en http://syncvet.test
```

`.env` define `API_PROXY_TARGET`: el backend al que el servidor de Vite manda `/api` (mismo origen en desarrollo, sin CORS). Sin
`.env`, `http://syncvet.test`.

## Levantar

```bash
npm run dev       # http://localhost:5173
```

| URL | Qué se ve |
|---|---|
| `/` | El inicio, dentro del layout con la barra (sucursal actual y usuario) y el menú según los permisos |
| `/ingresar` | El formulario de ingreso |
| `/perfil` | Tus datos y el cambio de la contraseña propia |
| `/configuracion` | Provisoria ("En construcción"), solo para el administrador |
| `/sin-permiso` | La página de falta de permiso, dentro del layout |
| Cualquier otra | No encontrado |

## Verificar

```bash
npm run typecheck
npm run lint
npm test
npm run build      # y npm run preview para servir dist/ en http://localhost:4173
```

Pruebas de punta a punta (Playwright, desde la F0.2b), contra la base `syncvet_e2e`: `npm run test:e2e`. **Sin descargar
navegadores:** Playwright usa el Edge que ya viene con Windows (`channel: 'msedge'` en `playwright.config.ts`); no se corre
`npx playwright install`. En cada PC hace falta solo Edge, además de lo de siempre (MySQL de Laragon y `php` en el `PATH`). Con
otro navegador instalado, `E2E_BROWSER_CHANNEL=chrome npm run test:e2e`. El detalle, en
[`setup.md`](../docs/04-desarrollo/setup.md#pruebas-de-punta-a-punta-playwright).
