---
name: validar-docs
description: "Audita la documentación: enlaces internos, documentos huérfanos, referencias desactualizadas y cifras contra el código. Usar al revisar docs o antes de cerrar una entrega. Solo informa."
allowed-tools: Read Grep Glob Bash(php .claude/skills/validar-docs/scripts/check-docs.php*) Bash(git log *) Bash(git rev-parse *)
---

# Validar la documentación

Auditar e informar. **Es de solo lectura: no modificar, crear ni borrar archivos, ni ejecutar comandos que lo hagan.** Corregir lo
encontrado es un pedido aparte del usuario.

## 1. Comprobaciones automáticas

Desde la raíz del repositorio:

```bash
php .claude/skills/validar-docs/scripts/check-docs.php --detalle
```

El script revisa enlaces y anclas (también de los `CLAUDE.md` y `README.md` de la raíz, `backend/` y `frontend/`), documentos huérfanos desde `docs/README.md`, rutas citadas que no existen (`docs/…` desde la raíz; `app/…`, `tests/…` y las demás carpetas de Laravel, desde `backend/`) y las cifras de los documentos
vivos contra el código. Código de salida 1 si hay hallazgos.

Toda cifra se compara salvo una marca explícita en su misma cláusula: datos históricos, ejemplos o diferencias. La cifra
de una clase de test se valida contra esa clase. **Revisar la lista de excluidas de `--detalle`:** una exclusión equivocada es un hallazgo.

Las cifras de los dos proyectos se cuentan y se validan por separado (el resumen dice "Backend:" y "Frontend:"):

| Proyecto | Qué cifras | Contra qué |
|---|---|---|
| Backend | tests, rutas, controllers, services, migraciones y permisos; `` `LoginTest` (9 tests) `` | Los `test_` de `backend/tests`, `route:list`, las carpetas de `backend/app` y el seeder de permisos |
| Frontend | "N tests" en una cláusula que nombra al frontend (o a Vitest) sin nombrar al backend, o en un documento de `frontend/` | La última corrida registrada en "Resultado" de una fase de `docs/03-plan-desarrollo/fases/frontend/`, con la línea de formato fijo ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.`` (la de fecha más reciente). Los tests no se cuentan en el código: `.each` y los bucles generan casos |

Una cifra del frontend que no es de tests ("5 rutas del frontend"), la de un archivo de test (`` `client.test.ts` (12 tests) ``) y las líneas de las corridas registradas no se comparan: se listan como excluidas. Si la cantidad de archivos de test de `frontend/src` no coincide con la de la última corrida, o no hay ninguna corrida registrada, se informa: hay que registrar la corrida nueva.

## 2. Revisión de consistencia

Lo que un script no puede juzgar. Comparar según las fuentes de verdad de `docs/README.md`:

* `docs/03-plan-desarrollo/handoff.md`: que el punto de continuidad exista en Git y que **Próximas tareas** coincida con el estado de `docs/03-plan-desarrollo/README.md`.
* `docs/03-plan-desarrollo/modulos.md` y `docs/03-plan-desarrollo/README.md`: que no se contradigan, y que lo marcado `COMPLETADO` o `PENDIENTE` coincida con el
  código. Buscar en rutas, modelos y tests; no fiarse de otro documento.
* `backend/CLAUDE.md`, sección 7, contra `docs/03-plan-desarrollo/deuda-tecnica.md`: las mismas deudas abiertas.

`docs/01-arquitectura/decisiones/registro.md` es registro histórico: su contenido viejo no es un hallazgo, salvo que un documento vivo se apoye en él. La
excepción son los enlaces, que sí deben funcionar.

## 3. Informe

Agrupar por gravedad e indicar archivo y línea.

| Gravedad | Qué incluye |
|---|---|
| Error | Enlaces rotos, documentos huérfanos, rutas inexistentes, cifras que no coinciden |
| Contradicción | Dos documentos vivos dicen cosas distintas, o un documento contradice al código |
| Aviso | Datos sospechosos que no se pudieron confirmar |

Si no hay hallazgos, decirlo y mostrar lo que se revisó. Indicar siempre lo que no se pudo comprobar.
