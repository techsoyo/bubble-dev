<?php
// backend/api/translate/jobs.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['success' => false, 'error' => 'Method not allowed']);
  exit;
}

// Incluir autoloader de Composer
require_once __DIR__ . '/../../vendor/autoload.php';

use Stichoza\GoogleTranslate\GoogleTranslate;

try {
  // Obtener datos del POST
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    throw new Exception('Invalid JSON input');
  }

  $text = $input['text'] ?? '';
  $targetLanguage = $input['targetLanguage'] ?? 'en';
  $sourceLanguage = $input['sourceLanguage'] ?? 'es';

  if (empty($text)) {
    throw new Exception('Text is required');
  }

  // Si el idioma objetivo es el mismo que el origen, devolver el texto original
  if ($targetLanguage === $sourceLanguage) {
    echo json_encode([
      'success' => true,
      'translation' => $text,
      'original' => $text,
      'targetLanguage' => $targetLanguage,
      'sourceLanguage' => $sourceLanguage,
      'cached' => false
    ]);
    exit;
  }

  // Configurar Google Translate
  $translator = new GoogleTranslate();
  $translator->setSource($sourceLanguage);
  $translator->setTarget($targetLanguage);

  // Configurar opciones para evitar rate limiting y problemas SSL
  $translator->setOptions([
    'timeout' => 10,
    'headers' => [
      'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ],
    'verify' => false, // Desactivar verificación SSL para desarrollo local
    'http_errors' => false
  ]);

  // Realizar la traducción
  $translation = $translator->translate($text);

  if (empty($translation)) {
    throw new Exception('Translation failed - empty result');
  }

  // Log para debugging (opcional)
  error_log("Google Translate: '$text' ($sourceLanguage) -> '$translation' ($targetLanguage)");

  echo json_encode([
    'success' => true,
    'translation' => $translation,
    'original' => $text,
    'targetLanguage' => $targetLanguage,
    'sourceLanguage' => $sourceLanguage,
    'cached' => false
  ]);
} catch (Exception $e) {
  error_log("Google Translate Error: " . $e->getMessage());

  // En caso de error, devolver el texto original como fallback
  echo json_encode([
    'success' => false,
    'error' => $e->getMessage(),
    'translation' => $text ?? '',
    'original' => $text ?? '',
    'fallback' => true
  ]);
}
