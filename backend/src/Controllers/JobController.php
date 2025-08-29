<?php

declare(strict_types=1);

namespace Controllers;

use Models\Job;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;
use Services\RateLimitService;
use Services\SecurityLoggerService;
use Services\ValidationService;

class JobController
{
    private Job $model;

    public function __construct()
    {
        $this->model = new Job();
    }

    /**
     * GET /api/jobs
     * Listar ofertas de trabajo
     */
    public function index(Request $request, array $params = [])
    {
        try {
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // RATE LIMITING para consultas de trabajos
            if (!RateLimitService::canPerform('job_list', $clientIP)) {
                $retryAfter = RateLimitService::getRetryAfter('job_list', $clientIP);
                SecurityLoggerService::logRateLimitViolation('job_list', $clientIP, 100, 100);

                return ResponseHelper::fail('Demasiadas consultas. Intente nuevamente más tarde.', 429, [
                    'retry_after' => $retryAfter
                ]);
            }

            // VALIDACIÓN Y SANITIZACIÓN DE PARÁMETROS
            $rawFilters = $_GET ?? [];

            // Validar y sanitizar parámetros de paginación
            $page = isset($rawFilters['page']) ? max(1, (int)$rawFilters['page']) : 1;
            $limit = isset($rawFilters['limit']) ? max(1, min(50, (int)$rawFilters['limit'])) : 20;

            // Validar filtros de búsqueda
            $filters = [];
            if (isset($rawFilters['search'])) {
                $searchValidation = ValidationService::validateInput($rawFilters['search'], 'search', [
                    'max_length' => 100,
                    'required' => false
                ]);
                if ($searchValidation['valid']) {
                    $filters['search'] = $searchValidation['sanitized'];
                }
            }

            if (isset($rawFilters['status'])) {
                $allowedStatuses = ['active', 'inactive', 'draft', 'closed'];
                if (in_array($rawFilters['status'], $allowedStatuses)) {
                    $filters['status'] = $rawFilters['status'];
                }
            }

            if (isset($rawFilters['department_id'])) {
                $deptIdValidation = ValidationService::validateInput($rawFilters['department_id'], 'id', [
                    'required' => false,
                    'type' => 'integer',
                    'min' => 1
                ]);
                if ($deptIdValidation['valid']) {
                    $filters['department_id'] = $deptIdValidation['sanitized'];
                }
            }

            if (isset($rawFilters['location'])) {
                $locationValidation = ValidationService::validateInput($rawFilters['location'], 'text', [
                    'required' => false,
                    'max_length' => 100
                ]);
                if ($locationValidation['valid']) {
                    $filters['location'] = $locationValidation['sanitized'];
                }
            }

            // REGISTRAR ACCESO AUTORIZADO
            RateLimitService::recordAttempt('job_list', $clientIP);
            SecurityLoggerService::logSecurityEvent('job_list_accessed', [
                'ip_address' => $clientIP,
                'filters' => $filters,
                'page' => $page,
                'limit' => $limit
            ], 'INFO');

            // EJECUTAR CONSULTA
            $rows = $this->model->searchJobs($filters, $page, $limit, []);
            $total = $this->model->countJobs($filters);

            Logger::info('Jobs listed successfully', [
                'total_results' => $total,
                'page' => $page,
                'limit' => $limit,
                'ip_address' => $clientIP
            ]);

            return ResponseHelper::success("Listado de trabajos obtenido", [
                'data' => $rows,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], 200);
        } catch (\Throwable $e) {
            SecurityLoggerService::logSecurityEvent('job_list_error', [
                'error' => $e->getMessage(),
                'ip_address' => $clientIP ?? 'unknown'
            ], 'ERROR');

            Logger::error('Error listing jobs', ['error' => $e->getMessage()]);
            return ResponseHelper::error("Error al listar trabajos", null, 500);
        }
    }

    /**
     * POST /api/jobs
     * Crear oferta de trabajo
     */
    public function store(Request $request, array $params = [])
    {
        try {
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // RATE LIMITING para creación de trabajos
            if (!RateLimitService::canPerform('job_create', $clientIP)) {
                $retryAfter = RateLimitService::getRetryAfter('job_create', $clientIP);
                SecurityLoggerService::logRateLimitViolation('job_create', $clientIP, 10, 10);

                return ResponseHelper::fail('Demasiadas creaciones de trabajo. Intente nuevamente más tarde.', 429, [
                    'retry_after' => $retryAfter
                ]);
            }

            // OBTENER Y VALIDAR DATOS DE ENTRADA
            $data = $request->getBody();
            if (!is_array($data) || empty($data)) {
                SecurityLoggerService::logSecurityEvent('job_create_invalid_data', [
                    'ip_address' => $clientIP,
                    'data_type' => gettype($data)
                ], 'WARNING');

                return ResponseHelper::fail('Datos inválidos proporcionados', 400);
            }

            // VALIDACIÓN DE CAMPOS REQUERIDOS
            $requiredFields = ['title', 'description', 'department_id'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field]) || empty(trim((string)$data[$field]))) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                SecurityLoggerService::logSecurityEvent('job_create_missing_fields', [
                    'ip_address' => $clientIP,
                    'missing_fields' => $missingFields
                ], 'WARNING');

                return ResponseHelper::fail('Campos requeridos faltantes: ' . implode(', ', $missingFields), 400);
            }

            // VALIDACIÓN Y SANITIZACIÓN DE CAMPOS
            $validatedData = [];

            // Validar título
            $titleValidation = ValidationService::validateInput($data['title'], 'text', [
                'required' => true,
                'max_length' => 200,
                'min_length' => 5
            ]);
            if (!$titleValidation['valid']) {
                return ResponseHelper::fail('Título inválido: ' . implode(', ', $titleValidation['errors']), 400);
            }
            $validatedData['title'] = $titleValidation['sanitized'];

            // Validar descripción
            $descValidation = ValidationService::validateInput($data['description'], 'text', [
                'required' => true,
                'max_length' => 5000,
                'min_length' => 50
            ]);
            if (!$descValidation['valid']) {
                return ResponseHelper::fail('Descripción inválida: ' . implode(', ', $descValidation['errors']), 400);
            }
            $validatedData['description'] = $descValidation['sanitized'];

            // Validar department_id
            $deptIdValidation = ValidationService::validateInput($data['department_id'], 'id', [
                'required' => true,
                'type' => 'integer',
                'min' => 1
            ]);
            if (!$deptIdValidation['valid']) {
                return ResponseHelper::fail('ID de departamento inválido: ' . implode(', ', $deptIdValidation['errors']), 400);
            }
            $validatedData['department_id'] = $deptIdValidation['sanitized'];

            // Validar campos opcionales
            if (isset($data['location'])) {
                $locationValidation = ValidationService::validateInput($data['location'], 'text', [
                    'required' => false,
                    'max_length' => 100
                ]);
                if ($locationValidation['valid']) {
                    $validatedData['location'] = $locationValidation['sanitized'];
                }
            }

            if (isset($data['salary_min'])) {
                $salaryMinValidation = ValidationService::validateInput($data['salary_min'], 'salary', [
                    'required' => false,
                    'type' => 'numeric',
                    'min' => 0
                ]);
                if ($salaryMinValidation['valid']) {
                    $validatedData['salary_min'] = $salaryMinValidation['sanitized'];
                }
            }

            if (isset($data['salary_max'])) {
                $salaryMaxValidation = ValidationService::validateInput($data['salary_max'], 'salary', [
                    'required' => false,
                    'type' => 'numeric',
                    'min' => 0
                ]);
                if ($salaryMaxValidation['valid']) {
                    $validatedData['salary_max'] = $salaryMaxValidation['sanitized'];
                }
            }

            if (isset($data['requirements'])) {
                $reqValidation = ValidationService::validateInput($data['requirements'], 'text', [
                    'required' => false,
                    'max_length' => 2000
                ]);
                if ($reqValidation['valid']) {
                    $validatedData['requirements'] = $reqValidation['sanitized'];
                }
            }

            // Asignar valores por defecto
            $validatedData['status'] = $data['status'] ?? 'active';
            $validatedData['created_at'] = date('Y-m-d H:i:s');
            $validatedData['updated_at'] = date('Y-m-d H:i:s');

            // REGISTRAR ACCESO AUTORIZADO
            RateLimitService::recordAttempt('job_create', $clientIP);
            SecurityLoggerService::logSecurityEvent('job_create_attempt', [
                'ip_address' => $clientIP,
                'title' => $validatedData['title'],
                'department_id' => $validatedData['department_id']
            ], 'INFO');

            // CREAR TRABAJO
            $id = $this->model->createJob($validatedData);

            if ($id === false) {
                SecurityLoggerService::logSecurityEvent('job_create_failed', [
                    'ip_address' => $clientIP,
                    'title' => $validatedData['title'],
                    'reason' => 'Database insertion failed'
                ], 'WARNING');

                return ResponseHelper::fail('No se pudo crear el trabajo', 400);
            }

            Logger::info('Job created successfully', [
                'id' => $id,
                'title' => $validatedData['title'],
                'ip_address' => $clientIP
            ]);

            SecurityLoggerService::logSecurityEvent('job_create_success', [
                'ip_address' => $clientIP,
                'job_id' => $id,
                'title' => $validatedData['title']
            ], 'INFO');

            return ResponseHelper::success("Trabajo creado correctamente", ['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            SecurityLoggerService::logSecurityEvent('job_create_validation_error', [
                'ip_address' => $clientIP,
                'error' => $e->getMessage()
            ], 'WARNING');

            Logger::error('Validation failed creating job', ['error' => $e->getMessage()]);
            return ResponseHelper::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            SecurityLoggerService::logSecurityEvent('job_create_unexpected_error', [
                'ip_address' => $clientIP,
                'error' => $e->getMessage()
            ], 'ERROR');

            Logger::error('Unexpected error creating job', ['error' => $e->getMessage()]);
            return ResponseHelper::error("Error al crear trabajo", null, 500);
        }
    }

    /**
     * GET /api/jobs/{id}
     * Ver detalle de un trabajo
     */
    public function show(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            $row = $this->model->getJob($id);
            if (!$row) {
                return ResponseHelper::fail("Trabajo no encontrado", 404);
            }

            return ResponseHelper::success("Trabajo encontrado", $row, 200);
        } catch (\Throwable $e) {
            Logger::error('Error retrieving job', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error("Error al obtener trabajo", $e, 500);
        }
    }

    /**
     * PUT /api/jobs/{id}
     * Actualizar trabajo
     */
    public function update(Request $request, array $params = [])
    {
        try {
            $id   = $params['id'] ?? null;
            $data = $request->getBody();

            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            $ok = $this->model->updateJob($id, $data);

            if (!$ok) {
                return ResponseHelper::fail('Update failed', 400);
            }

            Logger::info('Job updated from controller', ['id' => $id]);
            return ResponseHelper::success("Trabajo actualizado correctamente", ['success' => true], 200);
        } catch (\InvalidArgumentException $e) {
            Logger::error('Validation failed updating job', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Logger::error('Unexpected error updating job', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error("Error al actualizar trabajo", $e, 500);
        }
    }

    /**
     * DELETE /api/jobs/{id}
     * Eliminar trabajo
     */
    public function delete(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            $ok = $this->model->deleteJob($id);
            if (!$ok) {
                return ResponseHelper::fail('Delete failed', 400);
            }

            Logger::info('Job deleted from controller', ['id' => $id]);
            return ResponseHelper::success("Trabajo eliminado correctamente", null, 204);
        } catch (\Throwable $e) {
            Logger::error('Unexpected error deleting job', ['id' => $id, 'error' => $e->getMessage()]);
            return ResponseHelper::error("Error al eliminar trabajo", $e, 500);
        }
    }

    /**
     * GET /api/job_skills/{id}
     * Habilidades requeridas de un trabajo
     */
    public function skills(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: fetchJobSkills($id)
            $skills = [];

            return ResponseHelper::success("Habilidades del trabajo obtenidas", [
                'job_id' => $id,
                'skills' => $skills
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener habilidades del trabajo", $e);
        }
    }

    /**
     * GET /api/job_requirements/{id}
     * Requisitos de un trabajo
     */
    public function requirements(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: fetchJobRequirements($id)
            $requirements = [];

            return ResponseHelper::success("Requisitos del trabajo obtenidos", [
                'job_id'       => $id,
                'requirements' => $requirements
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener requisitos del trabajo", $e);
        }
    }

    /**
     * GET /api/job_benefits/{id}
     * Beneficios de un trabajo
     */
    public function benefits(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: fetchJobBenefits($id)
            $benefits = [];

            return ResponseHelper::success("Beneficios del trabajo obtenidos", [
                'job_id'   => $id,
                'benefits' => $benefits
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener beneficios del trabajo", $e);
        }
    }
}
