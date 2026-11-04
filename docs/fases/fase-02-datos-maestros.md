# FASE 2 — Datos maestros

Etapa: Base. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Todo lo que las demás fases necesitan configurar.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 2.1 | Usuarios: alta, roles, sucursales, sucursal actual | PENDIENTE |
| 2.2 | Sucursales y depósitos | PENDIENTE |
| 2.3 | Veterinarios (`VET-`) | PENDIENTE |
| 2.4 | Catálogo de servicios (`SRV-`) | PENDIENTE |
| 2.5 | Configuración de la clínica en `system_settings` y logo | PENDIENTE |
| 2.6 | Consulta de auditoría | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | ¿Un veterinario trabaja en varias sucursales? | | |
| 2 | ¿Hay precios distintos por sucursal? | | |
| 3 | ¿Qué datos de la clínica salen en recetas y facturas? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

Un administrador deja la clínica lista para operar sin tocar la base.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 2 según docs/fases/fase-02-datos-maestros.md (diseño aprobado).

- Usuarios: CRUD, asignación de roles y de sucursales, sucursal actual (PATCH me/current-branch). Contraseña de 12+ caracteres con mayúsculas, minúsculas, números, símbolos y uncompromised().
- Sucursales y depósitos: CRUD; no se desactiva la sucursal por defecto.
- Veterinarios: CRUD, código VET- por DocumentSequenceService, uno por usuario; en uso no se elimina (se inactiva).
- Servicios: CRUD, código SRV-; un servicio usado no cambia de nombre.
- Configuración de la clínica en system_settings y logo (GET público para el login).
- Auditoría: GET audit-logs con filtros (solo administrador).

Sigue CLAUDE.md. Tests por cada regla y por rol. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/5-cierre.md`](../prompts/5-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
