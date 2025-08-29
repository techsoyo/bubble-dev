/* ESLint configuración mínima y estricta para seguridad y calidad */
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
    '@typescript-eslint/no-require-imports': 'warn',
    '@typescript-eslint/no-unused-expressions': 'warn',
    '@typescript-eslint/no-empty-object-type': 'warn',
    '@typescript-eslint/no-namespace': 'warn',
    'no-useless-escape': 'warn',
    'no-empty': 'warn',
    'no-case-declarations': 'warn',
    'no-control-regex': 'warn',
    'react/react-in-jsx-scope': 'off',
    'react-hooks/rules-of-hooks': 'error',
    'react-hooks/exhaustive-deps': 'warn',

    // 🚨 PRODUCCIÓN: Prohibir storage APIs por seguridad
    'no-restricted-globals': [
      'error',
      {
        name: 'localStorage',
        message: '🔒 localStorage PROHIBIDO en producción. Usa safeStorage o cookies httpOnly'
      },
      {
        name: 'sessionStorage',
        message: '🔒 sessionStorage PROHIBIDO en producción. Usa safeStorage o cookies httpOnly'
      },
      {
        name: 'indexedDB',
        message: '🔒 indexedDB PROHIBIDO en producción. Los datos deben persistir en el servidor'
      }
    ],
    'no-restricted-properties': [
      'error',
      {
        object: 'window',
        property: 'localStorage',
        message: '🔒 window.localStorage PROHIBIDO en producción. Usa utils/safeStorage'
      },
      {
        object: 'window',
        property: 'sessionStorage',
        message: '🔒 window.sessionStorage PROHIBIDO en producción. Usa utils/safeStorage'
      },
      {
        object: 'window',
        property: 'indexedDB',
        message: '🔒 window.indexedDB PROHIBIDO en producción. Los datos van al servidor'
      }
    ],

    // 🚨 SEGURIDAD: Prohibir URLs hardcodeadas
    'no-restricted-syntax': [
      'error',
      {
        selector: 'Literal[value=/^https?:\\/\\/localhost/]',
        message: '🔒 URLs localhost PROHIBIDAS en código. Usa variables de entorno'
      },
      {
        selector: 'Literal[value=/^https?:\\/\\/127\\.0\\.0\\.1/]',
        message: '🔒 URLs 127.0.0.1 PROHIBIDAS en código. Usa variables de entorno'
      },
      {
        selector: 'Literal[value=/^https?:\\/\\/0\\.0\\.0\\.0/]',
        message: '🔒 URLs 0.0.0.0 PROHIBIDAS en código. Usa variables de entorno'
      },
      {
        selector: 'TemplateLiteral[quasis.length>0]:has(Literal[value=/localhost/])',
        message: '🔒 URLs localhost PROHIBIDAS en templates. Usa variables de entorno'
      }
    ],

    // 🚨 SEGURIDAD: Advertir sobre fetch sin manejo de errores
    'no-await-in-loop': 'warn',
    'no-async-promise-executor': 'error',

    // 🚨 SEGURIDAD: Prohibir console.log en producción
    'no-console': process.env.NODE_ENV === 'production' ? 'error' : 'warn',

    // 🚨 SEGURIDAD: Prohibir eval y similares
    'no-eval': 'error',
    'no-implied-eval': 'error',
    'no-new-func': 'error',

    // 🚨 SEGURIDAD: Prohibir innerHTML (riesgo XSS)
    'no-restricted-syntax': [
      'error',
      ...this.rules['no-restricted-syntax'] || [],
      {
        selector: 'AssignmentExpression[left.property.name="innerHTML"]',
        message: '🔒 innerHTML PROHIBIDO por riesgo XSS. Usa textContent o sanitización'
      },
      {
        selector: 'AssignmentExpression[left.property.name="outerHTML"]',
        message: '🔒 outerHTML PROHIBIDO por riesgo XSS. Usa métodos seguros'
      }
    ],

    // 🚨 SEGURIDAD: Advertir sobre expresiones regulares peligrosas
    'no-control-regex': 'error',
    'no-empty-character-class': 'error',
    'no-invalid-regexp': 'error',

    // 🚨 SEGURIDAD: Prohibir acceso directo a document.cookie
    'no-restricted-syntax': [
      'error',
      ...this.rules['no-restricted-syntax'] || [],
      {
        selector: 'MemberExpression[object.name="document"][property.name="cookie"]',
        message: '🔒 document.cookie PROHIBIDO. Usa librerías seguras para cookies'
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