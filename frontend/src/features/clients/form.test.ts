import { describe, expect, it } from 'vitest';
import {
  CLIENT_MESSAGES as M,
  clientFormErrors,
  clientFormSchema,
  clientFormToPayload,
  clientToForm,
  EMPTY_CLIENT_FORM,
  type ClientFormValues,
} from './form';
import { RUC_MESSAGE } from './ruc';
import { COMPANY, PERSON, wrongRuc } from '../../test/clients';

// Los documentos son los ficticios de ClientStoreTest (R-011).
const person: ClientFormValues = {
  ...EMPTY_CLIENT_FORM,
  first_name: 'Juan',
  last_name: 'Pérez',
  document_number: '99400001',
  email: 'juan@example.com',
  phone: '+595971222333',
};

const company: ClientFormValues = {
  ...EMPTY_CLIENT_FORM,
  kind: 'company',
  business_name: 'Haras del Sur SA',
  document_number: '99999904-4',
  email: 'haras@example.com',
  phone: '+595971222333',
};

const errorsFor = (overrides: Partial<ClientFormValues>, base = person) =>
  clientFormErrors({ ...base, ...overrides });

describe('clientFormErrors: las reglas de StoreClientRequest, con sus mensajes', () => {
  it('una persona y una empresa completas no tienen errores', () => {
    expect(clientFormErrors(person)).toEqual({});
    expect(clientFormErrors(company)).toEqual({});
  });

  it.each([
    ['first_name', '', M.first_name.required],
    ['first_name', '   ', M.first_name.required],
    ['first_name', 'J', M.first_name.min],
    ['first_name', 'a'.repeat(101), M.first_name.max],
    ['first_name', 'Juan2', M.first_name.regex],
    ['last_name', '', M.last_name.required],
    ['last_name', 'P', M.last_name.min],
    ['last_name', 'a'.repeat(101), M.last_name.max],
    // Los casos de PersonName de ClientStoreTest: el apóstrofo y el guion, solo entre letras.
    ['last_name', "'Ana", M.last_name.regex],
    ['last_name', "Ana'", M.last_name.regex],
    ['last_name', '-Ana', M.last_name.regex],
    ['last_name', 'Ana-', M.last_name.regex],
    ['last_name', 'Ana--María', M.last_name.regex],
    ['last_name', "D''Alessandro", M.last_name.regex],
    ['last_name', 'Ana -María', M.last_name.regex],
    ['last_name', 'Ana1', M.last_name.regex],
    ['document_number', '', M.document_number.required],
    ['document_number', '9940', M.document_number.min],
    ['document_number', '9'.repeat(21), M.document_number.max],
    ['document_number', '99400.001', M.document_number.regex],
    ['email', '', M.email.required],
    ['email', 'juan@', M.email.email],
    ['email', 'juan.example.com', M.email.email],
    ['email', `${'a'.repeat(40)}@example.com`, M.email.max],
    ['phone', '', M.phone.required],
    ['phone', '0981123456', M.phone.regex],
    ['phone', '+5959', M.phone.regex],
    ['phone', '+5959712223334444', M.phone.regex],
    ['address', 'a'.repeat(256), M.address.max],
    ['address', 'Calle <b>', M.address.regex],
    ['city', 'a'.repeat(101), M.city.max],
    ['city', 'Asunción 2', M.city.regex],
    ['notes', 'a'.repeat(1001), M.notes.max],
    ['notes', 'Paga con $', M.notes.regex],
  ] as const)('%s = %j: "%s"', (field, value, message) => {
    expect(errorsFor({ [field]: value })[field]).toBe(message);
  });

  it.each([
    ['business_name', '', M.business_name.required],
    ['business_name', 'H', M.business_name.min],
    ['business_name', 'a'.repeat(151), M.business_name.max],
    ['business_name', 'Haras <SA>', M.business_name.regex],
  ] as const)('empresa, %s = %j: "%s"', (field, value, message) => {
    expect(errorsFor({ [field]: value }, company)[field]).toBe(message);
  });

  it.each([
    [
      'nombres con apóstrofo y guion entre letras',
      { first_name: 'Ana-María', last_name: "D'Alessandro" },
    ],
    ['un teléfono con espacios y guiones', { phone: '+595 971 222-333' }],
    ['un email con mayúsculas y espacios (se pasa a minúsculas)', { email: '  Juan@Example.COM ' }],
    ['la dirección con sus caracteres', { address: 'Av. España 1.234 c/ Brasil, Nº 5 (piso 2°)' }],
    ['las notas con sus caracteres', { notes: 'Llamar antes: 10 a 12 (lunes)! Ok? #1 @x; ver.' }],
    ['una cédula sin guion', { document_number: '99400003' }],
    ['un documento con letras', { document_number: 'AB-12345-9' }],
    ['un RUC con su dígito', { document_number: '99999903-6' }],
  ])('pasa: %s', (_name, overrides) => {
    expect(errorsFor(overrides)).toEqual({});
  });

  it('el RUC con el dígito verificador equivocado: el mensaje de RucCheckDigit', () => {
    expect(errorsFor({ document_number: wrongRuc('99999903') }).document_number).toBe(RUC_MESSAGE);
    expect(errorsFor({ document_number: wrongRuc('99999904') }, company).document_number).toBe(
      RUC_MESSAGE,
    );
  });

  it('el RUC con espacios en los extremos se revisa sin ellos (como TrimStrings de la API)', () => {
    expect(errorsFor({ document_number: `  ${wrongRuc('99999903')}  ` }).document_number).toBe(
      RUC_MESSAGE,
    );
    expect(errorsFor({ document_number: '  99999903-6  ' })).toEqual({});
  });

  it.each(['josé@example.com', 'juan@ñandutí.com.py', 'juan!x@example.com', "o'brien@example.com"])(
    'el email %s pasa: el navegador revisa solo la forma mínima (el formato completo y el DNS, la API)',
    (email) => {
      expect(errorsFor({ email })).toEqual({});
    },
  );

  it.each(['juan', 'juan@', '@example.com', 'ju an@example.com', 'juan@@example.com'])(
    'el email %j no tiene la forma mínima',
    (email) => {
      expect(errorsFor({ email }).email).toBe(M.email.email);
    },
  );

  it('una persona no pide la razón social; una empresa no pide nombre ni apellido', () => {
    expect(errorsFor({ business_name: '' })).toEqual({});
    expect(errorsFor({ first_name: '', last_name: '' }, company)).toEqual({});
  });

  it('el esquema de Zod pone cada error en su campo', () => {
    const result = clientFormSchema.safeParse({ ...person, first_name: '', phone: 'x' });

    expect(result.success).toBe(false);
    expect(result.error?.issues.map((issue) => [issue.path.join('.'), issue.message])).toEqual([
      ['first_name', M.first_name.required],
      ['phone', M.phone.regex],
    ]);
  });
});

/**
 * Cada largo de la API en su borde exacto (revisión de la 1.2: con 100 → 99 o 50 → 49 los tests seguían pasando): el
 * valor en el límite pasa y el del límite más uno (o menos uno, en los mínimos) falla, con el mensaje de la API. Los
 * números se arman en el código: no son documentos de ejemplo (FictitiousDocumentsTest).
 */
describe('clientFormErrors: los límites de cada campo', () => {
  const digits = (count: number) => '9'.repeat(count);

  it.each([
    ['first_name', person, 'a'.repeat(100), 'a'.repeat(101), M.first_name.max],
    ['first_name', person, 'Jo', 'J', M.first_name.min],
    ['last_name', person, 'a'.repeat(100), 'a'.repeat(101), M.last_name.max],
    ['last_name', person, 'Po', 'P', M.last_name.min],
    ['business_name', company, 'a'.repeat(150), 'a'.repeat(151), M.business_name.max],
    ['business_name', company, 'HS', 'H', M.business_name.min],
    ['document_number', person, digits(20), digits(21), M.document_number.max],
    ['document_number', person, digits(5), digits(4), M.document_number.min],
    [
      'email',
      person,
      `${'a'.repeat(38)}@example.com`,
      `${'a'.repeat(39)}@example.com`,
      M.email.max,
    ],
    // 15 y 16 dígitos (el máximo de la regla); 8 y 7 (el mínimo).
    ['phone', person, `+${digits(15)}`, `+${digits(16)}`, M.phone.regex],
    ['phone', person, `+${digits(8)}`, `+${digits(7)}`, M.phone.regex],
    ['address', person, 'a'.repeat(255), 'a'.repeat(256), M.address.max],
    ['city', person, 'a'.repeat(100), 'a'.repeat(101), M.city.max],
    ['notes', person, 'a'.repeat(1000), 'a'.repeat(1001), M.notes.max],
  ] as const)('%s: pasa en el límite y falla pasándolo', (field, base, atLimit, past, message) => {
    expect(errorsFor({ [field]: atLimit }, base)[field]).toBeUndefined();
    expect(errorsFor({ [field]: past }, base)[field]).toBe(message);
  });
});

describe('clientFormToPayload: el cuerpo que se manda', () => {
  it('una persona: la razón social en null, el email en minúsculas, el teléfono sin espacios y los vacíos en null', () => {
    expect(
      clientFormToPayload({
        ...person,
        first_name: ' Juan ',
        business_name: 'Quedó de antes',
        email: '  Juan@Example.COM ',
        phone: '+595 971 222-333',
        address: '  ',
        notes: ' Cliente nuevo. ',
      }),
    ).toEqual({
      kind: 'person',
      first_name: 'Juan',
      last_name: 'Pérez',
      business_name: null,
      document_number: '99400001',
      email: 'juan@example.com',
      phone: '+595971222333',
      address: null,
      city: null,
      notifications_enabled: true,
      notes: 'Cliente nuevo.',
    });
  });

  it('persona → empresa: el nombre y el apellido que quedaron cargados van en null', () => {
    const payload = clientFormToPayload({ ...company, first_name: 'Juan', last_name: 'Pérez' });

    expect(payload).toMatchObject({
      kind: 'company',
      business_name: 'Haras del Sur SA',
      first_name: null,
      last_name: null,
    });
  });

  it('clientToForm: los null del cliente guardado como campos vacíos', () => {
    expect(clientToForm(COMPANY)).toMatchObject({
      kind: 'company',
      first_name: '',
      email: '',
      address: '',
      business_name: 'Granja San Jorge S.R.L.',
      notifications_enabled: false,
    });
    expect(clientFormToPayload(clientToForm(PERSON))).toMatchObject({
      first_name: 'Laura',
      email: 'laura.martinez@example.com',
      notes: null,
    });
  });
});
