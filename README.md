# SyncVet

Sistema de gestión para clínicas veterinarias de Paraguay. Un solo repositorio con el backend y el frontend.

| Carpeta | Contenido |
|---|---|
| [`backend/`](backend/README.md) | API en Laravel 13 (PHP 8.3, MySQL 8). Instalación, variables de entorno y datos de prueba en [`backend/README.md`](backend/README.md) |
| [`frontend/`](frontend/README.md) | React 19 + TypeScript + Vite + Tabler. Sin empezar |
| [`docs/`](docs/README.md) | Documentación de todo el proyecto: visión, arquitectura, requisitos, plan de desarrollo, entorno y pruebas |

Para retomar el desarrollo, empezar por [`docs/03-plan-desarrollo/handoff.md`](docs/03-plan-desarrollo/handoff.md). Las reglas de
trabajo están en [`CLAUDE.md`](CLAUDE.md).

## Puesta en marcha rápida (backend)

```bash
git clone https://github.com/rubenminarro/syncvet.git
cd syncvet/backend
composer install
cp .env.example .env
php artisan key:generate
```

El resto de los pasos, en [`backend/README.md`](backend/README.md#instalación).
