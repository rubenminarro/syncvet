import { describe, expect, it } from 'vitest';
import { FILE_VALUE, maskAuditValues, MASKED_VALUE } from './mask';

describe('maskAuditValues: lo que la pantalla nunca muestra, aunque la API lo deje pasar', () => {
  it('password, remember_token y token, por ********', () => {
    expect(
      maskAuditValues({
        email: 'ana@example.com',
        password: '$2y$12$hash-de-la-clave',
        remember_token: 'abc123',
        token: 'plain-token',
      }),
    ).toEqual({
      email: 'ana@example.com',
      password: MASKED_VALUE,
      remember_token: MASKED_VALUE,
      token: MASKED_VALUE,
    });
  });

  it('cualquier clave con password o token en el nombre, sin importar mayúsculas, y con cualquier tipo de valor', () => {
    expect(
      maskAuditValues({
        new_password: 'otra',
        Password_Confirmation: 'otra',
        api_token: 12345,
        access_tokens: ['uno', 'dos'],
        token_data: { secreto: 'x' },
      }),
    ).toEqual({
      new_password: MASKED_VALUE,
      Password_Confirmation: MASKED_VALUE,
      api_token: MASKED_VALUE,
      access_tokens: MASKED_VALUE,
      token_data: MASKED_VALUE,
    });
  });

  it('photo_path y logo_path, por "(archivo)"', () => {
    expect(
      maskAuditValues({
        photo_path: 'clinics/1/patients/7/foto.jpg',
        logo_path: 'algo/raro.png',
        name: 'Firulais',
      }),
    ).toEqual({ photo_path: FILE_VALUE, logo_path: FILE_VALUE, name: 'Firulais' });
  });

  it('una ruta de la clínica en cualquier clave, también anidada en objetos y listas', () => {
    expect(
      maskAuditValues({
        value: 'clinics/1/logo/abc.png',
        settings: {
          logo: 'clinics/12/logo/x.webp',
          nested: [{ file: 'clinics/3/patients/1/a.jpg' }, 'clinics/3/otro'],
          auth: { password: 'clave', user: { remember_token: 'zz' } },
        },
      }),
    ).toEqual({
      value: FILE_VALUE,
      settings: {
        logo: FILE_VALUE,
        nested: [{ file: FILE_VALUE }, FILE_VALUE],
        auth: { password: MASKED_VALUE, user: { remember_token: MASKED_VALUE } },
      },
    });
  });

  it('lo demás queda igual: textos, números, booleanos, listas y un texto que solo menciona clinics', () => {
    const values = {
      first_name: 'Ana',
      branch_ids: [10, 11],
      is_active: false,
      tax_rate: 10,
      notes: 'Ver clinics/1/ en el servidor',
      address: { city: 'Asunción' },
    };

    expect(maskAuditValues(values)).toEqual(values);
  });

  it('null queda null (un valor vacío no tiene nada que ocultar), y sin valores, null', () => {
    expect(maskAuditValues({ password: null, photo_path: null })).toEqual({
      password: null,
      photo_path: null,
    });
    expect(maskAuditValues(null)).toBeNull();
  });

  it('lo que la API ya ocultó no se reemplaza dos veces', () => {
    const fromApi = { password: MASKED_VALUE, photo_path: FILE_VALUE, value: FILE_VALUE };

    expect(maskAuditValues(fromApi)).toEqual(fromApi);
    expect(maskAuditValues(maskAuditValues(fromApi))).toEqual(fromApi);
  });

  it('devuelve una copia: no toca los datos de la caché', () => {
    const values = { password: 'clave', nested: { photo_path: 'clinics/1/a.jpg' } };
    const copy = structuredClone(values);

    maskAuditValues(values);

    expect(values).toEqual(copy);
  });
});
