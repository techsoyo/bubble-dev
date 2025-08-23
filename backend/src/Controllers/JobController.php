<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class JobController
{
    /**
     * GET /api/jobs
     * Listar ofertas de trabajo
     */
    public function index(Request $request, array $params = [])
    {
        try {
            $page   = (int)($request->getQuery('page') ?? 1);
            $limit  = (int)($request->getQuery('limit') ?? 20);
            $search = trim((string)($request->getQuery('search') ?? ''));

            // TODO: fetchJobs($page, $limit, $search)
            $items = [];

            return ResponseHelper::success("Listado de trabajos obtenido", [
                'page'  => $page,
                'limit' => $limit,
                'total' => count($items), // TODO total real
                'data'  => $items
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al listar trabajos", $e);
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

            if (empty($data['title'])) {
                return ResponseHelper::fail("El campo 'title' es obligatorio", 422);
            }

            // TODO: Insertar en DB
            // $id = createJob($data);

            return ResponseHelper::success("Trabajo creado correctamente", [
                'id'   => 0, // id real
                'data' => $data
            ], 201);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al crear trabajo", $e);
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

            // TODO: Buscar en DB
            // $job = findJob($id);
            $job = null;

            if (!$job) {
                return ResponseHelper::fail("Trabajo no encontrado", 404);
            }

            return ResponseHelper::success("Trabajo encontrado", [
                'id'   => $id,
                'data' => $job
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener trabajo", $e);
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

            // TODO: updateJob($id, $data)

            return ResponseHelper::success("Trabajo actualizado correctamente", [
                'id'   => $id,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al actualizar trabajo", $e);
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

            // TODO: deleteJob($id)

            return ResponseHelper::success("Trabajo eliminado correctamente", [
                'id' => $id
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al eliminar trabajo", $e);
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
