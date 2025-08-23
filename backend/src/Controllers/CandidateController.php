<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class CandidateController
{
    /**
     * GET /api/candidates
     * Lista de candidatos (paginable/filtrable)
     */
    public function index(Request $request, array $params = [])
    {
        try {
            // Ejemplo de lectura de query params
            $page   = (int)($request->getQuery('page') ?? 1);
            $limit  = (int)($request->getQuery('limit') ?? 20);
            $search = trim((string)($request->getQuery('search') ?? ''));

            // TODO: Reemplazar por consulta real a DB
            $items = []; // fetchCandidates($page, $limit, $search)

            return ResponseHelper::success("Listado de candidatos obtenido", [
                'page'  => $page,
                'limit' => $limit,
                'total' => count($items), // TODO: total real
                'data'  => $items
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al listar candidatos", $e);
        }
    }

    /**
     * POST /api/candidates
     * Crear candidato (staff/admin)
     */
    public function store(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();

            // Validación mínima
            if (empty($data['email'])) {
                return ResponseHelper::fail("El campo 'email' es obligatorio", 422);
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ResponseHelper::fail("Formato de email inválido", 422);
            }

            // TODO: Insertar en DB
            // $id = createCandidate($data);

            return ResponseHelper::success("Candidato creado correctamente", [
                'id'   => 0, // reemplazar por $id real
                'data' => $data
            ], 201);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al crear candidato", $e);
        }
    }

    /**
     * POST /api/candidates/register
     * Registro self-service de candidato
     */
    public function register(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();

            if (empty($data['email']) || empty($data['password'])) {
                return ResponseHelper::fail("Email y password son obligatorios", 422);
            }
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ResponseHelper::fail("Formato de email inválido", 422);
            }

            // TODO: crear usuario candidato + hash password
            // $id = registerCandidate($data['email'], $data['password'], ...)

            return ResponseHelper::success("Registro de candidato exitoso", [
                'candidate_id' => 0, // id real
                'email'        => $data['email']
            ], 201);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en registro de candidato", $e);
        }
    }

    /**
     * GET /api/candidates/{id}
     * Detalle de candidato
     */
    public function show(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: Buscar en DB
            // $candidate = findCandidate($id);
            $candidate = null;

            if (!$candidate) {
                return ResponseHelper::fail("Candidato no encontrado", 404);
            }

            return ResponseHelper::success("Candidato encontrado", [
                'id'   => $id,
                'data' => $candidate
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener candidato", $e);
        }
    }

    /**
     * PUT /api/candidates/{id}
     * Actualizar candidato
     */
    public function update(Request $request, array $params = [])
    {
        try {
            $id   = $params['id'] ?? null;
            $data = $request->getBody();

            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // Validaciones básicas opcionales
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                return ResponseHelper::fail("Formato de email inválido", 422);
            }

            // TODO: Update en DB
            // updateCandidate($id, $data)

            return ResponseHelper::success("Candidato actualizado correctamente", [
                'id'   => $id,
                'data' => $data
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al actualizar candidato", $e);
        }
    }

    /**
     * DELETE /api/candidates/{id}
     * Eliminar candidato
     */
    public function delete(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: Delete en DB
            // deleteCandidate($id)

            return ResponseHelper::success("Candidato eliminado correctamente", [
                'id' => $id
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al eliminar candidato", $e);
        }
    }

    /**
     * POST /api/candidates/upload-cv
     * Subida de CV (multipart/form-data o base64)
     */
    public function uploadCV(Request $request, array $params = [])
    {
        try {
            // Puedes tener el archivo en $_FILES o como base64 en el body
            $fileInfo = $request->getFile('cv') ?? null;
            $body     = $request->getBody();

            if (!$fileInfo && empty($body['cv_base64'])) {
                return ResponseHelper::fail("Se requiere 'cv' (archivo) o 'cv_base64'", 422);
            }

            // TODO: Validaciones de tipo/tamaño y almacenamiento
            // $storedPath = storeCv($fileInfo || $body['cv_base64'])

            return ResponseHelper::success("CV subido correctamente", [
                'stored_path' => 'path/to/cv.pdf' // reemplazar por real
            ], 201);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al subir CV", $e);
        }
    }

    /**
     * GET /api/candidates/profile/{id}
     * Perfil extendido del candidato
     */
    public function profile(Request $request, array $params = [])
    {
        try {
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: Unir datos de varias tablas (experiencia, edu, skills…)
            $profile = [
                'candidate'   => ['id' => $id, 'name' => 'Mocked Candidate'],
                'experiences' => [],
                'education'   => [],
                'skills'      => [],
            ];

            return ResponseHelper::success("Perfil de candidato obtenido", $profile);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener perfil", $e);
        }
    }

    /**
     * PATCH /api/candidates/{id}/status
     * Cambiar estado del candidato
     */
    public function updateStatus(Request $request, array $params = [])
    {
        try {
            $id   = $params['id'] ?? null;
            $data = $request->getBody();
            $status = $data['status'] ?? null;

            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }
            if (!$status) {
                return ResponseHelper::fail("El campo 'status' es obligatorio", 422);
            }

            // TODO: Validar estado permitido y actualizar en DB
            // updateCandidateStatus($id, $status)

            return ResponseHelper::success("Estado del candidato actualizado", [
                'id'     => $id,
                'status' => $status
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al actualizar estado del candidato", $e);
        }
    }

    /**
     * GET /api/recruiters/assigned-candidates
     * Candidatos asignados a un recruiter (usado por Recruiter dashboard)
     */
    public function assignedCandidates(Request $request, array $params = [])
    {
        try {
            // Ejemplo: recruiter_id desde token/sesión o query param
            $recruiterId = $request->getQuery('recruiter_id') ?? null;
            // TODO: obtener recruiter_id real desde Auth

            // TODO: Consulta real
            $items = []; // findAssignedCandidates($recruiterId)

            return ResponseHelper::success("Candidatos asignados obtenidos", [
                'recruiter_id' => $recruiterId,
                'data'         => $items
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener candidatos asignados", $e);
        }
    }
}
