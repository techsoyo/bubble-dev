<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;

/**
 * Modelo para gestiÃƒÆ’Ã‚Â³n de habilidades (skills)
 * 
 * Gestiona las habilidades del sistema con soporte para categorizaciÃƒÆ’Ã‚Â³n,
 * niveles de competencia y extracciÃƒÆ’Ã‚Â³n desde texto.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 1.0.0
 * @since 2025-08-24
 */
class Skill extends BaseModel
{
  protected string $table = 'skills';

  protected array $fillable = [
    'name',
    'category',
    'description',
    'level',
    'is_active'
  ];

  protected array $hidden = [];

  /**
   * CategorÃƒÆ’Ã‚Â­as vÃƒÆ’Ã‚Â¡lidas de habilidades
   */
  const VALID_CATEGORIES = [
    'technical',
    'soft',
    'language',
    'tool',
    'framework',
    'certification'
  ];

  /**
   * Niveles vÃƒÆ’Ã‚Â¡lidos de habilidad
   */
  const VALID_LEVELS = [
    'beginner',
    'intermediate',
    'advanced',
    'expert'
  ];

  /**
   * Crear una nueva habilidad
   * 
   * @param array $data Datos de la habilidad
   * @return int|false ID de la habilidad creada o false si falla
   */
  public function createSkill(array $data): int|false
  {
    try {
      $this->validateSkillData($data);
      // Filtrar solo los campos permitidos por $fillable
      $filtered = [];
      foreach ($this->fillable as $field) {
        if (array_key_exists($field, $data)) {
          $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
        }
      }
      // Valores por defecto
      if (!isset($filtered['category'])) {
        $filtered['category'] = 'technical';
      }
      if (!isset($filtered['description'])) {
        $filtered['description'] = '';
      }
      if (!isset($filtered['level'])) {
        $filtered['level'] = 'beginner';
      }
      if (!isset($filtered['is_active'])) {
        $filtered['is_active'] = true;
      }

      $id = $this->store($filtered);

      if ($id) {
        Logger::info('Skill created successfully', ['id' => $id, 'name' => $filtered['name'] ?? null]);
        return $id;
      }

      return false;
    } catch (\Exception $e) {
      Logger::error('Error creating skill', ['error' => $e->getMessage(), 'data' => $data]);
      throw $e;
    }
  }

  /**
   * Obtener una habilidad por ID
   * 
   * @param int $id ID de la habilidad
   * @return array|null Datos de la habilidad o null si no existe
   */
  public function getSkill(int $id): ?array
  {
    try {
      $skill = $this->findById($id);

      if ($skill) {
        Logger::debug('Skill retrieved successfully', ['id' => $id]);
      }

      return $skill;
    } catch (\Exception $e) {
      Logger::error('Error retrieving skill', ['id' => $id, 'error' => $e->getMessage()]);
      return null;
    }
  }

  /**
   * Actualizar una habilidad
   * 
   * @param int $id ID de la habilidad
   * @param array $data Datos a actualizar
   * @return bool True si se actualizÃƒÆ’Ã‚Â³ correctamente
   */
  public function updateSkill(int $id, array $data): bool
  {
    try {
      // Verificar que existe
      if (!$this->findById($id)) {
        return false;
      }

      $this->validateSkillData($data, false);

      // Filtrar solo los campos permitidos por $fillable
      $filtered = [];
      foreach ($this->fillable as $field) {
        if (array_key_exists($field, $data)) {
          $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
        }
      }
      // Eliminar nulos para no sobreescribir con null
      $filtered = array_filter($filtered, fn($value) => $value !== null);

      $result = $this->update($id, $filtered);

      if ($result) {
        Logger::info('Skill updated successfully', ['id' => $id]);
      }

      return $result;
    } catch (\Exception $e) {
      Logger::error('Error updating skill', ['id' => $id, 'error' => $e->getMessage()]);
      throw $e;
    }
  }

  /**
   * Eliminar una habilidad
   * 
   * @param int $id ID de la habilidad
   * @return bool True si se eliminÃƒÆ’Ã‚Â³ correctamente
   */
  public function deleteSkill(int $id): bool
  {
    try {
      $result = $this->delete($id);

      if ($result) {
        Logger::info('Skill deleted successfully', ['id' => $id]);
      }

      return $result;
    } catch (\Exception $e) {
      Logger::error('Error deleting skill', ['id' => $id, 'error' => $e->getMessage()]);
      return false;
    }
  }

  /**
   * Buscar habilidades
   * 
   * @param array $criteria Criterios de bÃƒÆ’Ã‚Âºsqueda
   * @param int $limit LÃƒÆ’Ã‚Â­mite de resultados
   * @param int $offset Offset para paginaciÃƒÆ’Ã‚Â³n
   * @return array Lista de habilidades encontradas
   */
  public function searchSkills(array $criteria = [], int $limit = 50, int $offset = 0): array
  {
    try {
      $filters = [];

      if (!empty($criteria['name'])) {
        $filters['name'] = ['LIKE', '%' . $criteria['name'] . '%'];
      }

      if (!empty($criteria['category'])) {
        $filters['category'] = $criteria['category'];
      }

      if (!empty($criteria['level'])) {
        $filters['level'] = $criteria['level'];
      }

      if (isset($criteria['is_active'])) {
        $filters['is_active'] = $criteria['is_active'];
      }

      $orderBy = ['name' => 'ASC'];
      $page = intval($offset / $limit) + 1;

      return $this->findAll($filters, $page, $limit, $orderBy);
    } catch (\Exception $e) {
      Logger::error('Error searching skills', ['criteria' => $criteria, 'error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Contar habilidades segÃƒÆ’Ã‚Âºn criterios
   * 
   * @param array $criteria Criterios de bÃƒÆ’Ã‚Âºsqueda
   * @return int NÃƒÆ’Ã‚Âºmero de habilidades encontradas
   */
  public function countSkills(array $criteria = []): int
  {
    try {
      $filters = [];

      if (!empty($criteria['name'])) {
        $filters['name'] = ['LIKE', '%' . $criteria['name'] . '%'];
      }

      if (!empty($criteria['category'])) {
        $filters['category'] = $criteria['category'];
      }

      if (!empty($criteria['level'])) {
        $filters['level'] = $criteria['level'];
      }

      if (isset($criteria['is_active'])) {
        $filters['is_active'] = $criteria['is_active'];
      }

      return $this->countAll($filters);
    } catch (\Exception $e) {
      Logger::error('Error counting skills', ['criteria' => $criteria, 'error' => $e->getMessage()]);
      return 0;
    }
  }

  /**
   * Extraer habilidades desde texto
   * 
   * @param string $text Texto del CV o descripciÃƒÆ’Ã‚Â³n
   * @return array Habilidades extraÃƒÆ’Ã‚Â­das
   */
  public function extractSkillsFromText(string $text): array
  {
    try {
      // Lista bÃƒÆ’Ã‚Â¡sica de habilidades tÃƒÆ’Ã‚Â©cnicas comunes
      $commonSkills = [
        // Lenguajes de programaciÃƒÆ’Ã‚Â³n
        'PHP',
        'JavaScript',
        'Python',
        'Java',
        'C#',
        'C++',
        'Ruby',
        'Go',
        'Rust',
        // Frameworks web
        'Laravel',
        'Symfony',
        'React',
        'Vue.js',
        'Angular',
        'Node.js',
        'Express',
        // Bases de datos
        'MySQL',
        'PostgreSQL',
        'MongoDB',
        'Redis',
        'Oracle',
        'SQL Server',
        // DevOps y herramientas
        'Docker',
        'Kubernetes',
        'AWS',
        'Azure',
        'GCP',
        'Jenkins',
        'Git',
        // Otros
        'HTML',
        'CSS',
        'SASS',
        'TypeScript',
        'GraphQL',
        'REST API'
      ];

      $foundSkills = [];
      $textUpper = strtoupper($text);

      foreach ($commonSkills as $skill) {
        if (strpos($textUpper, strtoupper($skill)) !== false) {
          $foundSkills[] = [
            'name' => $skill,
            'category' => $this->categorizeSkill($skill),
            'confidence' => 0.8 // Confidence score bÃƒÆ’Ã‚Â¡sico
          ];
        }
      }

      Logger::info('Skills extracted from text', [
        'found_count' => count($foundSkills),
        'text_length' => strlen($text)
      ]);

      return $foundSkills;
    } catch (\Exception $e) {
      Logger::error('Error extracting skills from text', ['error' => $e->getMessage()]);
      return [];
    }
  }

  /**
   * Validar datos de habilidad
   * 
   * @param array $data Datos a validar
   * @param bool $isCreation Si es una creaciÃƒÆ’Ã‚Â³n (requiere todos los campos)
   * @throws \InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
   */
  private function validateSkillData(array $data, bool $isCreation = true): void
  {
    if ($isCreation && empty($data['name'])) {
      throw new \InvalidArgumentException('El nombre de la habilidad es requerido');
    }

    if (isset($data['name']) && strlen(trim($data['name'])) < 2) {
      throw new \InvalidArgumentException('El nombre de la habilidad debe tener al menos 2 caracteres');
    }

    if (isset($data['category']) && !in_array($data['category'], self::VALID_CATEGORIES)) {
      throw new \InvalidArgumentException('CategorÃƒÆ’Ã‚Â­a de habilidad no vÃƒÆ’Ã‚Â¡lida');
    }

    if (isset($data['level']) && !in_array($data['level'], self::VALID_LEVELS)) {
      throw new \InvalidArgumentException('Nivel de habilidad no vÃƒÆ’Ã‚Â¡lido');
    }
  }

  /**
   * Categorizar automÃƒÆ’Ã‚Â¡ticamente una habilidad
   * 
   * @param string $skillName Nombre de la habilidad
   * @return string CategorÃƒÆ’Ã‚Â­a asignada
   */
  private function categorizeSkill(string $skillName): string
  {
    $skillUpper = strtoupper($skillName);

    // Lenguajes de programaciÃƒÆ’Ã‚Â³n
    $languages = ['PHP', 'JAVASCRIPT', 'PYTHON', 'JAVA', 'C#', 'C++', 'RUBY', 'GO', 'RUST'];
    if (in_array($skillUpper, $languages)) {
      return 'technical';
    }

    // Frameworks
    $frameworks = ['LARAVEL', 'SYMFONY', 'REACT', 'VUE', 'ANGULAR', 'NODE'];
    foreach ($frameworks as $framework) {
      if (strpos($skillUpper, $framework) !== false) {
        return 'framework';
      }
    }

    // Herramientas
    $tools = ['DOCKER', 'KUBERNETES', 'GIT', 'JENKINS'];
    if (in_array($skillUpper, $tools)) {
      return 'tool';
    }

    return 'technical'; // Por defecto
  }
}
