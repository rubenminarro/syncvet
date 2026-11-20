import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

const NO_INNER_HTML = {
  selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
  message: 'Sin dangerouslySetInnerHTML: mostrá el texto como texto.',
};

/** trim(), trimStart() y trimEnd() (también los de Zod): dejan invisibles que la API recorta (U+200B, U+3164…). */
const NO_TRIM = {
  selector: 'CallExpression[callee.property.name=/^trim(Start|End|Left|Right)?$/]',
  message:
    'Usá trimLikeApi o apiLength (src/lib/format/text.ts): trim() deja invisibles que la API recorta (deuda 28). Para las clases, classNames.',
};

export default defineConfig([
  globalIgnores(['dist', 'coverage', 'src/routeTree.gen.ts', 'test-results', 'playwright-report']),
  {
    files: ['**/*.{ts,tsx}'],
    extends: [
      js.configs.recommended,
      tseslint.configs.strictTypeChecked,
      tseslint.configs.stylisticTypeChecked,
      reactHooks.configs.flat.recommended,
      reactRefresh.configs.vite,
      prettier,
    ],
    languageOptions: {
      ecmaVersion: 2023,
      globals: globals.browser,
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    rules: {
      // Toda llamada a la API pasa por src/lib/api/client.ts: ni fetch (tampoco window.fetch, globalThis.fetch ni
      // self.fetch) ni XMLHttpRequest en el resto del código.
      'no-restricted-globals': [
        'error',
        { name: 'fetch', message: 'Usá el cliente de src/lib/api/client.ts.' },
        { name: 'XMLHttpRequest', message: 'Usá el cliente de src/lib/api/client.ts.' },
      ],
      'no-restricted-properties': [
        'error',
        ...['window', 'globalThis', 'self'].map((object) => ({
          object,
          property: 'fetch',
          message: 'Usá el cliente de src/lib/api/client.ts.',
        })),
      ],
      // Sin HTML crudo (React escapa el texto; dangerouslySetInnerHTML abre la puerta a un XSS) y sin trim(): lo que va a la
      // API se recorta como la API (trimLikeApi, deuda 28); las clases, con classNames.
      'no-restricted-syntax': ['error', NO_INNER_HTML, NO_TRIM],
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
      // TanStack Router corta una navegación con `throw redirect(...)` o `throw notFound()`, que no son un Error.
      '@typescript-eslint/only-throw-error': [
        'error',
        {
          allow: [
            { from: 'package', package: '@tanstack/router-core', name: 'Redirect' },
            { from: 'package', package: '@tanstack/router-core', name: 'NotFoundError' },
          ],
        },
      ],
    },
  },
  {
    // Las rutas de TanStack Router exportan `Route` junto con sus componentes.
    files: ['src/routes/**/*.tsx'],
    rules: { 'react-refresh/only-export-components': 'off' },
  },
  {
    // El cliente lee cabeceras y cuerpos de la respuesta (no manda texto escrito): ahí trim() es el de HTTP.
    files: ['src/lib/api/client.ts'],
    rules: { 'no-restricted-globals': 'off', 'no-restricted-syntax': ['error', NO_INNER_HTML] },
  },
  {
    // Playwright y su preparación corren en Node, fuera de la aplicación: llaman al backend directo (sin el cliente).
    files: ['e2e/**', 'playwright.config.ts'],
    languageOptions: { globals: { ...globals.node } },
    rules: { 'no-restricted-globals': 'off', 'no-restricted-syntax': ['error', NO_INNER_HTML] },
  },
  {
    // Los tests leen el texto de la pantalla y de los archivos: ahí trim() no va a la API.
    files: ['src/**/*.test.{ts,tsx}', 'src/test/**'],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
    rules: { 'no-restricted-syntax': ['error', NO_INNER_HTML] },
  },
]);
