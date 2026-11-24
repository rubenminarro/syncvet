---
name: cambiar-pc
description: Pone al día esta PC al llegar de otra (o al instalar una nueva). Trae los cambios, revisa PHP, Node, el .env, las dependencias, la base y Laragon, y propone lo que falta. Usar al cambiar de computadora o cuando el entorno falla sin causa clara.
allowed-tools: Read Grep Glob Bash(git fetch *) Bash(git status *) Bash(git branch --show-current) Bash(git log *) Bash(git rev-list *) Bash(bash .claude/skills/cambiar-pc/scripts/check-env.sh*) Bash(tail -n *)
---

# Cambiar de PC

Deja esta PC lista para seguir el trabajo hecho en otra. **No corre la suite de tests, no crea ramas, no commitea y nunca muestra
valores del `.env`** (solo los nombres de las claves). Todo, desde la raíz del repositorio.

## 1. Diagnóstico (solo lectura)

```bash
git fetch origin --prune
bash .claude/skills/cambiar-pc/scripts/check-env.sh > "<scratchpad>/check-env.txt" 2>&1
tail -n 40 "<scratchpad>/check-env.txt"
```

Son tres comandos separados, sin `;` ni `&&`: así cada uno coincide con su permiso de `allowed-tools`.

El script tarda unos 15 s. Hace los controles de [`setup.md`](../../../docs/04-desarrollo/setup.md) y muestra una línea por control
(`OK`, `AVISO` o `FALLA`): Git y los restos del monorepo, PHP (versión, extensiones, GD, zona `-03:00`, `curl.cainfo`, límites),
Composer, Node según `frontend/.nvmrc`, las claves de `backend/.env` contra `.env.example`, `vendor` y `node_modules` contra sus
*lockfiles*, las migraciones pendientes, la zona de la sesión de MySQL, el vhost de Laragon, `http://syncvet.test/api/me` y Edge.

**No leer `setup.md` entero** (unos 5k tokens): solo la sección que nombra una línea `FALLA` o `AVISO`.

## 2. Ponerse al día (con la confirmación del usuario)

Informar el diagnóstico y proponer, en este orden, **solo lo que haga falta**. Preguntar una sola vez (AskUserQuestion, con
selección múltiple) qué se aplica:

1. **Traer los cambios**: `git pull --ff-only`, solo con el árbol limpio. Con cambios sin commitear, o si la rama divergió, no
   hacer nada: informar y dejar que el usuario decida. Nunca `stash`, `reset` ni `checkout` de archivos.
2. **Dependencias**: `cd backend && composer install` si `vendor` no coincide; `cd frontend && npm ci` si `node_modules` no
   coincide (tarda varios minutos: en segundo plano, con la salida a un archivo, y esperar el aviso de fin).
3. **Base de desarrollo (`syncvet`)**, si hay migraciones pendientes. Ofrecer las dos opciones de `setup.md` §"Datos de demostración
   y cambio de rama":
   * Conservar los datos: `php artisan migrate` y `php artisan permissions:sync`.
   * Recrearla: `php artisan migrate:fresh --seed`, `php artisan db:seed --class=DemoSeeder` y `php artisan demo:agenda`. **Borra
     todos los datos**: solo con un sí explícito a esta opción, nunca por defecto.
4. **Cachés**: `php artisan optimize:clear` si el script avisa de configuración cacheada o después de cambiar el `.env`.

Lo que el script no puede arreglar se informa con su sección de `setup.md` y se deja al usuario: el `php.ini` (límites,
`curl.cainfo`, `timezonedb`, GD), la versión de Node (`nvm use`), el vhost de nginx, iniciar Laragon y las claves del `.env`. Las
claves que faltan respecto de `.env.example` se nombran con su línea de `.env.example` (que no tiene secretos) para que el usuario
las copie; **el `.env` no se edita ni se lee entero**.

Las exclusiones de Microsoft Defender (`setup.md` §"Antivirus de Windows") piden administrador: solo recordarlas en una PC nueva.

## 3. Verificación

Después de aplicar algo, volver a correr el script (misma redirección) y mostrar solo las líneas que no son `OK`. Si quedan
`FALLA`, no seguir con otra tarea sin avisar.

## 4. Informe

Lista simple, en español (voseo):

```text
Git           Rama, commits traídos y si quedó limpia
Aplicado      Lo que se corrió (pull, composer, npm, migraciones, cachés)
Pendiente     Cada FALLA o AVISO que quedó, con su sección de setup.md
Siguiente     /retomar, si el entorno quedó sin fallas
```
