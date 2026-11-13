/**
 * El dígito verificador del RUC paraguayo (módulo 11 de la SET), la misma cuenta que `ClinicService::rucCheckDigit` del
 * backend: de derecha a izquierda, cada dígito por un factor de 2 a 11 (después de 11 vuelve a 2); el resto de la suma
 * dividida por 11 da el dígito (11 − resto, o 0 si el resto es 0 o 1).
 */
export function rucCheckDigit(ruc: string): number {
  let total = 0;
  let factor = 2;

  for (const digit of Array.from(ruc).reverse()) {
    if (factor > 11) {
      factor = 2;
    }

    total += Number(digit) * factor;
    factor++;
  }

  const remainder = total % 11;

  return remainder > 1 ? 11 - remainder : 0;
}

/** El mensaje de la API (`RucCheckDigit::MESSAGE`). */
export const RUC_MESSAGE = 'El RUC no es válido: revisá el número y el dígito verificador.';

/** La forma de un RUC, "número-dígito" (NNNNNNN-D). */
const RUC_SHAPE = /^(\d+)-(\d)$/;

/**
 * ¿El documento pasa la regla del dígito verificador? Como `App\Rules\RucCheckDigit`: solo se revisa un documento con la
 * forma de un RUC; una cédula sin guion o un documento con letras no tiene dígito que revisar (pasa).
 */
export function hasValidRucCheckDigit(document: string): boolean {
  const parts = RUC_SHAPE.exec(document);

  if (parts === null) {
    return true;
  }

  return rucCheckDigit(parts[1] ?? '') === Number(parts[2]);
}
