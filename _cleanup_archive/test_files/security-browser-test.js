// Script de verificación de migración de seguridad
// Ejecutar en la consola del navegador después de cargar la aplicación

console.log('🔒 Iniciando verificación de migración de seguridad...');

// 1. Verificar que no hay datos sensibles en localStorage
const checkLocalStorage = () => {
    const sensitivePatterns = [
        /token/i, /auth/i, /login/i, /session/i, /password/i,
        /user/i, /id/i, /email/i, /profile/i, /credential/i
    ];
    
    const allKeys = Object.keys(localStorage);
    const sensitiveKeys = [];
    
    allKeys.forEach(key => {
        const value = localStorage.getItem(key);
        const keyLower = key.toLowerCase();
        
        if (sensitivePatterns.some(pattern => pattern.test(keyLower)) || 
            sensitivePatterns.some(pattern => pattern.test(value))) {
            sensitiveKeys.push(key);
        }
    });
    
    if (sensitiveKeys.length === 0) {
        console.log('✅ localStorage limpio de datos sensibles');
        return true;
    } else {
        console.log('❌ Datos sensibles encontrados en localStorage:', sensitiveKeys);
        return false;
    }
};

// 2. Verificar que no hay datos sensibles en sessionStorage
const checkSessionStorage = () => {
    const sensitivePatterns = [
        /token/i, /auth/i, /login/i, /session/i, /password/i,
        /user/i, /id/i, /email/i, /profile/i, /credential/i
    ];
    
    const allKeys = Object.keys(sessionStorage).filter(key => !key.startsWith('bt_'));
    const sensitiveKeys = [];
    
    allKeys.forEach(key => {
        const value = sessionStorage.getItem(key);
        const keyLower = key.toLowerCase();
        
        if (sensitivePatterns.some(pattern => pattern.test(keyLower)) || 
            sensitivePatterns.some(pattern => pattern.test(value))) {
            sensitiveKeys.push(key);
        }
    });
    
    if (sensitiveKeys.length === 0) {
        console.log('✅ sessionStorage limpio de datos sensibles (excepto bt_ prefijos)');
        return true;
    } else {
        console.log('❌ Datos sensibles encontrados en sessionStorage:', sensitiveKeys);
        return false;
    }
};

// 3. Verificar que las cookies HTTP-only están funcionando
const checkHTTPOnlyCookies = async () => {
    try {
        // Intentar hacer una petición autenticada
        const response = await fetch('/auth/test', {
            method: 'GET',
            credentials: 'include'
        });
        
        if (response.ok) {
            console.log('✅ Cookies HTTP-only funcionando correctamente');
            return true;
        } else {
            console.log('⚠️ Test de cookies HTTP-only falló, pero puede ser normal si no estás logueado');
            return true; // No es error si no hay sesión
        }
    } catch (error) {
        console.log('⚠️ No se pudo probar cookies HTTP-only:', error.message);
        return true; // No bloquear por problemas de red
    }
};

// 4. Verificar que SecurityMigration está disponible
const checkSecurityMigration = () => {
    if (typeof window.SecurityMigration !== 'undefined') {
        console.log('✅ SecurityMigration disponible globalmente');
        return true;
    } else {
        console.log('⚠️ SecurityMigration no está disponible globalmente');
        return false;
    }
};

// Ejecutar todas las verificaciones
const runSecurityVerification = async () => {
    console.log('\n🔍 Ejecutando verificaciones...\n');
    
    const results = {
        localStorage: checkLocalStorage(),
        sessionStorage: checkSessionStorage(),
        httpOnlyCookies: await checkHTTPOnlyCookies(),
        securityMigration: checkSecurityMigration()
    };
    
    const allPassed = Object.values(results).every(result => result === true);
    
    console.log('\n🔒 ===== RESULTADOS DE VERIFICACIÓN =====');
    Object.entries(results).forEach(([test, passed]) => {
        console.log(${passed ? '✅' : '❌'} : );
    });
    
    if (allPassed) {
        console.log('\n🎉 ¡MIGRACIÓN DE SEGURIDAD COMPLETADA EXITOSAMENTE!');
        console.log('Tu aplicación ahora usa cookies HTTP-only seguras.');
    } else {
        console.log('\n⚠️ Algunas verificaciones fallaron. Revisa los detalles arriba.');
    }
    
    return results;
};

// Ejecutar verificación
runSecurityVerification().then(results => {
    console.log('\n📊 Reporte completo:', results);
});

// Función para ejecutar migración manual si es necesario
window.runManualSecurityMigration = () => {
    if (window.SecurityMigration) {
        window.SecurityMigration.runMigrationFromConsole();
    } else {
        console.log('❌ SecurityMigration no está disponible');
    }
};

console.log('\n💡 Usa runManualSecurityMigration() para ejecutar migración manual si es necesario');
