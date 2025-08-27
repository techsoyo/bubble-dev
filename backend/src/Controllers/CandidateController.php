<?php

declare(strict_types=1);

namespace Controllers;

use Models\Candidate;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;

class CandidateController
{
    private Candidate $candidateModel;

    public function __construct()
    {
        $this->candidateModel = new Candidate();
    }
    // MÃƒÆ’Ã‚Â©todos CRUD - llamadas directas al modelo
    public function searchCandidates($filters = [], $page = 1, $limit = 20)
    {
        return $this->candidateModel->getCandidatesList($filters, $page, $limit);
    }

    public function countCandidates($filters = [])
    {
        // Use the paginated helper to obtain the total count
        $paginated = $this->candidateModel->getCandidatesPaginated($filters, 1, 1);
        return $paginated['pagination']['total'] ?? 0;
    }

    public function createCandidate($data)
    {
        // Use BaseModel::store to create a record
        return $this->candidateModel->store($data);
    }

    // MÃƒÆ’Ã‚Â©todos especÃƒÆ’Ã‚Â­ficos que mantienen lÃƒÆ’Ã‚Â³gica de negocio del controller
    public function register(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();

            // Preparar datos para registro
            if (empty($data['email']) || empty($data['password'])) {
                return ResponseHelper::fail("Email y password son obligatorios", 422);
            }

            // Hash del password
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']); // Remover password plano

            // Asignar valores por defecto para registro
            $data['registration_source'] = 'self-registration';
            $data['status'] = 'pending';

            // Usar método genérico de creación del modelo
            $id = $this->candidateModel->store($data);

            if ($id === false) {
                return ResponseHelper::fail("Error en el registro - datos invÃƒÆ’Ã‚Â¡lidos", 422);
            }

            return ResponseHelper::success("Registro de candidato exitoso", [
                'candidate_id' => $id,
                'email'        => $data['email']
            ], 201);
        } catch (\InvalidArgumentException $e) {
            Logger::warning('Validation error in CandidateController::register', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Logger::error('Error in CandidateController::register', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::error("Error en registro de candidato", $e);
        }
    }

    public function getCandidate($id)
    {
        $id = (int) $id;
        return $this->candidateModel->getCandidateProfile((string)$id);
    }

    public function updateCandidate($id, $data)
    {
        $id = (int) $id;
        return $this->candidateModel->update($id, $data);
    }

    public function deleteCandidate($id)
    {
        $id = (int) $id;
        return $this->candidateModel->delete($id);
    }

    public function uploadCV(Request $request, array $params = [])
    {
        try {
            // Usar $_FILES directamente ya que getFile() no existe en Request
            $fileInfo = $_FILES['cv'] ?? null;
            $body     = $request->getBody();

            if (!$fileInfo && empty($body['cv_base64'])) {
                return ResponseHelper::fail("Se requiere 'cv' (archivo) o 'cv_base64'", 422);
            }

            // TODO: Validaciones de tipo/tamaÃƒÆ’Ã‚Â±o y almacenamiento
            // $storedPath = storeCv($fileInfo || $body['cv_base64'])

            return ResponseHelper::success("CV subido correctamente", [
                'stored_path' => 'path/to/cv.pdf' // reemplazar por real
            ], 201);
        } catch (\Throwable $e) {
            Logger::error('Error in CandidateController::uploadCV', [
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::error("Error al subir CV", $e);
        }
    }

    public function profile(Request $request, array $params = [])
    {
        try {
            $id = isset($params['id']) ? (int)$params['id'] : 0;
            if ($id <= 0) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // Obtener datos reales desde el modelo
            $candidate = $this->candidateModel->getCandidateProfile((string)$id);
            if ($candidate === null) {
                return ResponseHelper::fail("Candidato no encontrado", 404);
            }

            $skills = $this->candidateModel->getCandidateSkills((string)$id);

            $profile = [
                'candidate'   => $candidate,
                'experiences' => [], // mantener si se integra luego
                'education'   => [],
                'skills'      => $skills,
            ];

            return ResponseHelper::success("Perfil de candidato obtenido", $profile);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener perfil", $e);
        }
    }

    public function updateStatus(Request $request, array $params = [])
    {
        try {
            $id   = isset($params['id']) ? (int)$params['id'] : 0;
            $data = $request->getBody();
            $status = $data['status'] ?? null;

            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }
            if (!$status) {
                return ResponseHelper::fail("El campo 'status' es obligatorio", 422);
            }

            // Usar el método específico del modelo
            $result = $this->candidateModel->updateStatus($id, $status, $data['notes'] ?? null);

            if ($result === false) {
                return ResponseHelper::fail("Error al actualizar estado - datos inválidos", 422);
            }

            return ResponseHelper::success("Estado del candidato actualizado", [
                'id'     => $id,
                'status' => $status
            ]);
        } catch (\InvalidArgumentException $e) {
            Logger::warning('Validation error in CandidateController::updateStatus', [
                'id' => $id ?? null,
                'status' => $status ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Logger::error('Error in CandidateController::updateStatus', [
                'id' => $id ?? null,
                'status' => $status ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::error("Error al actualizar estado del candidato", $e);
        }
    }

    public function assignedCandidates(Request $request, array $params = [])
    {
        try {
            // Ejemplo: recruiter_id desde token/sesiÃƒÆ’Ã‚Â³n o query param
            $recruiterId = $request->getQuery('recruiter_id') ?? null;
            $recruiterId = $recruiterId !== null ? (int)$recruiterId : null;

            // Construir filtros para candidatos asignados
            $filters = [];
            if ($recruiterId) {
                $filters['assigned_recruiter_id'] = $recruiterId;
            }

            // Usar método del modelo
            $items = $this->candidateModel->getCandidatesList($filters);

            return ResponseHelper::success("Candidatos asignados obtenidos", [
                'recruiter_id' => $recruiterId,
                'data'         => $items
            ]);
        } catch (\Throwable $e) {
            Logger::error('Error in CandidateController::assignedCandidates', [
                'recruiter_id' => $recruiterId ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::error("Error al obtener candidatos asignados", $e);
        }
    }
}
