# Instalación en una computadora nueva

Requisitos: PHP 8.3+ con `bcmath`, `pdo_mysql`, `pdo_sqlite`, `intl`; Composer; MySQL 8.

```bash
git clone https://github.com/rubenminarro/syncvet.git syncvet
cd syncvet
composer install
cp .env.example .env
php artisan key:generate
# Crear la base en utf8mb4 (ver README.md) y revisar DB_* en .env
php artisan migrate --seed
php artisan test
```

El detalle de las variables de entorno está en el [`README.md`](../../README.md) de la raíz.

En Laragon, `mysql.exe` no está en el `PATH`: está en `C:\laragon\bin\mysql\<versión>\bin\`.
