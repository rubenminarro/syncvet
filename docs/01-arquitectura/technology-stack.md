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
| Frontend | React 19 + TypeScript + Vite + Tabler (repositorio aparte) |

Una dependencia nueva se evalúa antes de agregarla (necesidad y compatibilidad) y se registra en el registro de decisiones.
