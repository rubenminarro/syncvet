# FASE 1 — Clientes y pacientes

Etapa: Base. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Cerrar con tests el módulo de ejemplo que trae el paquete y sumar la administración de especies y razas.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 1.1 | Tests de cada regla de `CLAUDE.md` 6.1 (clientes) | PENDIENTE |
| 1.2 | Tests de cada regla de `CLAUDE.md` 6.2 (pacientes y cambio de dueño) | PENDIENTE |
| 1.3 | ABM de especies y razas, con baja lógica si están en uso | PENDIENTE |
| 1.4 | Foto del paciente en disco privado | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | ¿Un paciente puede tener más de un tutor? | | |
| 2 | ¿Qué campos personalizados quieren las clínicas? | | |
| 3 | ¿Se valida el dígito verificador del RUC? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

Cada regla de `CLAUDE.md` 6.1 y 6.2 tiene su test; probado con el rol recepcionista.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 1 según docs/fases/fase-01-clientes-y-pacientes.md (diseño aprobado).

- El código de clientes y pacientes ya existe: no lo reescribas. Agrega los tests de cada regla de CLAUDE.md 6.1 y 6.2 (tipo persona/empresa, baja con uso 409, liberación de únicos, raza de otra especie, cambio de dueño, fallecido, búsqueda).
- ABM de especies y razas (species.store, species.update) con baja lógica si están en uso.
- Foto del paciente: POST/DELETE patients/{patient}/photo, disco privado, descarga con nosniff.
- Si un test encuentra un error en el código existente, corrígelo y anótalo en el Resultado de la fase.

Sigue CLAUDE.md. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
