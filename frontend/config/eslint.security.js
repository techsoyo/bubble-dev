// ESLint Security Configuration
export default {
  root: true,
  extends: [
    "../.eslintrc.json"
  ],
  ignorePatterns: ["!**/*.ts", "!**/*.tsx", "!**/*.js", "!**/*.jsx"],
  rules: {
    "@typescript-eslint/no-explicit-any": "error",
    "react-security/no-dangerously-set-innerhtml": "error",
    "react-security/no-javascript-urls": "error",
    "security/detect-eval-with-expression": "error",
    "security/detect-no-csrf-before-method-override": "error",
    "security/detect-unsafe-regex": "error",
    "security-node/detect-sql-injection": "error",
    "security-node/detect-nosql-injection": "error",
    "security-node/detect-insecure-randomness": "error",
    "no-eval": "error",
    "no-implied-eval": "error",
    "no-new-func": "error",
    "no-script-url": "error"
  }
};
