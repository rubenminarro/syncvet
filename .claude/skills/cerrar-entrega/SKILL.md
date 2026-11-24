---
name: cerrar-entrega
description: Actualiza la documentación de una entrega ya validada, según las reglas de docs/README.md, y la commitea y la sube.
disable-model-invocation: true
argument-hint: "[nombre de la entrega]"
allowed-tools: Read Grep Glob Bash(git status *) Bash(git status) Bash(git log *) Bash(git diff *) Bash(git branch --show-current) Bash(git add *) Bash(git commit *) Bash(git push) Bash(git push -u origin *) Bash(git rev-parse *) Edit Write Bash(php .claude/skills/validar-docs/scripts/check-docs.php*)
---

# Cerrar una entrega

Entrega a cerrar: $ARGUMENTS

Registrar en la documentación lo que realmente se hizo. **Solo documenta: no toca código, no corrige lo que encuentre y no hace
merge. Termina siempre con el commit y el push del cierre** (sección 6): invocar esta Skill es el pedido del usuario de commitear y
subir el cierre (`CLAUDE.md` de la raíz, §2, Git). Un cierre sin commitear se pierde al cambiar de PC o de rama: el de la 3.4 del
frontend quedó así hasta la sesión siguiente (desvío 1 de la 3.5).

## 1. Comprobar que la entrega esté validada

Antes de editar, confirmar cada punto. Si alguno falta, **detenerse e informar qué falta**.

* Se está en una rama de trabajo, no en `main`.
* La suite completa pasó sobre el código actual, en esta sesión o según un resultado que el usuario haya indicado. No suponerlo ni
  deducirlo de los commits. Según lo que toque la entrega (`git diff --stat origin/main...HEAD`):
  * **Backend:** `composer test` desde `backend/` (`backend/CLAUDE.md`).
  * **Frontend:** desde `frontend/`, `npm run typecheck`, `npm run lint`, `npm test` y `npm run build`, sin errores ni avisos
    (`frontend/CLAUDE.md` §2).
  * Una entrega de un proyecto confirma además que la suite del otro no cambió, si tocó algo común (la raíz, `docs/`, las skills).
* El usuario confirmó que la entrega está validada, si requiere revisión manual.
* **En un cierre de fase:** Newman pasó con **todas** las colecciones de `docs/05-pruebas/postman`, no solo las de la fase (`CLAUDE.md`
  de la raíz, §2, Cierre de fase), o cada falla está corregida o registrada como deuda. Informar la cifra de cada colección.

Sin entrega indicada en los argumentos, preguntar cuál es.

## 2. Reunir los hechos

Leer `docs/README.md`, secciones **Fuente de verdad de cada tema** y **Mantenimiento**, y el `CLAUDE.md` de la raíz, sección 2, **Documentación por
cambio**. Esas reglas mandan: esta Skill no las repite.

Con `git log` y `git diff origin/main...HEAD`, y con los datos de la conversación, listar qué cambió, qué se decidió y quién lo decidió.

## 3. Definir qué actualizar

Armar la lista de documentos a actualizar y el motivo de cada uno.

**Si no se puede determinar con certeza qué documentación corresponde, pedir confirmación al usuario antes de editar.** Por ejemplo: un
cambio que no encaja en la tabla de Mantenimiento, dudas sobre si algo es una decisión o una deuda, o reglas que parecen contradecirse.
Mostrar la lista propuesta y qué es lo dudoso. No resolverlo por cuenta propia.

## 4. Actualizar

| Regla | Detalle |
|---|---|
| No inventar | Ningún estado, fase, decisión, fecha ni resultado que no esté respaldado por el código, los tests o el usuario |
| Sin certeza sobre un dato | Usar `PENDIENTE DE VERIFICACIÓN`, o preguntar |
| Qué leer | Solo lo de la entrega: el handoff (corto), las últimas líneas del índice del registro (`docs/01-arquitectura/decisiones/registro.md`) y la sección de la entrega en el diseño de su fase, con su "Pendiente para el cierre". Ni el historial del handoff ni los resultados de otras entregas |
| Numeración | Mantener las fases. La sección nueva toma el número siguiente a la última `R-NNN` del índice del registro (correlativo, no es el número de la entrega) |
| Decisión nueva | Su sección `# R-NNN Título (AAAA-MM-DD)` va al final del archivo de su rango en `docs/01-arquitectura/decisiones/registro/`, de 50 en 50: el que cita la última línea del índice (hoy `R-101-150.md`); al pasar de rango (R-151), el archivo siguiente se crea con la misma cabecera. Su línea va al final de la tabla del índice: ``| [R-NNN](registro/R-101-150.md#ancla) | AAAA-MM-DD | Título |``, con el ancla del título (`/validar-docs` comprueba número, archivo, ancla, fecha y título) |
| Resultado | El de la entrega va en el `…-resultados.md` de su fase (`fases/frontend/f3-resultados.md`, `fases/backend/fase-03-resultados.md`), no en el diseño |
| Agregar al final | El registro y los `…-resultados.md` pesan hasta 190 KB: **nunca leerlos enteros**. Para agregar una sección, leer solo las últimas ~40 líneas (`Read` con `offset`; el total, con `Grep -c ''`) y editar desde ahí. Para tocar una sección concreta, ubicarla con `Grep -n` y leer solo ese tramo |
| Handoff | Se **reemplazan** el punto de continuidad y las próximas tareas con el estado nuevo, en líneas cortas: no se le agrega historial ("Antes, …"). Lo que cerró la entrega queda en su resultado y en su R |
| Cifras | Tests, rutas y commits salen de lo verificado, nunca de memoria |
| Una fuente | Actualizar donde vive cada dato y enlazar desde el resto |
| Historial | El registro solo crece: no se corrigen secciones viejas |
| `backend/CLAUDE.md` y `frontend/CLAUDE.md` | La sección 1 (**Estado actual**) del proyecto de la entrega se **reemplaza** en cada cierre con el estado de la fase y de la entrega, en pocas líneas y sin historial; la del otro, solo si la entrega lo cambió (una entrega del backend pedida por el frontend, por ejemplo). El resto, solo si cambió una regla, y solo esa regla: las reglas de negocio, en su archivo de `docs/02-requisitos/reglas/`; el detalle de una convención del frontend, en `docs/04-desarrollo/frontend-convenciones.md`. Las cifras de tests de cada uno salen de su suite: las del frontend, de la corrida de `npm test` del cierre, que se registra en "Resultado" del `fN-resultados.md` de la fase con la línea ``Corrida de `npm test` del frontend (AAAA-MM-DD): N tests en M archivos, en verde.`` (de ahí la toma `/validar-docs`) |
| Roadmap | Solo cambia el estado de lo entregado. Sin fases nuevas sin aprobación |
| Tabla de entregas del diseño | La fila de la entrega en "Entregas" del diseño de su fase pasa a `COMPLETADO (R-NNN, [resultado](…))`, con la rama real (`git branch --show-current`); la siguiente, a la que corresponda. Su "Pendiente para el cierre" queda vacío con el enlace al resultado (en la 3.3 y la 3.4 del frontend la tabla quedó sin actualizar) |

## 5. Verificar

Desde la raíz del repositorio:

```bash
php .claude/skills/validar-docs/scripts/check-docs.php
```

Corregir los enlaces rotos que hayan causado estos cambios. Informar los demás hallazgos sin tocarlos.

## 6. Commit y push (siempre)

La Skill no termina sin este paso. Desde la raíz, en la rama de la entrega (nunca en `main`):

1. `git status --short`: solo tienen que aparecer los documentos del cierre. Si hay código u otros cambios sin commitear que no son
   del cierre, **detenerse e informar** (no se mezclan en el commit del cierre ni se descartan).
2. `git add` de esos documentos, por nombre (no `git add -A`), y un commit en español que diga qué cierra y por qué:
   `Cierra la entrega <entrega> del <proyecto>: <tema> (R-NNN)`, con las líneas de atribución que pida la sesión.
3. `git push` (con `-u origin <rama>` si la rama todavía no está en el remoto).
4. Comprobar: `git status` sin cambios ("nothing to commit, working tree clean") y `git rev-parse HEAD` igual a
   `git rev-parse @{u}` (el remoto tiene el commit).

Si el commit o el push fallan (un hook, la red, el remoto rechaza), no se da el cierre por terminado: se informa el error tal cual y
qué falta. El merge a `main` no es parte de la Skill: lo autoriza el usuario al cerrar la fase.

## 7. Informe

Mostrar el estado de los dos proyectos después del cierre (backend y frontend: fase, entrega y cifra de tests), los archivos
modificados y qué cambió en cada uno, la evidencia usada, lo que quedó como `PENDIENTE DE VERIFICACIÓN` y el resultado de la
verificación. **Termina siempre con la línea** "git status limpio, commit y push confirmados: `<hash>` en `origin/<rama>`." (o, si
el paso 6 falló, con el error y lo que falta, sin esa línea).
