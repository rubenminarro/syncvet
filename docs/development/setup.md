# Instalación en una computadora nueva

Requisitos: PHP 8.3+ con `bcmath`, `pdo_mysql`, `pdo_sqlite`, `intl`; Composer; MySQL 8.

```bash
git clone <repositorio> syncvet-backend
cd syncvet-backend
composer install
cp .env.example .env
php artisan key:generate
# .env: APP_TIMEZONE=America/Asuncion, DB_CONNECTION=mysql, DB_DATABASE=syncvet (crear la base en utf8mb4)
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=SpeciesSeeder
php artisan test
```

Antes de la FASE 0 el repositorio no existe: el paquete base se copia sobre un proyecto Laravel 13 nuevo (`README.md` de la raíz).
