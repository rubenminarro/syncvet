import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import { apiLength, trimLikeApi } from './text';

// El recorte de los textos como el de la API (TrimStrings con Str::trim, revisión de la 3.5): los espacios de Unicode y
// los invisibles de Laravel, contra la lista de su código.

describe('trimLikeApi', () => {
  it('recorta los espacios y los invisibles de los extremos, no los del medio', () => {
    expect(trimLikeApi('  abc\t\n')).toBe('abc');
    expect(trimLikeApi(' abc　')).toBe('abc');
    // Los que String.prototype.trim deja y Laravel recorta.
    expect(trimLikeApi('​ab‎')).toBe('ab');
    expect(trimLikeApi('ㅤab\u0085')).toBe('ab');
    expect(trimLikeApi('\u{E0020}ab\u{1D173}')).toBe('ab');
    expect(trimLikeApi('a​b c')).toBe('a​b c');
  });

  it('cada carácter de Str::INVISIBLE_CHARACTERS (vendor de Laravel) se recorta', () => {
    const str = readFileSync(
      new URL(
        '../../../../backend/vendor/laravel/framework/src/Illuminate/Support/Str.php',
        import.meta.url,
      ),
      'utf8',
    );
    const list = /INVISIBLE_CHARACTERS = '([^']+)'/.exec(str)?.[1] ?? '';
    const codes = [...list.matchAll(/\\x\{([0-9A-F]+)\}/gi)].map(([, hex = '']) =>
      Number.parseInt(hex, 16),
    );

    expect(codes.length).toBeGreaterThan(50);

    for (const code of codes) {
      expect(trimLikeApi(`${String.fromCodePoint(code)}a`), code.toString(16)).toBe('a');
    }
  });

  it('apiLength cuenta puntos de código, como mb_strlen', () => {
    expect(apiLength('😀😀😀')).toBe(3);
    expect(apiLength('​ab')).toBe(2);
    expect(apiLength('ñandú')).toBe(5);
  });
});
