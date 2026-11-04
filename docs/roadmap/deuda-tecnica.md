# Deuda técnica

Deudas y discrepancias abiertas, numeradas. La misma lista resumida está en `CLAUDE.md`, sección 7. Una deuda resuelta pasa a
"Resueltas" con la sección del registro que la cerró.

## Abiertas

| # | Deuda | Origen | Resolver en |
|---|---|---|---|
| 1 | El módulo de clientes y pacientes no tiene tests de PHPUnit; los Form Requests y las rutas no se probaron | Registro 0.1 | FASE 1 |
| 2 | Los modelos fuera de clientes y pacientes se generaron desde el esquema: revisar relaciones y casts al usarlos | Registro 0.1 | Fase de cada módulo |
| 3 | Códigos SIFEN de `PaymentMethod::sifenCode()` e `invoice_items.unit_of_measure` sin verificar contra el Manual Técnico | Registro 0.1 | FASE 9 |
| 4 | Sin límite de intentos en el login | Diseño | FASE 10 |

## Resueltas

Ninguna todavía.
