# Reglas de negocio: pendientes de diseño

Reglas vigentes del backend (antes, `backend/CLAUDE.md` §6; el índice, en su
[§6](../../../backend/CLAUDE.md#6-reglas-de-negocio-vigentes)). Las referencias `§N` son de
[`backend/CLAUDE.md`](../../../backend/CLAUDE.md); las `§6.N`, de los archivos de esta carpeta. Si una regla contradice al
código, manda el código.

### 6.11 Pendientes de diseño (reglas a definir antes de programar)

* **Consultas:** qué se edita después de cerrar; quién puede reabrir.
* **Stock:** el consumo en consulta descuenta del depósito de la sucursal; lote obligatorio si `tracks_lots`.
* **Facturación:** emisión con timbrado (`invoice_stamps.next_number` con `lockForUpdate`), envío a SIFEN por cola,
  cancelación como evento SIFEN.
* **Caja:** una caja abierta por usuario; el pago en efectivo exige caja abierta.
