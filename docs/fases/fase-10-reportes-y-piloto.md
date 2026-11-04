# FASE 10 — Reportes y piloto

Etapa: Lanzamiento. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Dejar el MVP listo para que una clínica real lo use.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 10.1 | Reportes clínicos, de ventas, de caja y de inventario por sucursal | PENDIENTE |
| 10.2 | Dashboard | PENDIENTE |
| 10.3 | Datos de ejemplo por escenarios | PENDIENTE |
| 10.4 | Límite de intentos en el login y revisión de seguridad | PENDIENTE |
| 10.5 | Despliegue y respaldos (D7) | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D7: ¿hosting y respaldos? | | |
| 2 | ¿Qué reportes necesita la clínica piloto el primer mes? | | |
| 3 | ¿Quién da soporte? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

La clínica piloto opera una semana completa sin tocar la base.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 10 según docs/fases/fase-10-reportes-y-piloto.md (diseño aprobado).

- Reportes con filtros por fecha y sucursal: atenciones por veterinario, vacunas aplicadas, ventas por servicio y producto, caja por forma de pago, stock bajo mínimo y por vencer. Exportación CSV.
- Dashboard: turnos del día, consultas abiertas, cobros del día, avisos fallidos.
- Seeders de ejemplo por escenarios, por los Services, idempotentes.
- Rate limit en el login. Revisión de seguridad: archivos privados, cifrado de credenciales, CORS.
- docs/development/deployment.md con el despliegue y los respaldos según D7.

Sigue CLAUDE.md. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
