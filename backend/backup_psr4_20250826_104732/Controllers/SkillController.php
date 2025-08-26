<?php declare(strict_types=1);

namespace Controllers\SkillController.php\Controllers;

use Models\Skill;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class SkillController extends BaseController
{
  private Skill $model;

  public function __construct()
  {
    parent::__construct();
    $this->model = new Skill();
  }

  public function index(Request $request, array $params = [])
  {
    try {
      $filters = $_GET ?? [];
      $page = max(1, (int)($filters['page'] ?? 1));
      $limit = (int)($filters['limit'] ?? 20);
      $offset = ($page - 1) * $limit;

      // Buscar habilidades
      $criteria = [];
      if (!empty($filters['name'])) {
        $criteria['name'] = $filters['name'];
      }
      if (!empty($filters['category'])) {
        $criteria['category'] = $filters['category'];
      }
      if (!empty($filters['level'])) {
        $criteria['level'] = $filters['level'];
      }

      $rows = $this->model->searchSkills($criteria, $limit, $offset);
      $total = $this->model->countSkills($criteria);

      return ResponseHelper::success('Lista de habilidades', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error listing skills', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar habilidades', $e, 500);
    }
  }

  public function show(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $skill = $this->model->getSkill((int)$id);
      if (!$skill) {
        return ResponseHelper::fail('Habilidad no encontrada', 404);
      }

      return ResponseHelper::success('Habilidad encontrada', $skill, 200);
    } catch (\Throwable $e) {
      Logger::error('Error retrieving skill', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al obtener habilidad', $e, 500);
    }
  }

  public function store(Request $request, array $params = [])
  {
    try {
      $data = $request->getBody();

      $id = $this->model->createSkill($data);

      if ($id === false) {
        return ResponseHelper::fail('Unable to create skill', 400);
      }

      Logger::info('Skill created from controller', ['id' => $id]);
      return ResponseHelper::success('Habilidad creada', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed creating skill', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error creating skill', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear habilidad', $e, 500);
    }
  }

  public function update(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      $data = $request->getBody();

      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->updateSkill((int)$id, $data);

      if (!$ok) {
        return ResponseHelper::fail('Update failed', 400);
      }

      Logger::info('Skill updated from controller', ['id' => $id]);
      return ResponseHelper::success("Habilidad $id actualizada", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      Logger::error('Validation failed updating skill', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error updating skill', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al actualizar habilidad', $e, 500);
    }
  }

  public function delete(Request $request, array $params = [])
  {
    try {
      $id = $params['id'] ?? null;
      if (!$id) {
        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $ok = $this->model->deleteSkill((int)$id);
      if (!$ok) {
        return ResponseHelper::fail('Delete failed', 400);
      }

      Logger::info('Skill deleted from controller', ['id' => $id]);
      return ResponseHelper::success("Habilidad $id eliminada", [], 204);
    } catch (\Throwable $e) {
      Logger::error('Unexpected error deleting skill', ['id' => $id, 'error' => $e->getMessage()]);
      return ResponseHelper::error('Error al eliminar habilidad', $e, 500);
    }
  }

  public function extract(Request $request, array $params = [])
  {
    try {
      $data = $request->getBody();
      $text = $data['text'] ?? '';

      if (empty($text)) {
        return ResponseHelper::fail('Texto no proporcionado', 400);
      }

      $skills = $this->model->extractSkillsFromText($text);

      Logger::info('Skills extracted from text', ['skills_count' => count($skills)]);
      return ResponseHelper::success('Habilidades extraÃ­das', [
        'skills' => $skills,
        'total' => count($skills)
      ], 200);
    } catch (\Throwable $e) {
      Logger::error('Error extracting skills from text', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al extraer habilidades', $e, 500);
    }
  }
}
