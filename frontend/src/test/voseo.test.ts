// @vitest-environment node
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative } from 'node:path';
import ts from 'typescript';
import { describe, expect, it } from 'vitest';

/**
 * Textos de la interfaz en español y con voseo (como VoseoTest del backend, con sus mismas listas). Revisa las cadenas y el
 * texto de JSX de src/ (los que tienen un espacio, que son los que llegan al usuario; no los comentarios, los identificadores
 * ni las clases de CSS) y busca:
 *
 * - Palabras que solo son de tú o de usted ("tienes", "usted", "asegúrese"), en cualquier lugar.
 * - Imperativos de tú y de usted de los verbos de VERBS ("Ingresa", "registre"), solo al comienzo de una oración o de una
 *   cláusula, o después de "por favor", "no" o "que": en otro lugar son la tercera persona y no se marcan.
 * - Textos en inglés: palabras frecuentes del inglés que no existen en español.
 *
 * Los archivos de test quedan afuera (este, a propósito, tiene ejemplos con tú y con usted). Un verbo nuevo en un texto se
 * agrega a VERBS como infinitivo; uno irregular, a IRREGULAR_VERBS.
 */

const TU_OR_USTED_WORDS = [
  'tú',
  'ti',
  'contigo',
  'usted',
  'tienes',
  'puedes',
  'debes',
  'quieres',
  'eres',
  'has',
  'sabes',
  'necesitas',
  'estés',
  'seas',
  'asegúrate',
  'asegúrese',
  'comunícate',
  'comuníquese',
  'fíjate',
  'fíjese',
  'contáctanos',
  'contáctenos',
  'acuérdate',
  'acuérdese',
  'inténtalo',
  'inténtelo',
];

const VERBS = [
  'ingresar',
  'registrar',
  'revisar',
  'intentar',
  'reintentar',
  'seleccionar',
  'contactar',
  'verificar',
  'usar',
  'utilizar',
  'completar',
  'indicar',
  'confirmar',
  'agregar',
  'esperar',
  'adjuntar',
  'cambiar',
  'buscar',
  'guardar',
  'presionar',
  'aceptar',
  'cancelar',
  'actualizar',
  'iniciar',
  'asignar',
  'eliminar',
  'borrar',
  'editar',
  'modificar',
  'informar',
  'avisar',
  'solicitar',
  'llamar',
  'anotar',
  'pagar',
  'anular',
  'descargar',
  'especificar',
  'proporcionar',
  'rellenar',
  'llenar',
  'olvidar',
  'aguardar',
  'escribir',
  'subir',
  'abrir',
  'añadir',
  'emitir',
  'imprimir',
  'definir',
  'acudir',
  'leer',
  'responder',
  'inactivar',
  'crear',
];

const IRREGULAR_VERBS: Record<string, [string, string, string]> = {
  volver: ['vuelve', 'vuelva', 'vuelvas'],
  elegir: ['elige', 'elija', 'elijas'],
  corregir: ['corrige', 'corrija', 'corrijas'],
  escoger: ['escoge', 'escoja', 'escojas'],
  pedir: ['pide', 'pida', 'pidas'],
  repetir: ['repite', 'repita', 'repitas'],
  recordar: ['recuerda', 'recuerde', 'recuerdes'],
  comprobar: ['comprueba', 'compruebe', 'compruebes'],
  enviar: ['envía', 'envíe', 'envíes'],
  introducir: ['introduce', 'introduzca', 'introduzcas'],
  hacer: ['haz', 'haga', 'hagas'],
  poner: ['pon', 'ponga', 'pongas'],
  decir: ['di', 'diga', 'digas'],
  tener: ['ten', 'tenga', 'tengas'],
  venir: ['ven', 'venga', 'vengas'],
  ir: ['ve', 'vaya', 'vayas'],
  reducir: ['reduce', 'reduzca', 'reduzcas'],
};

const ENGLISH_WORDS = new Set([
  'the',
  'is',
  'are',
  'be',
  'been',
  'must',
  'should',
  'please',
  'your',
  'you',
  'this',
  'that',
  'with',
  'for',
  'of',
  'to',
  'and',
  'not',
  'have',
  'was',
  'were',
  'cannot',
  'could',
  'field',
  'invalid',
  'required',
  'already',
  'missing',
  'failed',
  'does',
  'an',
  'in',
  'on',
  'by',
  'from',
  'enter',
  'select',
  'try',
  'again',
]);

/** Comienzo de una oración o de una cláusula, con "por favor" opcional. */
const CLAUSE_START = '(?:^|[.:;,!?¡¿(]\\s*)(?:por\\s+favor,?\\s+)?';
const NOT_LETTER_AFTER = '(?![\\p{L}_])';

function imperatives(): [string, string, string][] {
  const forms = Object.values(IRREGULAR_VERBS);

  for (const verb of VERBS) {
    const stem = verb.slice(0, -2);

    if (verb.endsWith('ar')) {
      // Cambios de ortografía ante "e": buscar → busque, pagar → pague, utilizar → utilice.
      const subjunctive = stem.replace(/c$/, 'qu').replace(/g$/, 'gu').replace(/z$/, 'c');
      forms.push([`${stem}a`, `${subjunctive}e`, `${subjunctive}es`]);
    } else {
      forms.push([`${stem}e`, `${stem}a`, `${stem}as`]);
    }
  }

  return forms;
}

const IMPERATIVES = imperatives();

function forbiddenForms(text: string): string[] {
  const forms = new Set<string>();

  for (const word of TU_OR_USTED_WORDS) {
    if (new RegExp(`(?<![\\p{L}_])${word}${NOT_LETTER_AFTER}`, 'iu').test(text)) {
      forms.add(word);
    }
  }

  for (const [affirmativeTu, usted, negativeTu] of IMPERATIVES) {
    const patterns: [string, string][] = [
      [affirmativeTu, CLAUSE_START + affirmativeTu],
      [usted, `(?:${CLAUSE_START}(?:no\\s+)?|\\bque\\s+)${usted}`],
      [negativeTu, `\\bno\\s+${negativeTu}`],
    ];

    for (const [form, pattern] of patterns) {
      if (new RegExp(pattern + NOT_LETTER_AFTER, 'iu').test(text)) {
        forms.add(form);
      }
    }
  }

  // Sin los identificadores en mayúsculas (CLIENT_IN_USE), que no son texto en inglés.
  const prose = text.replace(/\b[A-Z][A-Z0-9]*(?:_[A-Z0-9]+)+\b/g, '');
  const words = prose.toLowerCase().split(/[^\p{L}]+/u);

  if (words.some((word) => ENGLISH_WORDS.has(word))) {
    forms.add('english');
  }

  return [...forms];
}

/**
 * Una lista de clases de CSS ("d-flex align-items-center") o una clase que se suma a otras (" required", " is-invalid"):
 * no es texto para el usuario.
 */
function isClassList(text: string): boolean {
  const tokens = text.trim().split(/\s+/);
  const classes = tokens.every((token) => /^[a-z0-9-]+$/.test(token));

  return classes && (text.startsWith(' ') || tokens.some((token) => token.includes('-')));
}

const SRC = join(import.meta.dirname, '..');

function sourceFiles(dir: string): string[] {
  return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
    const path = join(dir, entry.name);

    if (entry.isDirectory()) {
      return sourceFiles(path);
    }

    return /\.tsx?$/.test(entry.name) &&
      !/\.test\.tsx?$/.test(entry.name) &&
      entry.name !== 'routeTree.gen.ts'
      ? [path]
      : [];
  });
}

/** Las cadenas y el texto de JSX con al menos un espacio, con su línea. */
function texts(path: string): [number, string][] {
  const source = ts.createSourceFile(
    path,
    readFileSync(path, 'utf8'),
    ts.ScriptTarget.Latest,
    true,
  );
  const found: [number, string][] = [];

  const visit = (node: ts.Node): void => {
    let text: string | null = null;

    if (ts.isStringLiteral(node) || ts.isNoSubstitutionTemplateLiteral(node)) {
      text = node.text;
    } else if (ts.isTemplateHead(node) || ts.isTemplateMiddle(node) || ts.isTemplateTail(node)) {
      text = node.text;
    } else if (ts.isJsxText(node)) {
      text = node.text.replace(/\s+/g, ' ').trim();
    }

    if (text !== null && text.includes(' ') && !isClassList(text)) {
      found.push([source.getLineAndCharacterOfPosition(node.getStart()).line + 1, text]);
    }

    ts.forEachChild(node, visit);
  };

  visit(source);

  return found;
}

describe('voseo', () => {
  it('detecta tú, usted e inglés, y deja pasar el voseo y la tercera persona', () => {
    expect(forbiddenForms('No tienes permisos.')).toEqual(['tienes']);
    expect(forbiddenForms('Ingresa tu email.')).toEqual(['ingresa']);
    expect(forbiddenForms('Elija otra fecha.')).toEqual(['elija']);
    expect(forbiddenForms('Por favor, indique el motivo.')).toEqual(['indique']);
    expect(forbiddenForms('No olvides la fecha.')).toEqual(['olvides']);
    expect(forbiddenForms('Usted debe indicar su email.')).toEqual(['usted']);
    expect(forbiddenForms('Please enter your email.')).toEqual(['english']);

    expect(forbiddenForms('No tenés permisos. Ingresá tu email. Probá de nuevo.')).toEqual([]);
    expect(forbiddenForms('Elegí tu sucursal y revisalos.')).toEqual([]);
    expect(forbiddenForms('La consulta registra la fecha.')).toEqual([]);
    expect(forbiddenForms('CLIENT_IN_USE')).toEqual([]);
  });

  it('las clases de CSS no son texto', () => {
    expect(isClassList('d-flex align-items-center')).toBe(true);
    expect(isClassList(' is-invalid')).toBe(true);
    expect(isClassList(' required')).toBe(true);
    expect(isClassList('required field')).toBe(false);
    expect(isClassList('Ingresá a tu cuenta')).toBe(false);
  });

  it('los textos de src/ usan voseo', () => {
    const files = sourceFiles(SRC);
    const found: string[] = [];

    expect(files.length).toBeGreaterThan(10);

    for (const path of files) {
      for (const [line, text] of texts(path)) {
        for (const form of forbiddenForms(text)) {
          found.push(`${relative(SRC, path)}:${line} "${form}" en ${text}`);
        }
      }
    }

    expect(found, 'Textos con tú, con usted o en inglés.').toEqual([]);
  });
});
