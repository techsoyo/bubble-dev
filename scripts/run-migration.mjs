#!/usr/bin/env node

console.log('🚀 Ejecutando script de migración de endpoints...\n');

import('./migrate-endpoints.mjs')
  .then(() => {
    console.log('\n✅ Ejecución completada exitosamente');
  })
  .catch((error) => {
    console.error('\n❌ Error durante la ejecución:', error.message);
    process.exit(1);
  });
