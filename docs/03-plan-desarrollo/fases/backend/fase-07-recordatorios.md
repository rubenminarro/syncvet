# FASE 7 — Recordatorios y avisos

Etapa: Comunicación. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

El tutor recibe por WhatsApp o email el recordatorio de la próxima vacuna, el control y la confirmación del turno.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 7.1 | Recordatorios desde aplicaciones con próxima dosis y desde turnos | PENDIENTE |
| 7.2 | Envío por cola y scheduler | PENDIENTE |
| 7.3 | WhatsApp Cloud API con plantillas aprobadas | PENDIENTE |
| 7.4 | Email | PENDIENTE |
| 7.5 | Reintentos y reenvío manual | PENDIENTE |
| 7.6 | Webhook de estado | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D5: ¿proveedor de WhatsApp? | | |
| 2 | ¿Cuántos días antes se avisa? | | |
| 3 | ¿A qué hora salen los avisos? | | |
| 4 | ¿Qué pasa si el tutor no tiene WhatsApp? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

Un aviso no se repite (`dedupe_key`); sin worker ni scheduler los avisos quedan `pending` y no se pierden.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 7 según docs/fases/fase-07-recordatorios.md (diseño aprobado).

- ReminderService: crear recordatorios al registrar una aplicación con next_dose_due_at y al confirmar un turno; cancelarlos si se anula el origen; marcarlos cumplidos cuando se aplica la dosis.
- Comando programado (cada minuto, hora de Paraguay) que pasa los recordatorios vencidos a client_notifications y los despacha a las colas whatsapp o mail.
- Canal WhatsApp Cloud API detrás de una interfaz (WhatsAppGateway), simulable con Http::fake. Credenciales cifradas.
- Webhook POST webhooks/whatsapp con verificación de firma de Meta.
- dedupe_key, reintentos con límite y reenvío manual.
- Respeta notifications_enabled del cliente: si está desactivado, el aviso queda skipped con el motivo.

Sigue CLAUDE.md. Los tests no usan red. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
