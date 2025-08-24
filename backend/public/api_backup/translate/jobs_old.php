<?php
// backend/api/translate/jobs.php
// REDIRIGIDO A JobTranslate class en jobs.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  exit(0);
}

// Incluir el archivo principal que contiene la clase JobTranslate
require_once __DIR__ . '/../jobs.php';

// Redirigir la petición a la clase JobTranslate
JobTranslate::handleTranslationRequest();
