# FASE 8 — Caja y facturación

Etapa: Cobro. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Cobrar lo atendido: factura desde la consulta, pagos totales o parciales y cierre de caja por usuario.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 8.1 | Timbrados por sucursal | PENDIENTE |
| 8.2 | Factura en borrador desde ítems pendientes o venta directa | PENDIENTE |
| 8.3 | Emisión con numeración y anulación | PENDIENTE |
| 8.4 | Caja diaria: apertura, movimientos y cierre | PENDIENTE |
| 8.5 | Pagos totales o parciales y saldo | PENDIENTE |
| 8.6 | Recibos de facturas a crédito | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | ¿Se puede facturar a un tercero (otro RUC)? | | |
| 2 | ¿Se factura con la consulta abierta? | | |
| 3 | ¿Varias consultas en una factura? | | |
| 4 | ¿Un usuario puede tener dos cajas abiertas? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

Dos emisiones simultáneas nunca reciben el mismo número; el cierre de caja coincide con la suma de pagos por forma de pago.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 8 según docs/fases/fase-08-caja-y-facturacion.md (diseño aprobado).

- InvoiceStampService: CRUD de timbrados por sucursal; vigencia; no se edita uno usado.
- InvoiceService: borrador desde consultation_items pendientes (pasan a invoiced al emitir, vuelven a pending al anular) o venta directa; totales exento/5/10 e IVA con BCMath (precios con IVA incluido); emitir asigna número con lockForUpdate sobre invoice_stamps; emitida es inmutable; anular con motivo.
- CashRegisterService: abrir, movimientos, cerrar con monto declarado, esperado y diferencia; una caja abierta por usuario.
- PaymentService: pago total o parcial dentro de la caja abierta; actualiza balance y payment_status; anular con motivo; recibo numerado en facturas a crédito.
- Orden de bloqueo: factura → pago; caja → pago. Comprobantes imprimibles en PDF.

Sigue CLAUDE.md. Incluye tests de concurrencia de numeración. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/7-cierre.md`](../prompts/7-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
