# FASE 5 — Inventario

Etapa: Atención. Estado: **PENDIENTE**. El estado vigente de cada fase está en [`docs/roadmap/README.md`](../roadmap/README.md).

## Objetivo

Lo que se usa en una consulta descuenta stock, con lotes y aviso de vencimiento.

## Alcance inicial

El diseño (paso 2) completa cada entrega con reglas de negocio, datos, endpoints, errores y tests.

| Entrega | Contenido | Estado |
|---|---|---|
| 5.1 | Productos (`PRO-`) | PENDIENTE |
| 5.2 | Stock por depósito y lotes | PENDIENTE |
| 5.3 | Kardex inmutable con saldo y costo promedio | PENDIENTE |
| 5.4 | Entradas, ajustes y bajas por vencimiento | PENDIENTE |
| 5.5 | Consumo desde la consulta y devolución al anular | PENDIENTE |
| 5.6 | Avisos de stock mínimo y vencimiento a 30 días | PENDIENTE |

## Preguntas de negocio

Las respuestas se anotan con la fecha y pasan a [`docs/decisions/registro.md`](../decisions/registro.md).

| # | Pregunta | Respuesta | Fecha |
|---|---|---|---|
| 1 | ¿Se permite stock negativo? | | |
| 2 | ¿Qué lote se usa por defecto (el que vence primero)? | | |
| 3 | ¿Hace falta registrar compras ya o alcanza con entradas? | | |

## Diseño

PENDIENTE: lo completa el paso 2 ([`docs/prompts/2-diseno.md`](../prompts/2-diseno.md)), aplicando la regla de oro de
[`docs/project/principios.md`](../project/principios.md).

## Criterio de cierre

El saldo del kardex coincide con `product_stocks` después de cualquier secuencia de movimientos.

## Prompt de implementación (paso 4)

Usar solo con el diseño aprobado.

```text
Implementa la FASE 5 según docs/fases/fase-05-inventario.md (diseño aprobado).

- InventoryService: entrada, ajuste, consumo, devolución y baja por vencimiento. Cada uno crea un inventory_movement con balance_after y average_cost_after, y actualiza product_stocks y product_lots en la misma transacción.
- Orden de bloqueo: consulta → ítem → product_stocks → product_lots.
- Agregar un ítem de producto a la consulta consume stock del depósito de la sucursal; quitarlo o anular la consulta lo devuelve. Lote obligatorio si tracks_lots.
- Cantidades y costos con BCMath.
- Reportes: stock por depósito, bajo mínimo y lotes que vencen en 30 días.

Sigue CLAUDE.md. Incluye un test que compare kardex contra saldo. Corre la suite e infórmame. No hagas commit.
```

## Resultado

PENDIENTE: lo completa el cierre ([`docs/prompts/5-cierre.md`](../prompts/5-cierre.md)): archivos, endpoints, tests y aserciones,
desvíos del diseño y por qué.
