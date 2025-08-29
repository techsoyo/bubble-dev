<?php

declare(strict_types=1);

namespace Controllers;

use Models\Candidate;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;
use Services\RateLimitService;
use Services\SecurityLoggerService;
use Services\ValidationService;

class CandidateController
{
    private Candidate $candidateModel;

    public function __construct()
    {
        $this->candidateModel = new Candidate();
    }
    // Métodos CRUD - llamadas directas al modelo
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

    // Métodos especí­ficos que mantienen lógica de negocio del controller
    public function register(Request $request, array $params = [])
    {
        try {
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

            // RATE LIMITING para registro de candidatos
            if (!RateLimitService::canPerform('candidate_register', $clientIP)) {
                $retryAfter = RateLimitService::getRetryAfter('candidate_register', $clientIP);
                SecurityLoggerService::logRateLimitViolation('candidate_register', $clientIP, 5, 5);

                return ResponseHelper::fail('Demasiados intentos de registro. Intente nuevamente más tarde.', 429, [
                    'retry_after' => $retryAfter
                ]);
            }

            $data = $request->getBody();

            // VALIDACIÓN DE CAMPOS REQUERIDOS
            if (empty($data['email']) || empty($data['password'])) {
                SecurityLoggerService::logSecurityEvent('candidate_register_missing_fields', [
                    'ip_address' => $clientIP,
                    'has_email' => !empty($data['email']),
                    'has_password' => !empty($data['password'])
                ], 'WARNING');

                return ResponseHelper::fail("Email y password son obligatorios", 422);
            }

            // VALIDACIÓN Y SANITIZACIÓN DE EMAIL
            $emailValidation = ValidationService::validateInput($data['email'], 'email', [
                'required' => true,
                'max_length' => 255
            ]);

            if (!$emailValidation['valid']) {
                SecurityLoggerService::logSecurityEvent('candidate_register_invalid_email', [
                    'ip_address' => $clientIP,
                    'provided_email' => $data['email'],
                    'validation_errors' => $emailValidation['errors']
                ], 'WARNING');

                return ResponseHelper::fail('Email inválido: ' . implode(', ', $emailValidation['errors']), 422);
            }

            // VALIDACIÓN Y SANITIZACIÓN DE CONTRASEÑA
            $passwordValidation = ValidationService::validateInput($data['password'], 'password', [
                'required' => true,
                'min_length' => 8,
                'max_length' => 128
            ]);

            if (!$passwordValidation['valid']) {
                SecurityLoggerService::logSecurityEvent('candidate_register_weak_password', [
                    'ip_address' => $clientIP,
                    'email' => $emailValidation['sanitized']
                ], 'WARNING');

                return ResponseHelper::fail('Contraseña inválida: ' . implode(', ', $passwordValidation['errors']), 422);
            }

            // VALIDACIÓN Y SANITIZACIÓN DE NOMBRE (si proporcionado)
            $validatedData = [
                'email' => $emailValidation['sanitized']
            ];

            if (isset($data['name'])) {
                $nameValidation = ValidationService::validateInput($data['name'], 'name', [
                    'required' => false,
                    'max_length' => 100,
                    'min_length' => 2
                ]);

                if ($nameValidation['valid']) {
                    $validatedData['name'] = $nameValidation['sanitized'];
                }
            }

            // Hash del password (usando password_hash nativo)
            $validatedData['password_hash'] = password_hash($passwordValidation['sanitized'], PASSWORD_DEFAULT);

            // Asignar valores por defecto para registro
            $validatedData['registration_source'] = 'self-registration';
            $validatedData['status'] = 'pending';
            $validatedData['created_at'] = date('Y-m-d H:i:s');
            $validatedData['updated_at'] = date('Y-m-d H:i:s');

            // REGISTRAR ACCESO AUTORIZADO
            RateLimitService::recordAttempt('candidate_register', $clientIP);
            SecurityLoggerService::logSecurityEvent('candidate_register_attempt', [
                'ip_address' => $clientIP,
                'email' => $validatedData['email']
            ], 'INFO');

            // Usar método genérico de creación del modelo
            $id = $this->candidateModel->store($validatedData);

            if ($id === false) {
                SecurityLoggerService::logSecurityEvent('candidate_register_failed', [
                    'ip_address' => $clientIP,
                    'email' => $validatedData['email'],
                    'reason' => 'Database insertion failed'
                ], 'WARNING');

                return ResponseHelper::fail("Error en el registro - datos inválidos", 422);
            }

            Logger::info('Candidate registered successfully', [
                'candidate_id' => $id,
                'email' => $validatedData['email'],
                'ip_address' => $clientIP
            ]);

            SecurityLoggerService::logSecurityEvent('candidate_register_success', [
                'ip_address' => $clientIP,
                'candidate_id' => $id,
                'email' => $validatedData['email']
            ], 'INFO');

            return ResponseHelper::success("Registro de candidato exitoso", [
                'candidate_id' => $id,
                'email' => $validatedData['email']
            ], 201);
        } catch (\InvalidArgumentException $e) {
            SecurityLoggerService::logSecurityEvent('candidate_register_validation_error', [
                'ip_address' => $clientIP,
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage()
            ], 'WARNING');

            Logger::warning('Validation error in CandidateController::register', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::fail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            SecurityLoggerService::logSecurityEvent('candidate_register_unexpected_error', [
                'ip_address' => $clientIP,
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage()
            ], 'ERROR');

            Logger::error('Error in CandidateController::register', [
                'email' => $data['email'] ?? null,
                'error' => $e->getMessage()
            ]);
            return ResponseHelper::error("Error en registro de candidato", null, 500);
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

            // TODO: Validaciones de tipo/tamaí±o y almacenamiento
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
            // Ejemplo: recruiter_id desde token/sesión o query param
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
