# FASE 4 — Historia clínica

Etapa: Atención. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

El veterinario atiende: abre la consulta desde el turno, registra examen, diagnóstico, plan, lo que usó y las vacunas aplicadas.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 4.1 | Consultas (`CON-`) desde un turno fichado o sin turno | PENDIENTE |
| 4.2 | Ítems de servicio y de producto (sin stock todavía) | PENDIENTE |
| 4.3 | Adjuntos | PENDIENTE |
| 4.4 | Aplicaciones con próxima dosis | PENDIENTE |
| 4.5 | Cierre, reapertura y anulación | PENDIENTE |
| 4.6 | Historial del paciente | PENDIENTE |

## Pendiente de la FASE 3

La relación entre "atendido" y la consulta la decide este diseño. La FASE 3 dejó una propuesta
([relación con la consulta](fase-03-agenda-y-turnos.md#relación-con-la-consulta-fase-4)) y lo pendiente en su
[cierre](fase-03-agenda-y-turnos.md#cierre-de-la-fase-3) (R-056): la consulta se abre desde un turno fichado y lo marca atendido en la
misma transacción; `POST appointments/{appointment}/attend` sigue para los turnos sin historia clínica; la consulta sin turno sigue
siendo posible.

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D4: ¿qué se edita en una consulta cerrada? ¿Quién reabre? | | |
| 2 | ¿Se permite consulta sin turno (urgencia)? | | |
| 3 | ¿Qué campos del examen son obligatorios? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

El flujo turno fichado → consulta → cierre funciona de punta a punta; una consulta cerrada no se edita.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 4 según docs/fases/fase-04-historia-clinica.md (diseño aprobado).

- ConsultationService: abrir desde un turno fichado (pasa el turno a atendido) o sin turno; copia client_id del dueño actual; código CON-.
- Editar solo abierta; cerrar (closed_by, closed_at); reabrir según D4 con permiso consultations.reopen; anular con motivo.
- Ítems: servicio (precio del catálogo si no se envía) o producto; totales con BCMath; solo con la consulta abierta. Todavía no descuentan stock (FASE 5).
- Adjuntos en disco privado. Aplicaciones (vacuna, desparasitación, medicamento) con lote copiado y next_dose_due_at.
- GET patients/{patient}/history: consultas, aplicaciones y documentos en orden.

Sigue CLAUDE.md. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
