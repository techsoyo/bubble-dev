<?php

namespace Controllers;

use Models\Job;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

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
            $filters = $_GET ?? [];
            $page    = max(1, (int)($filters['page'] ?? 1));
            $limit   = (int)($filters['limit'] ?? 20);
            $orderBy = [];

            $rows  = $this->model->searchJobs($filters, $page, $limit, $orderBy);
            $total = $this->model->countJobs($filters);

            return ResponseHelper::success("Listado de trabajos obtenido", [
                'data' => $rows,
                'total' => $total,
                'page' => $page,
                'limit' => $limit
            ], 200);
        } catch (\Throwable $e) {
            Logger::error('Error listing jobs', ['error' => $e->getMessage()]);
            return ResponseHelper::error("Error al listar trabajos", $e, 500);
        }
    }

    /**
     * POST /api/jobs
     * Crear oferta de trabajo
     */
    public function store(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();

            $id = $this->model->createJob($data);

            if ($id === false) {
                return ResponseHelper::fail('Unable to create job', 400);
            }

            Logger::info('Job created from controller', ['id' => $id]);
            return ResponseHelper::success("Trabajo creado correctamente", ['id' => $id], 201);
        } catch (\InvalidArgumentException $e) {
            Logger::error('Validation failed creating job', ['error' => $e->getMessage()]);
            return ResponseHelper::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Logger::error('Unexpected error creating job', ['error' => $e->getMessage()]);
            return ResponseHelper::error("Error al crear trabajo", $e, 500);
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
