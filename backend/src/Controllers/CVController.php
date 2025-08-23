<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class CVController
{
  public function analyzeFile(Request $request)
  {
    // TODO: Analizar archivo de CV
    $file = $request->file('cv');
    return ResponseHelper::success('CV file analyzed', [
      'file' => $file
    ]);
  }

  public function analyzeText(Request $request)
  {
    // TODO: Analizar texto de un CV
    $text = $request->input('text');
    return ResponseHelper::success('CV text analyzed', [
      'text' => $text
    ]);
  }

  public function extractText(Request $request)
  {
    // TODO: Extraer texto de un PDF
    $file = $request->file('pdf');
    return ResponseHelper::success('Text extracted from PDF', [
      'file' => $file
    ]);
  }

  public function process(Request $request)
  {
    // TODO: Pipeline de procesamiento completo de un CV
    return ResponseHelper::success('CV processed', [
      'steps' => []
    ]);
  }
}
