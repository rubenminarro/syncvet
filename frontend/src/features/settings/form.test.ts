import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import {
  SETTINGS_FORM_FIELDS,
  SETTINGS_MESSAGES,
  settingsFormErrors,
  settingsFormToPayload,
  settingsToForm,
  type SettingsFormValues,
} from './form';
import { SETTING_KEYS, type SystemSettings } from './types';

const VALID: SettingsFormValues = {
  name: 'Veterinaria Central',
  legal_name: 'Veterinaria Central S.A.',
  settings: {
    contact_email: 'contacto@example.com',
    contact_phone: '+595211100001',
    website: 'https://example.com',
    document_footer: 'Gracias por su visita.',
  },
};

function errorsFor(
  changes: Partial<Omit<SettingsFormValues, 'settings'>> & {
    settings?: Partial<SettingsFormValues['settings']>;
  },
) {
  return settingsFormErrors({
    ...VALID,
    ...changes,
    settings: { ...VALID.settings, ...changes.settings },
  });
}

const m = SETTINGS_MESSAGES;

describe('settingsFormErrors: las reglas de UpdateSystemSettingsRequest en su borde', () => {
  it('el formulario completo y válido no tiene errores', () => {
    expect(settingsFormErrors(VALID)).toEqual({});
  });

  it('las claves vacías pasan (la API las borra)', () => {
    expect(
      errorsFor({
        settings: { contact_email: '', contact_phone: ' ', website: '', document_footer: '' },
      }),
    ).toEqual({});
  });

  it.each([
    ['', m.name.required],
    ['   ', m.name.required],
    ['A', m.name.min],
    ['A'.repeat(101), m.name.max],
  ])('el nombre "%s" se rechaza', (name, message) => {
    expect(errorsFor({ name }).name).toBe(message);
  });

  it('el nombre de 2 y de 100 caracteres pasa; un emoji cuenta como un carácter', () => {
    expect(errorsFor({ name: 'AB' }).name).toBeUndefined();
    expect(errorsFor({ name: 'A'.repeat(100) }).name).toBeUndefined();
    expect(errorsFor({ name: `${'A'.repeat(99)}🐾` }).name).toBeUndefined();
  });

  it('la razón social: obligatoria, de 2 a 150', () => {
    expect(errorsFor({ legal_name: '' }).legal_name).toBe(m.legal_name.required);
    expect(errorsFor({ legal_name: 'A' }).legal_name).toBe(m.legal_name.min);
    expect(errorsFor({ legal_name: 'A'.repeat(150) }).legal_name).toBeUndefined();
    expect(errorsFor({ legal_name: 'A'.repeat(151) }).legal_name).toBe(m.legal_name.max);
  });

  it('el correo: la forma mínima y hasta 150 (en minúsculas)', () => {
    const at150 = `${'a'.repeat(138)}@example.com`;

    expect(at150).toHaveLength(150);
    expect(
      errorsFor({ settings: { contact_email: at150 } })['settings.contact_email'],
    ).toBeUndefined();
    expect(errorsFor({ settings: { contact_email: `a${at150}` } })['settings.contact_email']).toBe(
      m.contact_email.max,
    );
    expect(errorsFor({ settings: { contact_email: 'sin-arroba' } })['settings.contact_email']).toBe(
      m.contact_email.email,
    );
    expect(
      errorsFor({ settings: { contact_email: ' CONTACTO@Example.COM ' } })[
        'settings.contact_email'
      ],
    ).toBeUndefined();
  });

  it('el teléfono: la regla de clientes, con espacios y guiones quitados', () => {
    expect(
      errorsFor({ settings: { contact_phone: '0981 123-456' } })['settings.contact_phone'],
    ).toBe(m.contact_phone.regex);
    expect(
      errorsFor({ settings: { contact_phone: '+595 981 123-456' } })['settings.contact_phone'],
    ).toBeUndefined();
    expect(
      errorsFor({ settings: { contact_phone: '2'.repeat(7) } })['settings.contact_phone'],
    ).toBe(m.contact_phone.regex);
    expect(
      errorsFor({ settings: { contact_phone: '2'.repeat(8) } })['settings.contact_phone'],
    ).toBeUndefined();
  });

  it.each([
    'http://example.com',
    'example.com',
    'ftp://example.com',
    'https://',
    'https://exa mple.com',
  ])('el sitio "%s" se rechaza con el mensaje de la API', (website) => {
    expect(errorsFor({ settings: { website } })['settings.website']).toBe(m.website.url);
  });

  it('el sitio con https (también en mayúsculas) pasa; hasta 150', () => {
    const at150 = `https://example.com/${'a'.repeat(130)}`;

    expect(at150).toHaveLength(150);
    expect(
      errorsFor({ settings: { website: 'HTTPS://example.com' } })['settings.website'],
    ).toBeUndefined();
    expect(errorsFor({ settings: { website: at150 } })['settings.website']).toBeUndefined();
    expect(errorsFor({ settings: { website: `${at150}a` } })['settings.website']).toBe(
      m.website.max,
    );
  });

  it('el pie: hasta 500', () => {
    expect(
      errorsFor({ settings: { document_footer: 'a'.repeat(500) } })['settings.document_footer'],
    ).toBeUndefined();
    expect(
      errorsFor({ settings: { document_footer: 'a'.repeat(501) } })['settings.document_footer'],
    ).toBe(m.document_footer.max);
  });
});

describe('settingsFormToPayload: el cuerpo de PATCH system-settings', () => {
  it('el formulario completo, sin ruc ni ruc_dv', () => {
    const payload = settingsFormToPayload(VALID);

    expect(Object.keys(payload).sort()).toEqual(['legal_name', 'name', 'settings']);
    expect(payload).not.toHaveProperty('ruc');
    expect(payload).not.toHaveProperty('ruc_dv');
    expect(Object.keys(payload.settings)).toEqual([...SETTING_KEYS]);
  });

  it('las claves vacías van en null; el correo en minúsculas; el teléfono sin espacios ni guiones', () => {
    expect(
      settingsFormToPayload({
        name: '  Veterinaria Central ',
        legal_name: 'Veterinaria Central S.A.',
        settings: {
          contact_email: ' CONTACTO@Example.COM ',
          contact_phone: '+595 21 110-0001',
          website: '  ',
          document_footer: '',
        },
      }),
    ).toEqual({
      name: 'Veterinaria Central',
      legal_name: 'Veterinaria Central S.A.',
      settings: {
        contact_email: 'contacto@example.com',
        contact_phone: '+595211100001',
        website: null,
        document_footer: null,
      },
    });
  });

  it('lo guardado vuelve al formulario con null como vacío', () => {
    const data: SystemSettings = {
      clinic: { name: 'A', legal_name: 'B', ruc: '99999901', ruc_dv: '0' },
      settings: {
        contact_email: null,
        contact_phone: null,
        website: 'https://a.com',
        document_footer: null,
      },
      has_logo: false,
    };

    expect(settingsToForm(data)).toEqual({
      name: 'A',
      legal_name: 'B',
      settings: {
        contact_email: '',
        contact_phone: '',
        website: 'https://a.com',
        document_footer: '',
      },
    });
  });
});

describe('las claves, iguales a las del backend', () => {
  it('SETTING_KEYS son las editables de SystemSettingKey, en su orden', () => {
    const source = readFileSync(
      join(process.cwd(), '..', 'backend', 'app', 'Enums', 'SystemSettingKey.php'),
      'utf8',
    );
    const cases = Array.from(source.matchAll(/case \w+ = '(\w+)';/g), (match) => match[1]);
    const internal = ['logo_path', 'demo_data'];

    expect(cases.filter((key) => !internal.includes(key ?? ''))).toEqual([...SETTING_KEYS]);
    expect(SETTINGS_FORM_FIELDS.filter((field) => field.startsWith('settings.'))).toEqual(
      SETTING_KEYS.map((key) => `settings.${key}`),
    );
  });
});

describe('deuda 28: los invisibles de los bordes se recortan como en la API (trimLikeApi)', () => {
  it('el nombre "\\u200BA" tiene un solo carácter para la API', () => {
    expect(errorsFor({ name: '\u200BA' }).name).toBe(SETTINGS_MESSAGES.name.min);
  });
});
