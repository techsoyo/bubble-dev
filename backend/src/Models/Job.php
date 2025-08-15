<?php

namespace Models;

/**
 * Modelo para la entidad Job
 */
class Job extends BaseModel
{
    /**
     * Nombre de la tabla
     * @var string
     */
    protected string $table = 'jobs';

    /**
     * Encuentra trabajos por título
     *
     * @param string $title Título a buscar
     * @return array Lista de trabajos
     */
    public function findByTitle($title)
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE title LIKE ?",
            ['%' . $title . '%']
        );
    }

    /**
     * Encuentra trabajos por ubicación
     *
     * @param string $location Ubicación a buscar
     * @return array Lista de trabajos
     */
    public function findByLocation($location)
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE location LIKE ?",
            ['%' . $location . '%']
        );
    }

    /**
     * Encuentra trabajos por habilidades requeridas
     *
     * @param array $skills Lista de habilidades a buscar
     * @return array Lista de trabajos
     */
    public function findBySkills($skills)
    {
        // Construir la condición SQL para buscar en el JSON de habilidades
        $skillConditions = [];
        foreach ($skills as $skill) {
            $skillConditions[] = "JSON_CONTAINS(required_skills, '\"" . addslashes($skill) . "\"')";
        }

        $sql = "SELECT * FROM {$this->table} WHERE " . implode(' OR ', $skillConditions);

        return $this->query($sql);
    }

    /**
     * Encuentra trabajos activos
     *
     * @return array Lista de trabajos activos
     */
    public function findActive()
    {
        return $this->query(
            "SELECT * FROM {$this->table} WHERE status = 'active'"
        );
    }

    /**
     * Obtiene todos los trabajos con filtros
     *
     * @param array $filters Filtros a aplicar (title, location, skills, etc.)
     * @param int $page Número de página
     * @param int $limit Límite de resultados por página
     * @return array Lista de trabajos
     */
    public function findAll(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        // Para el MVP, generamos datos internos de referencia
        $jobs = [];

        // Generar 10 trabajos de referencia para desarrollo
        for ($i = 1; $i <= 10; $i++) {
            $jobs[] = [
                'id' => $i,
                'title' => 'Puesto de trabajo ' . $i,
                'company' => 'Empresa ' . $i,
                'location' => 'Ciudad ' . ($i % 3 + 1),
                'description' => 'Descripción del puesto de trabajo ' . $i,
                'required_skills' => ['PHP', 'JavaScript', 'MySQL'],
                'salary_range' => '30,000 - 45,000',
                'status' => $i <= 8 ? 'active' : 'inactive',
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        // Aplicar filtros sobre los datos de referencia
        if (!empty($filters)) {
            $filteredJobs = [];

            foreach ($jobs as $job) {
                // Filtrar por título
                if (isset($filters['title']) && !empty($filters['title'])) {
                    if (stripos($job['title'], $filters['title']) === false) {
                        continue;
                    }
                }

                // Filtrar por ubicación
                if (isset($filters['location']) && !empty($filters['location'])) {
                    if (stripos($job['location'], $filters['location']) === false) {
                        continue;
                    }
                }

                // Filtrar por habilidades
                if (isset($filters['skills']) && !empty($filters['skills'])) {
                    $hasSkill = false;

                    foreach ($filters['skills'] as $skill) {
                        if (in_array($skill, $job['required_skills'])) {
                            $hasSkill = true;
                            break;
                        }
                    }

                    if (!$hasSkill) {
                        continue;
                    }
                }

                // Filtrar por estado
                if (isset($filters['status']) && !empty($filters['status'])) {
                    if ($job['status'] !== $filters['status']) {
                        continue;
                    }
                }

                $filteredJobs[] = $job;
            }

            $jobs = $filteredJobs;
        }

        // Aplicar paginación
        $offset = ($page - 1) * $limit;
        $jobs = array_slice($jobs, $offset, $limit);

        return $jobs;
    }

    /**
     * Cuenta el número total de trabajos con filtros
     *
     * @param array $filters Filtros a aplicar
     * @return int Número total de trabajos
     */
    public function countAll(array $filters = []): int
    {
        // Para el MVP, devolvemos un valor fijo
        return 10;
    }
}
