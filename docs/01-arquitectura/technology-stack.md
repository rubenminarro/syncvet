# Tecnologías

| Capa | Elección |
|---|---|
| Backend | Laravel 13 |
| Lenguaje | PHP 8.3+ (extensión bcmath obligatoria) |
| Base de datos | MySQL 8 (`utf8mb4`); SQLite en los tests |
| Autenticación | Laravel Sanctum, tokens Bearer |
| Permisos | spatie/laravel-permission, guard `api` |
| API | REST, JSON con el contrato de `CLAUDE.md` §4 |
| Documentos | PDF desde vistas Blade |
| Procesos automáticos | Laravel Queues y Scheduler |
| Notificaciones | WhatsApp y email |
| Facturación | SIFEN (DNIT, Paraguay), Manual Técnico vigente |
| Frontend | React 19 + TypeScript + Vite + Tabler, en `frontend/` del mismo repositorio (monorepo, R-057) |

Una dependencia nueva se evalúa antes de agregarla (necesidad y compatibilidad) y se registra en el registro de decisiones.

## Frontend

El stack aprobado en R-058, con las versiones fijadas en la F0.1 (exactas, en `frontend/package.json` y `package-lock.json`;
verificadas en npm el 2026-11-12). Las reglas de uso están en [`frontend/CLAUDE.md`](../../frontend/CLAUDE.md).

| Tema | Paquetes y versión |
|---|---|
| Entorno | Node 26 LTS (`frontend/.nvmrc`, `engines` con `engine-strict`), npm 11 |
| Base | `react` y `react-dom` 19.3.1, `vite` 8.3.8, `@vitejs/plugin-react` 6.3.0, `typescript` 6.0.3 (estricto; no TS 7) |
| Interfaz | `@tabler/core` 1.7.5 (solo los estilos; 1.7.4 hasta la 0.2a), `@tabler/icons-react` 3.51.0. Sin Tailwind |
| Rutas | `@tanstack/react-router` 1.170.46, `@tanstack/router-plugin` 1.169.0 |
| Datos del servidor | `@tanstack/react-query` 5.107.1, sobre un cliente propio con `fetch` (sin Axios) |
| Formularios | `react-hook-form` 7.92.0, `zod` 4.6.7, `@hookform/resolvers` 7.0.6 |
| Fechas | `date-fns` 4.4.0, `@date-fns/tz` 1.5.0 |
| Tests | `vitest` 5.1.0, `jsdom` 31.0.0, `msw` 3.0.5, `@testing-library/react` 16.3.3, `@testing-library/dom` 10.4.2, `@testing-library/user-event` 14.6.10, `@testing-library/jest-dom` 7.0.1; Playwright desde la F0.2b |
| Calidad | `eslint` 10.14.0, `@eslint/js` 10.0.1, `typescript-eslint` 8.72.2, `eslint-plugin-react-hooks` 7.1.1, `eslint-plugin-react-refresh` 0.5.7, `eslint-config-prettier` 10.1.8, `globals` 17.14.0, `prettier` 3.10.1 |
| Tipos | `@types/react` y `@types/react-dom` 19.3.0, `@types/node` 26.6.6 |
