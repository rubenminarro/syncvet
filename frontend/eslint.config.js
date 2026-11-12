import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import { defineConfig, globalIgnores } from 'eslint/config';
import globals from 'globals';
import tseslint from 'typescript-eslint';

export default defineConfig([
  globalIgnores(['dist', 'coverage', 'src/routeTree.gen.ts']),
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
      // Toda llamada a la API pasa por src/lib/api/client.ts.
      'no-restricted-globals': [
        'error',
        { name: 'fetch', message: 'Usá el cliente de src/lib/api/client.ts.' },
      ],
      '@typescript-eslint/restrict-template-expressions': ['error', { allowNumber: true }],
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
    files: ['src/**/*.test.{ts,tsx}', 'src/test/**'],
    languageOptions: { globals: { ...globals.browser, ...globals.node } },
    rules: {
      // Un it() por caso, escrito: /validar-docs cuenta los tests leyendo los it( y test( (como el backend cuenta los
      // test_), así que ni .each ni it() dentro de un bucle o de un callback de arreglo.
      'no-restricted-properties': [
        'error',
        ...['it', 'test', 'describe'].map((object) => ({
          object,
          property: 'each',
          message: 'Escribí un it() por caso (con una función auxiliar si se repite).',
        })),
      ],
      'no-restricted-syntax': [
        'error',
        {
          selector:
            ':matches(ForStatement, ForOfStatement, ForInStatement, WhileStatement, DoWhileStatement, CallExpression[callee.property.name=/^(forEach|map|flatMap)$/]) CallExpression[callee.name=/^(it|test)$/]',
          message:
            'Escribí un it() por caso, fuera de bucles (con una función auxiliar si se repite).',
        },
      ],
    },
  },
]);
