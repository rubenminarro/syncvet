import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

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
      // Sin HTML crudo: React escapa el texto; dangerouslySetInnerHTML abre la puerta a un XSS.
      'no-restricted-syntax': [
        'error',
        {
          selector: "JSXAttribute[name.name='dangerouslySetInnerHTML']",
          message: 'Sin dangerouslySetInnerHTML: mostrá el texto como texto.',
        },
      ],
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
    files: ['src/lib/api/client.ts'],
    rules: { 'no-restricted-globals': 'off' },
  },
  {
    // Playwright y su preparación corren en Node, fuera de la aplicación: llaman al backend directo (sin el cliente).
    files: ['e2e/**', 'playwright.config.ts'],
    languageOptions: { globals: { ...globals.node } },
    rules: { 'no-restricted-globals': 'off' },
  },
  {
    files: ['src/**/*.test.{ts,tsx}', 'src/test/**'],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
  },
]);
