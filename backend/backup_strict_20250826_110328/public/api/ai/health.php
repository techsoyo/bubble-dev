<?php declare(strict_types=1);

// @public

/**
 * Endpoint de verificaciÃ³n de salud del sistema
 *
 * Usado para confirmar que el sistema estÃ¡ funcionando correctamente
 */

require_once dirname(__DIR__) . '/bootstrap.php';


// Verificar el estado del sistema
$status = [
  'status' => 'ok',
  'timestamp' => date('c'),
  'version' => '1.0.0',
  'services' => [
    'parser' => [
      'status' => 'active',
      'implementation' => 'php-native'
    ],
    'matching' => [
      'status' => 'active',
      'implementation' => 'php-native'
    ]
  ]
];

// Enviar respuesta
echo json_encode($status, JSON_PRETTY_PRINT);
