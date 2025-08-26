<?php declare(strict_types=1);

namespace Controllers\CandidateController.php\Controllers;

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
    // MÃ©todos CRUD - llamadas directas al modelo
    public function searchCandidates($filters = [], $page = 1, $limit = 20)
    {
        return $this->candidateModel->searchCandidates($filters, $page, $limit);
    }

    public function countCandidates($filters = [])
    {
        return $this->candidateModel->countCandidates($filters);
    }

    public function createCandidate($data)
    {
        return $this->candidateModel->createCandidate($data);
    }

    // MÃ©todos especÃ­ficos que mantienen lÃ³gica de negocio del controller
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

            // Usar mÃ©todo encapsulado del modelo
            $id = $this->candidateModel->createCandidate($data);

            if ($id === false) {
                return ResponseHelper::fail("Error en el registro - datos invÃ¡lidos", 422);
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
        return $this->candidateModel->getCandidate($id);
    }

    public function updateCandidate($id, $data)
    {
        return $this->candidateModel->updateCandidate($id, $data);
    }

    public function deleteCandidate($id)
    {
        return $this->candidateModel->deleteCandidate($id);
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

            // TODO: Validaciones de tipo/tamaÃ±o y almacenamiento
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
            $id = $params['id'] ?? null;
            if (!$id) {
                return ResponseHelper::fail("ID no proporcionado", 400);
            }

            // TODO: Unir datos de varias tablas (experiencia, edu, skillsâ€¦)
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

            // Preparar datos para actualizaciÃ³n usando el mÃ©todo encapsulado
            $updateData = [
                'status' => $status,
                'status_notes' => $data['notes'] ?? null
            ];

            // Usar mÃ©todo encapsulado del modelo que incluye validaciÃ³n de estado
            $result = $this->candidateModel->updateCandidate($id, $updateData);

            if ($result === false) {
                return ResponseHelper::fail("Error al actualizar estado - datos invÃ¡lidos", 422);
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
            // Ejemplo: recruiter_id desde token/sesiÃ³n o query param
            $recruiterId = $request->getQuery('recruiter_id') ?? null;
            // TODO: obtener recruiter_id real desde Auth

            // Construir filtros para candidatos asignados
            $filters = [];
            if ($recruiterId) {
                $filters['assigned_recruiter_id'] = $recruiterId;
            }

            // Usar mÃ©todo encapsulado del modelo
            $items = $this->candidateModel->searchCandidates($filters);

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
