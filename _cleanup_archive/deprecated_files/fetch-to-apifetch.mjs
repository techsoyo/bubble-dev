import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

// Configuración
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Archivos objetivo con rutas exactas
const TARGET_FILES = [
  // Auth y Core
  'frontend/src/lib/auth/secureAuthManager.ts',
  'frontend/src/utils/validation.ts',
  'frontend/src/pages/dashboard/CDDashboard.tsx',
  'frontend/src/pages/dashboard/HRDashboard.tsx',
  'frontend/src/pages/auth/Register.tsx',
  'frontend/src/pages/auth/CandidateAuthPage.tsx',
  'frontend/src/lib/candidate-department-assignment.ts',
  'frontend/src/lib/cvApi.ts',

  // Servicios y Hooks
  'frontend/src/services/secureApiService.ts',
  'frontend/src/lib/googleTranslation.ts',
  'frontend/src/lib/security/secureCookieManager.ts',
  'frontend/src/lib/auth/csrfProtection.ts',
  'frontend/src/lib/auth/tokenManager.ts',
  'frontend/src/lib/auth/secureCookies.ts',
  'frontend/src/hooks/useOfflineOperations.ts',
  'frontend/src/hooks/useOnlineStatus.ts',
  'frontend/src/hooks/useSecureAuth.ts',

  // Componentes
  'frontend/src/components/auth/ResetPasswordForm.tsx',
  'frontend/src/components/auth/ForgotPasswordForm.tsx',
  'frontend/src/components/ChatbotDecisionTree.tsx',
  'frontend/src/components/UploadCV.tsx',
  'frontend/src/components/ChatBotManage.tsx',

  // Utilidades
  'frontend/src/pages/ApiTester.tsx',
  'frontend/src/security/sri.ts',
  'frontend/src/security/csp.ts',
  'frontend/src/utils/webVitalsMonitor.ts',
  'frontend/src/utils/performance.ts',

  // No críticos
  'frontend/src/serviceWorker.ts',
  'frontend/src/components/__tests__/LoginForm.test.tsx',
  'frontend/src/pages/recruiter/RecruiterDashboardPage.tsx'
].map(file => path.join(__dirname, file));

// Función mejorada con validación estricta
async function processFiles() {
  console.log('🔍 Iniciando procesamiento seguro...');
  console.log('📌 Base directory:', __dirname);

  // 1. Verificación previa
  const existingFiles = TARGET_FILES.filter(filePath => {
    const exists = fs.existsSync(filePath);
    if (!exists) {
      console.warn(`⚠️  Archivo no encontrado: ${filePath.replace(__dirname, '')}`);
      return false;
    }
    return true;
  });

  if (existingFiles.length === 0) {
    console.log('❌ No se encontraron archivos válidos');
    return;
  }

  // 2. Confirmación manual (opcional)
  console.log('\n📋 Archivos a modificar:');
  existingFiles.forEach(file => console.log(`- ${file.replace(__dirname, '')}`));

  const readline = await import('readline');
  const rl = readline.createInterface({
    input: process.stdin,
    output: process.stdout
  });

  const answer = await new Promise(resolve => {
    rl.question('\n¿Continuar con los reemplazos? (y/n) ', resolve);
  });
  rl.close();

  if (answer.toLowerCase() !== 'y') {
    console.log('🚫 Operación cancelada');
    return;
  }

  // 3. Procesamiento seguro
  let successCount = 0;
  const backupDir = path.join(__dirname, 'apiFetchBackups');
  fs.mkdirSync(backupDir, { recursive: true });

  for (const filePath of existingFiles) {
    try {
      const fileName = path.basename(filePath);
      const backupPath = path.join(backupDir, `${fileName}.${Date.now()}.bak`);

      // Crear backup en directorio separado
      fs.copyFileSync(filePath, backupPath);

      // Leer contenido
      let content = fs.readFileSync(filePath, 'utf8');
      const originalContent = content;

      // Reemplazos seguros (solo donde corresponde)
      content = content
        .replace(/(\bawait\s+)fetch\(/g, '$1apiFetch(')  // await fetch(
        .replace(/([^\.]\b)fetch\(/g, '$1apiFetch(')     // fetch( pero no .fetch(
        .replace(/\bfetch\./g, 'apiFetch.')              // fetch.method()
        .replace(/(\bconst\s+\w+\s*=\s*)fetch/g, '$1apiFetch'); // const x = fetch

      // Escribir solo si hubo cambios
      if (content !== originalContent) {
        fs.writeFileSync(filePath, content, 'utf8');
        console.log(`✅ ${fileName}: Actualizado (backup en ${backupPath.replace(__dirname, '')}`);
        successCount++;
      } else {
        console.log(`ℹ️  ${fileName}: Sin cambios necesarios`);
        fs.unlinkSync(backupPath); // Eliminar backup innecesario
      }
    } catch (error) {
      console.error(`❌ Error procesando ${path.basename(filePath)}:`, error.message);
    }
  }

  // Reporte final
  console.log(`\n🎉 Resultado: ${successCount}/${existingFiles.length} archivos modificados`);
  console.log(`💾 Backups guardados en: ${backupDir.replace(__dirname, '')}`);
}

// Ejecutar
processFiles();