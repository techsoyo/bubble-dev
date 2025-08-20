<?php

declare(strict_types=1);

// ====================================================================
// CONFIGURACIÓN DEL CONTENEDOR DE DEPENDENCY INJECTION
// Arquitectura moderna con Symfony DI Container
// ====================================================================

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

$container = new ContainerBuilder();

// ====================================================================
// 1. CONFIGURACIÓN DE BASE DE DATOS (DOCTRINE)
// ====================================================================

// Configuración de conexión a BD
$container->setParameter('database.host', $_ENV['DB_HOST'] ?? 'localhost');
$container->setParameter('database.port', $_ENV['DB_PORT'] ?? 3306);
$container->setParameter('database.name', $_ENV['DB_NAME'] ?? 'bubble_talents');
$container->setParameter('database.user', $_ENV['DB_USER'] ?? 'root');
$container->setParameter('database.password', $_ENV['DB_PASSWORD'] ?? '');

// EntityManager de Doctrine
$container->register('doctrine.entity_manager', \Doctrine\ORM\EntityManager::class)
  ->setFactory([\App\Factories\DoctrineFactory::class, 'createEntityManager'])
  ->setArguments([
    '%database.host%',
    '%database.port%',
    '%database.name%',
    '%database.user%',
    '%database.password%',
  ]);

// ====================================================================
// 2. CLIENTES HTTP Y AI
// ====================================================================

// Cliente HTTP Guzzle
$container->register('http.client', \GuzzleHttp\Client::class)
  ->setArguments([[
    'timeout' => 180,
    'connect_timeout' => 30,
    'headers' => [
      'User-Agent' => 'BubbleOfTalents-Backend/2.0',
      'Accept' => 'application/json',
    ]
  ]]);

// Configuración de IA
$container->setParameter('ai.ollama.base_url', $_ENV['OLLAMA_API_URL'] ?? 'http://localhost:11434');
$container->setParameter('ai.ollama.model', $_ENV['OLLAMA_MODEL'] ?? 'llama3.2');
$container->setParameter('ai.ollama.timeout', (int)($_ENV['OLLAMA_TIMEOUT_MS'] ?? 180000));

$container->setParameter('ai.openai.api_key', $_ENV['OPENAI_API_KEY'] ?? '');
$container->setParameter('ai.openai.model', $_ENV['OPENAI_MODEL'] ?? 'gpt-4');

// ====================================================================
// 3. SERVICIOS DE IA
// ====================================================================

// Servicio Ollama
$container->register('ai.ollama_service', \Services\AI\OllamaService::class)
  ->setArguments([
    new Reference('http.client'),
    new Reference('logger'),
    [
      'base_url' => '%ai.ollama.base_url%',
      'model' => '%ai.ollama.model%',
      'timeout' => '%ai.ollama.timeout%',
    ]
  ]);

// Servicio OpenAI
$container->register('ai.openai_service', \Services\AI\OpenAIService::class)
  ->setArguments([
    new Reference('http.client'),
    new Reference('logger'),
    [
      'api_key' => '%ai.openai.api_key%',
      'model' => '%ai.openai.model%',
    ]
  ]);

// Factory de proveedores de IA
$container->register('ai.provider_factory', \Services\AI\AIProviderFactory::class)
  ->setArguments([new Reference('service_container')]);

// ====================================================================
// 4. SERVICIOS DE PDF Y ARCHIVOS
// ====================================================================

// Servicio de PDF
$container->register('pdf.service', \Services\PdfTextService::class)
  ->setArguments([new Reference('logger')]);

// Servicio de almacenamiento
$container->register('storage.service', \Services\FileStorageService::class)
  ->setArguments([
    $_ENV['STORAGE_PATH'] ?? __DIR__ . '/../storage',
    new Reference('logger')
  ]);

// ====================================================================
// 5. REPOSITORIOS
// ====================================================================

$container->register('repository.cv', \Repositories\CvRepository::class)
  ->setArguments([new Reference('doctrine.entity_manager')]);

$container->register('repository.cv_metrics', \Repositories\CvMetricsRepository::class)
  ->setArguments([new Reference('doctrine.entity_manager')]);

// ====================================================================
// 6. SERVICIOS DE APLICACIÓN
// ====================================================================

// Servicio principal de procesamiento de CV
$container->register('cv.processing_service', \Services\CvProcessingService::class)
  ->setArguments([
    new Reference('ai.provider_factory'),
    new Reference('pdf.service'),
    new Reference('storage.service'),
    new Reference('repository.cv'),
    new Reference('repository.cv_metrics'),
    new Reference('logger'),
    new Reference('cache.service'),
  ]);

// Servicio de caché
$container->register('cache.service', \Services\Cache\CvCacheService::class)
  ->setArguments([
    new Reference('cache.adapter')
  ]);

// Adaptador de caché (Redis)
$container->register('cache.adapter', \Predis\Client::class)
  ->setArguments([[
    'host' => $_ENV['REDIS_HOST'] ?? 'localhost',
    'port' => $_ENV['REDIS_PORT'] ?? 6379,
    'database' => $_ENV['REDIS_DB'] ?? 0,
  ]]);

// ====================================================================
// 7. LOGGING
// ====================================================================

$container->register('logger', \Monolog\Logger::class)
  ->setArguments(['bubble_talents'])
  ->addMethodCall('pushHandler', [
    new \Monolog\Handler\StreamHandler(
      $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs/app.log',
      \Monolog\Level::Info
    )
  ])
  ->addMethodCall('pushHandler', [
    new \Monolog\Handler\RotatingFileHandler(
      $_ENV['LOG_PATH'] ?? __DIR__ . '/../logs/app.log',
      30, // mantener 30 días
      \Monolog\Level::Warning
    )
  ]);

// ====================================================================
// 8. VALIDACIÓN
// ====================================================================

$container->register('validator', \Symfony\Component\Validator\Validator\ValidatorInterface::class)
  ->setFactory([\Symfony\Component\Validator\Validation::class, 'createValidator']);

// ====================================================================
// 9. MIDDLEWARE
// ====================================================================

$container->register('middleware.cors', \Middleware\CorsMiddleware::class);
$container->register('middleware.auth', \Middleware\AuthMiddleware::class)
  ->setArguments([new Reference('logger')]);
$container->register('middleware.rate_limit', \Middleware\RateLimitMiddleware::class)
  ->setArguments([new Reference('cache.adapter'), new Reference('logger')]);

$container->register('middleware.stack', \Middleware\MiddlewareStack::class)
  ->setArguments([[
    new Reference('middleware.cors'),
    new Reference('middleware.rate_limit'),
    new Reference('middleware.auth'),
  ]]);

// ====================================================================
// 10. CONTROLADORES
// ====================================================================

$container->register(Controllers\CvProcessingController::class)
  ->setArguments([
    new Reference('cv.processing_service'),
    new Reference('storage.service'),
    new Reference('validator'),
    new Reference('logger')
  ]);

// ====================================================================
// 11. JOBS Y QUEUE
// ====================================================================

$container->register('queue.transport', \Symfony\Component\Messenger\Transport\RedisTransport::class)
  ->setArguments([
    'redis://localhost:6379/messages',
    []
  ]);

$container->register('queue.bus', \Symfony\Component\Messenger\MessageBus::class)
  ->setArguments([
    new Reference('queue.transport')
  ]);

// Compilar container
$container->compile();

return $container;
