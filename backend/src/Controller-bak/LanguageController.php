<?php

declare(strict_types=1);

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class LanguageController extends BaseController
{
  public function getLanguage(Request $request, array $params = [])
  {
    // TODO: leer de sesión/DB
    return ResponseHelper::success('Idioma actual', [
      'language' => 'es',
      'country'  => 'ES'
    ]);
  }

  public function setLanguage(Request $request, array $params = [])
  {
    $lang = $request->input('language', 'es');
    $ctry = $request->input('country', 'ES');
    // TODO: persistir preferencia
    return ResponseHelper::success('Idioma actualizado', [
      'language' => $lang,
      'country'  => $ctry
    ]);
  }
}
