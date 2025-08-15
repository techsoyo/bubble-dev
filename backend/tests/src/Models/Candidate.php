<?php

namespace Models;

/**
 * Modelo para la entidad Candidate
 */
class Candidate extends BaseModel
{
    /**
     * Nombre de la tabla
     * @var string
     */
    protected string $table = 'candidates';

    /**
     * Encuentra un candidato por su ID de usuario
     *
     * @param int $userId ID del usuario asociado
     * @return array|false Candidato encontrado o false si no existe
     */
    public function findByUserId($userId)
    {
        return $this->findOneBy('user_id', $userId);
    }

    /**
     * Crea un nuevo candidato
     *
     * @param array $data Datos del candidato
     * @return int|false ID del candidato creado o false si falla
     */
    public function create($data)
    {
        return parent::create($data);
    }

    /**
     * Actualiza la información del CV de un candidato
     *
     * @param int $id ID del candidato
     * @param string $cvPath Ruta al archivo del CV
     * @param array $parsedData Datos extraídos del CV
     * @return bool True si la actualización es exitosa, false en caso contrario
     */
    public function updateCVInfo($id, $cvPath, $parsedData)
    {
        $data = [
          'cv_file_path' => $cvPath,
          'cv_parsed_data' => json_encode($parsedData),
          'cv_updated_at' => date('Y-m-d H:i:s')
        ];

        return $this->update($id, $data);
    }

    /**
     * Busca candidatos por habilidades
     *
     * @param array $skills Lista de habilidades a buscar
     * @param int $limit Límite de resultados
     * @param int $offset Offset para paginación
     * @return array Lista de candidatos que coinciden con las habilidades
     */
    public function searchBySkills($skills, $limit = 10, $offset = 0)
    {
        // Construir la condición SQL para buscar en el JSON de habilidades
        $skillConditions = [];
        foreach ($skills as $skill) {
            $skillConditions[] = "JSON_CONTAINS(skills, '\"" . addslashes($skill) . "\"')";
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' OR ', $skillConditions);
        $sql .= ' LIMIT ? OFFSET ?';

        return $this->query($sql, [$limit, $offset]);
    }

    /**
     * Obtiene los candidatos que mejor coinciden con una oferta de trabajo
     *
     * @param array $jobRequirements Requisitos del trabajo
     * @param int $limit Límite de resultados
     * @return array Lista de candidatos ordenados por coincidencia
     */
    public function getMatchingCandidates($jobRequirements, $limit = 10)
    {
        // Placeholder temporal: devolver candidatos con límite
        // TODO: implementar matching real con SQL/IA
        return parent::findAll([], 1, $limit, []);
    }

    /**
     * Actualiza el estado de un candidato
     *
     * @param int $id ID del candidato
     * @param string $status Nuevo estado
     * @param string|null $notes Notas opcionales sobre el cambio de estado
     * @return bool True si la actualización es exitosa, false en caso contrario
     */
    public function updateStatus($id, $status, $notes = null)
    {
        $data = [
          'status' => $status,
          'status_updated_at' => date('Y-m-d H:i:s')
        ];

        if ($notes !== null) {
            $data['status_notes'] = $notes;
        }

        return $this->update($id, $data);
    }

    /**
     * Obtiene todos los candidatos con filtros
     *
     * @param array $filters Filtros a aplicar (skill, location, experience, search)
     * @param int $page Número de página
     * @param int $limit Límite de resultados por página
     * @return array Lista de candidatos
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        // Delegar en la implementación del BaseModel (consultas reales)
        return parent::findAll($filters, $page, $limit, $orderBy);
    }

    /**
     * Cuenta el número total de candidatos con filtros
     *
     * @param array $filters Filtros a aplicar
     * @return int Número total de candidatos
     */
    public function countAll(array $filters = []): int
    {
        return parent::countAll($filters);
    }
}
