/**
 * Los caracteres que Laravel recorta de los extremos de cada texto que llega (`TrimStrings`, con `Str::trim`): el `\s` de
 * PCRE con `/u` (los espacios de Unicode, también U+0085), `" \n\r\t\v\0"` y `Str::INVISIBLE_CHARACTERS` (los invisibles,
 * como U+200B o U+3164). `String.prototype.trim` deja algunos de esos: un texto que parecía de 3 caracteres llegaba con 2
 * (revisión de la 3.5). La lista de Laravel, en `vendor/laravel/framework/src/Illuminate/Support/Str.php`. Un conjunto, no
 * una clase de regex: varios son caracteres combinantes (U+034F, U+200D) y en una clase se mezclarían con el anterior.
 */
const INVISIBLE = new Set<number>([
  0x0000, 0x0085, 0x00a0, 0x00ad, 0x034f, 0x061c, 0x115f, 0x1160, 0x17b4, 0x17b5, 0x180e, 0x2000,
  0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200a, 0x200b, 0x200c,
  0x200d, 0x200e, 0x200f, 0x202f, 0x205f, 0x2060, 0x2061, 0x2062, 0x2063, 0x2064, 0x2065, 0x206a,
  0x206b, 0x206c, 0x206d, 0x206e, 0x206f, 0x3000, 0x2800, 0x3164, 0xfeff, 0xffa0, 0x1d159, 0x1d173,
  0x1d174, 0x1d175, 0x1d176, 0x1d177, 0x1d178, 0x1d179, 0x1d17a, 0xe0020,
]);

const SPACE = /^\s$/u;

function trimmed(character: string): boolean {
  return SPACE.test(character) || INVISIBLE.has(character.codePointAt(0) ?? -1);
}

/** El texto como lo guarda la API: sin los espacios ni los invisibles de los extremos (`Str::trim`). */
export function trimLikeApi(value: string): string {
  const characters = Array.from(value);
  let start = 0;
  let end = characters.length;

  while (start < end && trimmed(characters[start] ?? '')) {
    start += 1;
  }

  while (end > start && trimmed(characters[end - 1] ?? '')) {
    end -= 1;
  }

  return characters.slice(start, end).join('');
}

/** El largo como lo cuenta la API (`mb_strlen`, en puntos de código, no en unidades UTF-16), después de recortar. */
export function apiLength(value: string): number {
  return Array.from(trimLikeApi(value)).length;
}
