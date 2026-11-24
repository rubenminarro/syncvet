---
name: cambiar-rama
description: Cambia a otra rama y deja el entorno acorde (dependencias, base de desarrollo, permisos, cachés). Detecta qué cambió entre las dos ramas y propone solo lo necesario. Usar al cambiar de rama para probar o seguir otra entrega. Recibe el nombre de la rama.
argument-hint: <rama>
allowed-tools: Read Grep Glob Bash(git fetch *) Bash(git status *) Bash(git branch *) Bash(git log *) Bash(git rev-parse *) Bash(git rev-list *) Bash(bash .claude/skills/cambiar-rama/scripts/diff-rama.sh *)
---

# Cambiar de rama

Pasa a la rama `$ARGUMENTS` y deja el entorno de desarrollo acorde. **No corre la suite de tests, no commitea, no hace push y no
crea ramas** (para una rama nueva de una tarea, la crea el flujo de la entrega). Todo, desde la raíz del repositorio. Sin argumento,
listar las ramas recientes (`git branch --sort=-committerdate | head -n 10`) y preguntar.

## 1. Antes de cambiar

```bash
git fetch origin --prune
git status --porcelain
```

* **Con cambios sin commitear: no cambiar.** Informar los archivos y que el usuario decida (commit WIP, o descartarlos él). Nunca
  `stash`, `reset`, `checkout -- <archivo>` ni `switch --force`: se llevan el trabajo de la entrega (CLAUDE.md, Ritmo de las
  entregas).
* La rama tiene que existir, local o en `origin`. Si no existe, informar y no crear nada.

Anotar el commit de partida: `git rev-parse HEAD`.

## 2. Cambiar y traer

```bash
git switch <rama>                 # una rama solo remota se crea siguiendo a origin/<rama>
git pull --ff-only                # solo si sigue a una remota; si divergió, no forzar: informar
```

## 3. Qué cambió

```bash
bash .claude/skills/cambiar-rama/scripts/diff-rama.sh <commit de partida> HEAD
```

Una línea por tema (`IGUAL` o `CAMBIA`): `composer.lock`, `package-lock.json`, migraciones (nuevas, o borradas o editadas), seeders,
permisos (`RolesAndPermissionsSeeder`), `config/` y `.env.example` (las claves nuevas). Al final, la `ACCION` recomendada para la base.
No hace falta leer `setup.md`: la regla está en su sección "Datos de demostración y cambio de rama" y la resume la `ACCION`.

## 4. Ponerse al día (con la confirmación del usuario)

Si todo es `IGUAL`, no hay nada que hacer: ir al informe. Si no, preguntar una sola vez (AskUserQuestion, con selección múltiple)
qué se aplica, marcando la recomendada:

1. **Dependencias**: `cd backend && composer install`; `cd frontend && npm ci` (en segundo plano, con la salida a un archivo).
2. **Base de desarrollo (`syncvet`)**, desde `backend/`:
   * Migrar (conserva los datos): `php artisan migrate` y `php artisan permissions:sync`. Alcanza si solo hay migraciones nuevas.
   * Recrear: `php artisan migrate:fresh --seed`, `php artisan db:seed --class=DemoSeeder` y `php artisan demo:agenda`. **Borra
     todos los datos**: solo con un sí explícito. Es la recomendada si hay migraciones borradas o editadas, o al volver a una rama
     más vieja (la base tiene tablas que esa rama no conoce).
   * Solo permisos: `php artisan permissions:sync`, si cambiaron los permisos sin migraciones.
3. **Cachés**: `php artisan optimize:clear`, si cambió `config/` o `.env.example`.

Las claves nuevas de `.env.example` se informan para que el usuario las copie a su `.env`: **el `.env` no se lee ni se edita**.
La base de Playwright (`syncvet_e2e`) no se toca: su preparación la recrea sola si cambian las migraciones.

Si un comando falla, mostrar el error y detenerse; no probar alternativas destructivas.

## 5. Informe

Lista simple, en español (voseo):

```text
Rama          De <origen> a <destino>, commits traídos con el pull
Cambios       Los temas CAMBIA del script
Aplicado      Lo que se corrió
Pendiente     Claves del .env y lo que el usuario decidió no aplicar
```
