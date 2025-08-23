<?php
/**
 * RUN ALL TESTS - BUBBLE TALENTS
 * Script maestro para ejecutar toda la suite de testing
 * Orden: Smoke -> Functional -> Security -> Debug Protection
 */

require_once __DIR__ . '/test_config.php';
require_once __DIR__ . '/smoke_tests.php';
require_once __DIR__ . '/functional_tests.php';
require_once __DIR__ . '/security_tests.php';
require_once __DIR__ . '/debug_protection_tests.php';

class TestRunner {
    private $results = [];
    private $start_time;
    private $total_tests = 0;
    private $passed_tests = 0;
    
    public function __construct() {
        $this->start_time = microtime(true);
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "✨ BUBBLE TALENTS - SUITE COMPLETA DE TESTING ✨\n";
        echo "Iniciado: " . date('Y-m-d H:i:s') . "\n";
        echo "URL Base: " . TestConfig::$base_url . "\n";
        echo str_repeat('=', 60) . "\n";
    }
    
    /**
     * Ejecutar smoke tests
     */
    private function runSmokeTests() {
        echo "\n\n🔥 FASE 1: SMOKE TESTS (Conectividad Básica)\n";
        echo str_repeat('-', 50) . "\n";
        
        $smokeTests = new SmokeTests();
        $result = $smokeTests->runAll();
        
        $this->results['smoke'] = [
            'passed' => $result,
            'critical' => true,
            'description' => 'Tests básicos de conectividad y disponibilidad'
        ];
        
        if ($result) {
            echo "✅ Smoke tests COMPLETADOS - Sistema accesible\n";
            $this->passed_tests++;
        } else {
            echo "❌ Smoke tests FALLARON - Problemas de conectividad críticos\n";
            echo "\n⚠️  ADVERTENCIA: Fallos en smoke tests pueden causar fallos en tests posteriores\n";
        }
        
        $this->total_tests++;
        return $result;
    }
    
    /**
     * Ejecutar functional tests
     */
    private function runFunctionalTests() {
        echo "\n\n✨ FASE 2: FUNCTIONAL TESTS (Casos Felices)\n";
        echo str_repeat('-', 50) . "\n";
        
        $functionalTests = new FunctionalTests();
        $result = $functionalTests->runAll();
        
        $this->results['functional'] = [
            'passed' => $result,
            'critical' => false,
            'description' => 'Tests de funcionalidad principal y casos felices'
        ];
        
        if ($result) {
            echo "✅ Functional tests COMPLETADOS - Funcionalidad principal operativa\n";
            $this->passed_tests++;
        } else {
            echo "❌ Functional tests FALLARON - Problemas en funcionalidad principal\n";
        }
        
        $this->total_tests++;
        return $result;
    }
    
    /**
     * Ejecutar security tests
     */
    private function runSecurityTests() {
        echo "\n\n🔒 FASE 3: SECURITY TESTS (Seguridad y Validaciones)\n";
        echo str_repeat('-', 50) . "\n";
        
        $securityTests = new SecurityTests();
        $result = $securityTests->runAll();
        
        $this->results['security'] = [
            'passed' => $result,
            'critical' => true,
            'description' => 'Tests de seguridad, autenticación y validaciones'
        ];
        
        if ($result) {
            echo "✅ Security tests COMPLETADOS - Nivel de seguridad aceptable\n";
            $this->passed_tests++;
        } else {
            echo "❌ Security tests FALLARON - Vulnerabilidades de seguridad detectadas\n";
        }
        
        $this->total_tests++;
        return $result;
    }
    
    /**
     * Ejecutar debug protection tests
     */
    private function runDebugProtectionTests() {
        echo "\n\n🚫 FASE 4: DEBUG PROTECTION TESTS (Protección Producción)\n";
        echo str_repeat('-', 50) . "\n";
        
        $debugTests = new DebugProtectionTests();
        $result = $debugTests->runAll();
        
        $this->results['debug_protection'] = [
            'passed' => $result,
            'critical' => true,
            'description' => 'Tests de protección de endpoints debug en producción'
        ];
        
        if ($result) {
            echo "✅ Debug protection tests COMPLETADOS - Protección adecuada\n";
            $this->passed_tests++;
        } else {
            echo "❌ Debug protection tests FALLARON - Exposición de endpoints debug\n";
        }
        
        $this->total_tests++;
        return $result;
    }
    
    /**
     * Generar reporte final
     */
    private function generateFinalReport() {
        $execution_time = round((microtime(true) - $this->start_time), 2);
        $success_rate = round(($this->passed_tests / $this->total_tests) * 100, 1);
        
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "📈 REPORTE FINAL DE TESTING\n";
        echo str_repeat('=', 60) . "\n";
        
        echo "Tiempo total de ejecución: {$execution_time}s\n";
        echo "Suites ejecutadas: {$this->passed_tests}/{$this->total_tests}\n";
        echo "Tasa de éxito: {$success_rate}%\n\n";
        
        // Detalles por suite
        echo "DETALLES POR SUITE:\n";
        echo str_repeat('-', 30) . "\n";
        
        foreach ($this->results as $suite => $data) {
            $status = $data['passed'] ? '✅ PASS' : '❌ FAIL';
            $critical = $data['critical'] ? ' (CRÍTICO)' : '';
            echo sprintf("%-20s %s%s\n", strtoupper($suite), $status, $critical);
            echo "  → {$data['description']}\n\n";
        }
        
        // Determinar estado general
        $critical_failures = 0;
        foreach ($this->results as $suite => $data) {
            if ($data['critical'] && !$data['passed']) {
                $critical_failures++;
            }
        }
        
        echo "ESTADO GENERAL DEL SISTEMA:\n";
        echo str_repeat('-', 30) . "\n";
        
        if ($critical_failures === 0 && $success_rate >= 90) {
            echo "✅ EXCELENTE - Sistema listo para producción\n";
            $final_status = 'EXCELLENT';
        } elseif ($critical_failures === 0 && $success_rate >= 75) {
            echo "🔆 BUENO - Sistema estable con mejoras menores recomendadas\n";
            $final_status = 'GOOD';
        } elseif ($critical_failures <= 1) {
            echo "⚠️  ACEPTABLE - Sistema funcional pero requiere atención\n";
            $final_status = 'ACCEPTABLE';
        } else {
            echo "❌ CRÍTICO - Sistema requiere corrección inmediata antes de producción\n";
            $final_status = 'CRITICAL';
        }
        
        echo "\nFALLOS CRÍTICOS: $critical_failures\n";
        
        // Recomendaciones
        echo "\nRECOMENDACIONES INMEDIATAS:\n";
        echo str_repeat('-', 30) . "\n";
        
        if (!$this->results['smoke']['passed']) {
            echo "1. ⚠️  URGENTE: Resolver problemas de conectividad básica\n";
        }
        
        if (!$this->results['security']['passed']) {
            echo "2. 🔒 SEGURIDAD: Corregir vulnerabilidades detectadas\n";
        }
        
        if (!$this->results['debug_protection']['passed']) {
            echo "3. 🚫 PRODUCCIÓN: Proteger endpoints debug antes de desplegar\n";
        }
        
        if (!$this->results['functional']['passed']) {
            echo "4. ✨ FUNCIONALIDAD: Implementar endpoints faltantes críticos\n";
        }
        
        // Generar archivo de log final
        $this->saveFinalReport($final_status, $execution_time, $success_rate);
        
        return $final_status;
    }
    
    /**
     * Guardar reporte final en archivo
     */
    private function saveFinalReport($status, $execution_time, $success_rate) {
        $report = [
            'timestamp' => date('Y-m-d H:i:s'),
            'execution_time' => $execution_time,
            'success_rate' => $success_rate,
            'final_status' => $status,
            'results' => $this->results,
            'summary' => [
                'total_suites' => $this->total_tests,
                'passed_suites' => $this->passed_tests,
                'critical_failures' => array_sum(array_map(function($r) {
                    return $r['critical'] && !$r['passed'] ? 1 : 0;
                }, $this->results))
            ]
        ];
        
        $log_file = __DIR__ . '/logs/final_test_report.json';
        if (!is_dir(dirname($log_file))) {
            mkdir(dirname($log_file), 0755, true);
        }
        
        file_put_contents($log_file, json_encode($report, JSON_PRETTY_PRINT));
        echo "\n📄 Reporte detallado guardado en: $log_file\n";
    }
    
    /**
     * Ejecutar toda la suite de testing
     */
    public function runAllTests($skip_on_critical_failure = false) {
        echo "Ejecutando suite completa de testing...\n";
        echo "Configuración: " . json_encode(TestConfig::getEnvironmentConfig()) . "\n";
        
        // Fase 1: Smoke Tests (crítico)
        $smoke_result = $this->runSmokeTests();
        
        if (!$smoke_result && $skip_on_critical_failure) {
            echo "\n🛑 DETENIENDO EJECUCIÓN: Smoke tests fallaron y skip_on_critical_failure=true\n";
            return false;
        }
        
        // Fase 2: Functional Tests  
        $this->runFunctionalTests();
        
        // Fase 3: Security Tests (crítico)
        $security_result = $this->runSecurityTests();
        
        if (!$security_result && $skip_on_critical_failure) {
            echo "\n🛑 ADVERTENCIA: Security tests fallaron pero continuando ejecución\n";
        }
        
        // Fase 4: Debug Protection Tests (crítico)
        $this->runDebugProtectionTests();
        
        // Generar reporte final
        $final_status = $this->generateFinalReport();
        
        return in_array($final_status, ['EXCELLENT', 'GOOD', 'ACCEPTABLE']);
    }
}

/**
 * FUNCIONES DE UTILIDAD PARA LÍNEA DE COMANDOS
 */
function printUsage() {
    echo "USO: php run_all_tests.php [opciones]\n";
    echo "\nOpciones:\n";
    echo "  --help, -h          Mostrar esta ayuda\n";
    echo "  --skip-critical     Detener ejecución en fallos críticos\n";
    echo "  --smoke-only        Ejecutar solo smoke tests\n";
    echo "  --functional-only   Ejecutar solo functional tests\n";
    echo "  --security-only     Ejecutar solo security tests\n";
    echo "  --debug-only        Ejecutar solo debug protection tests\n";
    echo "  --url=<url>         URL base personalizada\n";
    echo "\nEjemplos:\n";
    echo "  php run_all_tests.php\n";
    echo "  php run_all_tests.php --skip-critical\n";
    echo "  php run_all_tests.php --smoke-only\n";
    echo "  php run_all_tests.php --url=https://api.bubbletalents.com\n";
}

function parseArguments($argv) {
    $options = [
        'skip_critical' => false,
        'test_type' => 'all',
        'custom_url' => null
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        switch ($arg) {
            case '--help':
            case '-h':
                printUsage();
                exit(0);
                
            case '--skip-critical':
                $options['skip_critical'] = true;
                break;
                
            case '--smoke-only':
                $options['test_type'] = 'smoke';
                break;
                
            case '--functional-only':
                $options['test_type'] = 'functional';
                break;
                
            case '--security-only':
                $options['test_type'] = 'security';
                break;
                
            case '--debug-only':
                $options['test_type'] = 'debug';
                break;
                
            default:
                if (strpos($arg, '--url=') === 0) {
                    $options['custom_url'] = substr($arg, 6);
                }
                break;
        }
    }
    
    return $options;
}

// Ejecutar si se llama directamente desde línea de comandos
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $options = parseArguments($argv ?? []);
    
    // Aplicar URL personalizada si se especifica
    if ($options['custom_url']) {
        TestConfig::$base_url = $options['custom_url'];
        echo "Usando URL personalizada: {$options['custom_url']}\n";
    }
    
    $success = false;
    
    // Ejecutar tipo de test específico
    switch ($options['test_type']) {
        case 'smoke':
            $smokeTests = new SmokeTests();
            $success = $smokeTests->runAll();
            break;
            
        case 'functional':
            $functionalTests = new FunctionalTests();
            $success = $functionalTests->runAll();
            break;
            
        case 'security':
            $securityTests = new SecurityTests();
            $success = $securityTests->runAll();
            break;
            
        case 'debug':
            $debugTests = new DebugProtectionTests();
            $success = $debugTests->runAll();
            break;
            
        case 'all':
        default:
            $runner = new TestRunner();
            $success = $runner->runAllTests($options['skip_critical']);
            break;
    }
    
    exit($success ? 0 : 1);
}
?>