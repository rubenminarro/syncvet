# FASE 3 — Agenda y turnos

Etapa: Atención. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

La recepción da turnos sin superposiciones y el veterinario ve su cola del día.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 3.1 | Agendas con horario semanal y bloqueos | PENDIENTE |
| 3.2 | Turnos únicos y recurrentes | PENDIENTE |
| 3.3 | Estados: a confirmar → confirmado → fichado → atendido, ausente o cancelado | PENDIENTE |
| 3.4 | Disponibilidad por agenda | PENDIENTE |
| 3.5 | Cola del día por veterinario | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D3: ¿cómo se generan los turnos recurrentes? | | |
| 2 | ¿Se permite sobreturno? | | |
| 3 | ¿Con cuánta anticipación se cancela? | | |
| 4 | ¿Se da turno sin paciente (primera vez)? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

Dos turnos simultáneos en el mismo horario: uno entra y el otro recibe 409; tests de cada transición.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 3 según docs/fases/fase-03-agenda-y-turnos.md (diseño aprobado).

- AppointmentService: alta, edición, mover, confirmar, fichar, marcar ausente, cancelar (motivo obligatorio). Transiciones con AppointmentStatus; inválida → 422 con error_code propio.
- Sin superposición en la misma agenda: dentro de DB::transaction, lockForUpdate sobre la fila de la agenda y luego verificar el rango. Conflicto → 409 APPOINTMENT_OVERLAP.
- Respetar horario semanal (schedule_hours) y bloqueos (schedule_blocks), salvo sobreturno si D3 lo permite.
- Recurrencia según D3, agrupada por series_id; cancelar uno o toda la serie.
- GET schedules/{schedule}/availability?date= y GET appointments/today (cola del día por veterinario).
- Fechas: la base guarda hora de Paraguay y la API responde en UTC.

Sigue CLAUDE.md. Incluye un test de concurrencia. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/5-cierre.md`](../prompts/5-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
