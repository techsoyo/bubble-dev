/* ESLint confi  rules: {
    '@typescript-eslint/no-explicit-any': 'warn',
    '@typescript-eslint/no-require-imports': 'warn',
    '@typescript-eslint/no-unused-expressions': 'warn',
    '@typescript-eslint/no-empty-object-type': 'warn',
    '@typescript-eslint/no-namespace': 'warn',
    'no-useless-escape': 'warn',
    'no-empty': 'warn',
    'no-case-declarations': 'warn',
    'no-control-regex': 'warn',
    'react/react-in-jsx-scope': 'off',
    'react-hooks/rules-of-hooks': 'warn',
    'react-hooks/exhaustive-deps': 'warn'
  }, minima y estricta */
module.exports = {
  root: true,
  parser: '@typescript-eslint/parser',
  parserOptions: { ecmaVersion: 'latest', sourceType: 'module' },
  env: { browser: true, es2021: true, node: true },
  plugins: ['@typescript-eslint', 'react', 'react-hooks'],
  extends: [
    'eslint:recommended',
    'plugin:@typescript-eslint/recommended',
    'plugin:react/recommended',
    'plugin:react-hooks/recommended',
    'prettier'
  ],
  settings: { react: { version: 'detect' } },
  rules: {
    '@typescript-eslint/no-explicit-any': 'warn',
    'no-useless-escape': 'warn',
    'no-empty': 'warn',
    'no-case-declarations': 'warn',
    'no-control-regex': 'warn', // Cambio de error a warning
    'react/react-in-jsx-scope': 'off',
    'react-hooks/rules-of-hooks': 'error',
    'react-hooks/exhaustive-deps': 'warn',

    // 🚨 PRODUCCIÓN: Prohibir storage APIs por seguridad
    'no-restricted-globals': [
      'error',
      {
        'name': 'localStorage',
        'message': '🔒 localStorage PROHIBIDO en producción. Usa safeStorage o cookies httpOnly'
      },
      {
        'name': 'sessionStorage',
        'message': '🔒 sessionStorage PROHIBIDO en producción. Usa safeStorage o cookies httpOnly'
      },
      {
        'name': 'indexedDB',
        'message': '🔒 indexedDB PROHIBIDO en producción. Los datos deben persistir en el servidor'
      }
    ],
    'no-restricted-properties': [
      'error',
      {
        'object': 'window',
        'property': 'localStorage',
        'message': '🔒 window.localStorage PROHIBIDO en producción. Usa utils/safeStorage'
      },
      {
        'object': 'window',
        'property': 'sessionStorage',
        'message': '🔒 window.sessionStorage PROHIBIDO en producción. Usa utils/safeStorage'
      },
      {
        'object': 'window',
        'property': 'indexedDB',
        'message': '🔒 window.indexedDB PROHIBIDO en producción. Los datos van al servidor'
      }
    ]
  },
  ignorePatterns: [
    'node_modules/',
    'dist/',
    'build/',
    'coverage/',
    '**/*.d.ts'
  ]
};
