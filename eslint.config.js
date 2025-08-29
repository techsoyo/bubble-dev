import js from '@eslint/js';
import tseslint from '@typescript-eslint/eslint-plugin';
import tsparser from '@typescript-eslint/parser';
import complexity from 'eslint-plugin-complexity';
import security from 'eslint-plugin-security';

export default [
  js.configs.recommended,
  {
    files: ['**/*.ts', '**/*.tsx'],
    languageOptions: {
      parser: tsparser,
      ecmaVersion: 2020,
      sourceType: 'module',
      globals: {
        console: 'readonly',
        process: 'readonly',
        __dirname: 'readonly',
        __filename: 'readonly'
      }
    },
    plugins: {
      '@typescript-eslint': tseslint,
      complexity,
      security
    },
    rules: {
      ...tseslint.configs.recommended.rules,
      ...security.configs.recommended.rules,
      'complexity': ['error', 10],
      'security/detect-object-injection': 'error',
      'security/detect-eval-with-expression': 'error',
      'security/detect-no-csrf-before-method-override': 'error',
      'security/detect-possible-timing-attacks': 'error',
      'security/detect-unsafe-regex': 'error'
    }
  },
  {
    ignores: ['node_modules/', 'dist/', 'test-results/', 'playwright-report/']
  }
];
