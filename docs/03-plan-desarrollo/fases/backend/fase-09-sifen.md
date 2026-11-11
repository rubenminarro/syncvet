# FASE 9 — SIFEN

Etapa: Cobro. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/03-plan-desarrollo/README.md`](../../README.md).

## Objetivo

La factura emitida se transmite a SIFEN y queda aprobada, con su CDC, QR y KuDE.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 9.1 | Configuración de la clínica: certificado, CSC, ambiente | PENDIENTE |
| 9.2 | XML según el Manual Técnico vigente, firma y QR | PENDIENTE |
| 9.3 | Envío por cola y consulta de estado | PENDIENTE |
| 9.4 | KuDE en PDF | PENDIENTE |
| 9.5 | Eventos de cancelación e inutilización | PENDIENTE |
| 9.6 | Notas de crédito | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/01-arquitectura/decisiones/registro.md`](../../../01-arquitectura/decisiones/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | D6: ¿integración propia o proveedor? | | |
| 2 | ¿Qué hacer si SIFEN está caído? | | |
| 3 | ¿Quién cancela una factura aprobada? | | |
| 4 | ¿Notas de crédito en el MVP? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/03-plan-desarrollo/prompts/2-diseno.md`](../../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/00-vision/principios.md`](../../../00-vision/principios.md).

## Criterio de cierre

Factura aprobada en el ambiente de pruebas de la DNIT; rechazo guardado con su código y mensaje; cancelación aprobada.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 9 según docs/03-plan-desarrollo/fases/backend/fase-09-sifen.md (diseño aprobado).

- Interfaz SifenGateway con una implementación según D6 y otra falsa para tests. Ninguna clase fuera de app/Services/Sifen conoce el XML.
- Al emitir (FASE 8) la factura pasa a sifen_status queued; un job en la cola sifen genera el XML, lo firma con el certificado de la clínica, agrega el QR con el CSC y lo envía. Guarda cdc, código y mensaje de respuesta, xml_path y kude_path.
- Reintentos con límite; GET invoices/{invoice}/sifen; reenvío manual (invoices.resend-sifen).
- Eventos: cancelación de una factura aprobada e inutilización de números, en sifen_events.
- KuDE en PDF con el QR. Certificado y CSC cifrados; el .p12 en disco privado.
- Prueba real contra el ambiente de pruebas de la DNIT.

Sigue CLAUDE.md. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/03-plan-desarrollo/prompts/7-cierre.md`](../../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
