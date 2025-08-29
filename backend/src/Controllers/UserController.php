<?php

declare(strict_types=1);

namespace Controllers;

use Models\User;
use Utils\Request;
use Utils\ResponseHelper;
use Utils\Logger;
use Services\RateLimitService;
use Services\SecurityLoggerService;
use Services\ValidationService;

class UserController extends BaseController
{
  private User $model;

  public function __construct()
  {
    parent::__construct();
    $this->model = new User();
  }

  public function index(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para consultas de usuarios
      if (!RateLimitService::canPerform('api_general', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('api_general', $clientIP);
        SecurityLoggerService::logRateLimitViolation('api_general', $clientIP, 100, 100);

        return ResponseHelper::fail('Demasiadas consultas. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
      }

      // VALIDACIÓN Y SANITIZACIÓN DE PARÁMETROS
      $rawFilters = $_GET ?? [];

      // Validar y sanitizar parámetros de paginación
      $page = isset($rawFilters['page']) ? max(1, (int)$rawFilters['page']) : 1;
      $limit = isset($rawFilters['limit']) ? max(1, min(100, (int)$rawFilters['limit'])) : 20;

      // Validar y sanitizar orderBy
      $orderBy = $this->sanitizeOrder($rawFilters['orderBy'] ?? []);

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

      if (isset($rawFilters['role'])) {
        $allowedRoles = ['admin', 'hr', 'recruiter', 'staff', 'candidate'];
        if (in_array($rawFilters['role'], $allowedRoles)) {
          $filters['role'] = $rawFilters['role'];
        }
      }

      if (isset($rawFilters['status'])) {
        $allowedStatuses = ['active', 'inactive', 'disabled'];
        if (in_array($rawFilters['status'], $allowedStatuses)) {
          $filters['status'] = $rawFilters['status'];
        }
      }

      // REGISTRAR ACCESO AUTORIZADO
      RateLimitService::recordAttempt('api_general', $clientIP);
      SecurityLoggerService::logSecurityEvent('user_list_accessed', [
        'ip_address' => $clientIP,
        'filters' => $filters,
        'page' => $page,
        'limit' => $limit
      ], 'INFO');

      // EJECUTAR CONSULTA
      $rows = User::searchUsers($filters, $limit, ($page - 1) * $limit);
      $total = count($rows); // TODO: Implementar countUsers en modelo

      Logger::info('Users listed successfully', [
        'total_results' => $total,
        'page' => $page,
        'limit' => $limit,
        'ip_address' => $clientIP
      ]);

      return ResponseHelper::success('Lista de usuarios', [
        'data' => $rows,
        'total' => $total,
        'page' => $page,
        'limit' => $limit
      ], 200);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('user_list_error', [
        'error' => $e->getMessage(),
        'ip_address' => $clientIP ?? 'unknown'
      ], 'ERROR');

      Logger::error('Error listing users', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al listar usuarios', null, 500);
    }
  }

  public function show(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para consultas individuales
      if (!RateLimitService::canPerform('user_detail', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('user_detail', $clientIP);
        SecurityLoggerService::logRateLimitViolation('user_detail', $clientIP, 30, 30);

        return ResponseHelper::fail('Demasiadas consultas. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
      }

      // VALIDACIÓN DEL ID
      $id = $params['id'] ?? null;
      if (!$id) {
        SecurityLoggerService::logSecurityEvent('user_detail_missing_id', [
          'ip_address' => $clientIP,
          'params' => $params
        ], 'WARNING');

        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      // Validar formato del ID
      $idValidation = ValidationService::validateInput($id, 'id', [
        'required' => true,
        'type' => 'integer',
        'min' => 1
      ]);

      if (!$idValidation['valid']) {
        SecurityLoggerService::logSecurityEvent('user_detail_invalid_id', [
          'ip_address' => $clientIP,
          'provided_id' => $id,
          'validation_errors' => $idValidation['errors']
        ], 'WARNING');

        return ResponseHelper::fail('ID inválido', 400);
      }

      $userId = (int)$idValidation['sanitized'];

      // REGISTRAR ACCESO AUTORIZADO
      RateLimitService::recordAttempt('user_detail', $clientIP);
      SecurityLoggerService::logSecurityEvent('user_detail_accessed', [
        'ip_address' => $clientIP,
        'user_id' => $userId
      ], 'INFO');

      // OBTENER USUARIO
      $row = User::getUser($userId);
      if (!$row) {
        SecurityLoggerService::logSecurityEvent('user_detail_not_found', [
          'ip_address' => $clientIP,
          'user_id' => $userId
        ], 'INFO');

        return ResponseHelper::fail('Usuario no encontrado', 404);
      }

      Logger::info('User detail retrieved successfully', [
        'user_id' => $userId,
        'ip_address' => $clientIP
      ]);

      return ResponseHelper::success("Detalle usuario $userId", $row, 200);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('user_detail_error', [
        'error' => $e->getMessage(),
        'ip_address' => $clientIP ?? 'unknown',
        'user_id' => $userId ?? null
      ], 'ERROR');

      Logger::error('Error retrieving user', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ]);
      return ResponseHelper::error('Error al obtener usuario', null, 500);
    }
  }

  public function store(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para creación de usuarios
      if (!RateLimitService::canPerform('user_create', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('user_create', $clientIP);
        SecurityLoggerService::logRateLimitViolation('user_create', $clientIP, 10, 10);

        return ResponseHelper::fail('Demasiadas creaciones de usuario. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
      }

      // OBTENER Y VALIDAR DATOS DE ENTRADA
      $data = $request->getBody();
      if (!is_array($data) || empty($data)) {
        SecurityLoggerService::logSecurityEvent('user_create_invalid_data', [
          'ip_address' => $clientIP,
          'data_type' => gettype($data)
        ], 'WARNING');

        return ResponseHelper::fail('Datos inválidos proporcionados', 400);
      }

      // VALIDACIÓN DE CAMPOS REQUERIDOS
      $requiredFields = ['name', 'email', 'password'];
      $missingFields = [];
      foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
          $missingFields[] = $field;
        }
      }

      if (!empty($missingFields)) {
        SecurityLoggerService::logSecurityEvent('user_create_missing_fields', [
          'ip_address' => $clientIP,
          'missing_fields' => $missingFields
        ], 'WARNING');

        return ResponseHelper::fail('Campos requeridos faltantes: ' . implode(', ', $missingFields), 400);
      }

      // VALIDACIÓN Y SANITIZACIÓN DE CAMPOS
      $validatedData = [];

      // Validar nombre
      $nameValidation = ValidationService::validateInput($data['name'], 'name', [
        'required' => true,
        'max_length' => 100,
        'min_length' => 2
      ]);
      if (!$nameValidation['valid']) {
        return ResponseHelper::fail('Nombre inválido: ' . implode(', ', $nameValidation['errors']), 400);
      }
      $validatedData['name'] = $nameValidation['sanitized'];

      // Validar email
      $emailValidation = ValidationService::validateInput($data['email'], 'email', [
        'required' => true,
        'max_length' => 255
      ]);
      if (!$emailValidation['valid']) {
        return ResponseHelper::fail('Email inválido: ' . implode(', ', $emailValidation['errors']), 400);
      }
      $validatedData['email'] = $emailValidation['sanitized'];

      // Validar contraseña
      $passwordValidation = ValidationService::validateInput($data['password'], 'password', [
        'required' => true,
        'min_length' => 8,
        'max_length' => 128
      ]);
      if (!$passwordValidation['valid']) {
        return ResponseHelper::fail('Contraseña inválida: ' . implode(', ', $passwordValidation['errors']), 400);
      }
      $validatedData['password'] = $passwordValidation['sanitized'];

      // Validar rol (opcional)
      if (isset($data['role'])) {
        $allowedRoles = ['admin', 'hr', 'recruiter', 'staff', 'candidate'];
        if (!in_array($data['role'], $allowedRoles)) {
          return ResponseHelper::fail('Rol inválido', 400);
        }
        $validatedData['role'] = $data['role'];
      }

      // Validar estado (opcional)
      if (isset($data['status'])) {
        $allowedStatuses = ['active', 'inactive', 'disabled'];
        if (!in_array($data['status'], $allowedStatuses)) {
          return ResponseHelper::fail('Estado inválido', 400);
        }
        $validatedData['status'] = $data['status'];
      }

      // REGISTRAR ACCESO AUTORIZADO
      RateLimitService::recordAttempt('user_create', $clientIP);
      SecurityLoggerService::logSecurityEvent('user_create_attempt', [
        'ip_address' => $clientIP,
        'email' => $validatedData['email'],
        'role' => $validatedData['role'] ?? 'candidate'
      ], 'INFO');

      // CREAR USUARIO
      $id = User::createUser($validatedData);

      if ($id === false) {
        SecurityLoggerService::logSecurityEvent('user_create_failed', [
          'ip_address' => $clientIP,
          'email' => $validatedData['email'],
          'reason' => 'Database insertion failed'
        ], 'WARNING');

        return ResponseHelper::fail('No se pudo crear el usuario', 400);
      }

      Logger::info('User created successfully', [
        'id' => $id,
        'email' => $validatedData['email'],
        'ip_address' => $clientIP
      ]);

      SecurityLoggerService::logSecurityEvent('user_create_success', [
        'ip_address' => $clientIP,
        'user_id' => $id,
        'email' => $validatedData['email']
      ], 'INFO');

      return ResponseHelper::success('Usuario creado exitosamente', ['id' => $id], 201);
    } catch (\InvalidArgumentException $e) {
      SecurityLoggerService::logSecurityEvent('user_create_validation_error', [
        'ip_address' => $clientIP,
        'error' => $e->getMessage()
      ], 'WARNING');

      Logger::error('Validation failed creating user', ['error' => $e->getMessage()]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('user_create_unexpected_error', [
        'ip_address' => $clientIP,
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('Unexpected error creating user', ['error' => $e->getMessage()]);
      return ResponseHelper::error('Error al crear usuario', null, 500);
    }
  }

  public function update(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para actualizaciones de usuarios
      if (!RateLimitService::canPerform('user_update', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('user_update', $clientIP);
        SecurityLoggerService::logRateLimitViolation('user_update', $clientIP, 20, 20);

        return ResponseHelper::fail('Demasiadas actualizaciones. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
      }

      // VALIDACIÓN DEL ID
      $id = $params['id'] ?? null;
      if (!$id) {
        SecurityLoggerService::logSecurityEvent('user_update_missing_id', [
          'ip_address' => $clientIP,
          'params' => $params
        ], 'WARNING');

        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $idValidation = ValidationService::validateInput($id, 'id', [
        'required' => true,
        'type' => 'integer',
        'min' => 1
      ]);

      if (!$idValidation['valid']) {
        SecurityLoggerService::logSecurityEvent('user_update_invalid_id', [
          'ip_address' => $clientIP,
          'provided_id' => $id,
          'validation_errors' => $idValidation['errors']
        ], 'WARNING');

        return ResponseHelper::fail('ID inválido', 400);
      }

      $userId = (int)$idValidation['sanitized'];

      // OBTENER Y VALIDAR DATOS DE ENTRADA
      $data = $request->getBody();
      if (!is_array($data) || empty($data)) {
        SecurityLoggerService::logSecurityEvent('user_update_invalid_data', [
          'ip_address' => $clientIP,
          'user_id' => $userId,
          'data_type' => gettype($data)
        ], 'WARNING');

        return ResponseHelper::fail('Datos inválidos proporcionados', 400);
      }

      // VALIDACIÓN Y SANITIZACIÓN DE CAMPOS
      $validatedData = [];

      // Validar nombre (opcional)
      if (isset($data['name'])) {
        $nameValidation = ValidationService::validateInput($data['name'], 'name', [
          'required' => false,
          'max_length' => 100,
          'min_length' => 2
        ]);
        if (!$nameValidation['valid']) {
          return ResponseHelper::fail('Nombre inválido: ' . implode(', ', $nameValidation['errors']), 400);
        }
        $validatedData['name'] = $nameValidation['sanitized'];
      }

      // Validar email (opcional)
      if (isset($data['email'])) {
        $emailValidation = ValidationService::validateInput($data['email'], 'email', [
          'required' => false,
          'max_length' => 255
        ]);
        if (!$emailValidation['valid']) {
          return ResponseHelper::fail('Email inválido: ' . implode(', ', $emailValidation['errors']), 400);
        }
        $validatedData['email'] = $emailValidation['sanitized'];
      }

      // Validar rol (opcional)
      if (isset($data['role'])) {
        $allowedRoles = ['admin', 'hr', 'recruiter', 'staff', 'candidate'];
        if (!in_array($data['role'], $allowedRoles)) {
          return ResponseHelper::fail('Rol inválido', 400);
        }
        $validatedData['role'] = $data['role'];
      }

      // Validar estado (opcional)
      if (isset($data['status'])) {
        $allowedStatuses = ['active', 'inactive', 'disabled'];
        if (!in_array($data['status'], $allowedStatuses)) {
          return ResponseHelper::fail('Estado inválido', 400);
        }
        $validatedData['status'] = $data['status'];
      }

      // Verificar que al menos un campo sea actualizado
      if (empty($validatedData)) {
        SecurityLoggerService::logSecurityEvent('user_update_no_changes', [
          'ip_address' => $clientIP,
          'user_id' => $userId
        ], 'INFO');

        return ResponseHelper::fail('No se proporcionaron campos para actualizar', 400);
      }

      // REGISTRAR ACCESO AUTORIZADO
      RateLimitService::recordAttempt('user_update', $clientIP);
      SecurityLoggerService::logSecurityEvent('user_update_attempt', [
        'ip_address' => $clientIP,
        'user_id' => $userId,
        'fields_to_update' => array_keys($validatedData)
      ], 'INFO');

      // ACTUALIZAR USUARIO
      $ok = $this->model->update($userId, $validatedData);

      if (!$ok) {
        SecurityLoggerService::logSecurityEvent('user_update_failed', [
          'ip_address' => $clientIP,
          'user_id' => $userId,
          'reason' => 'Database update failed'
        ], 'WARNING');

        return ResponseHelper::fail('No se pudo actualizar el usuario', 400);
      }

      Logger::info('User updated successfully', [
        'user_id' => $userId,
        'updated_fields' => array_keys($validatedData),
        'ip_address' => $clientIP
      ]);

      SecurityLoggerService::logSecurityEvent('user_update_success', [
        'ip_address' => $clientIP,
        'user_id' => $userId,
        'updated_fields' => array_keys($validatedData)
      ], 'INFO');

      return ResponseHelper::success("Usuario $userId actualizado exitosamente", ['success' => true], 200);
    } catch (\InvalidArgumentException $e) {
      SecurityLoggerService::logSecurityEvent('user_update_validation_error', [
        'ip_address' => $clientIP,
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ], 'WARNING');

      Logger::error('Validation failed updating user', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ]);
      return ResponseHelper::fail($e->getMessage(), 422);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('user_update_unexpected_error', [
        'ip_address' => $clientIP,
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('Unexpected error updating user', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ]);
      return ResponseHelper::error('Error al actualizar usuario', null, 500);
    }
  }

  public function delete(Request $request, array $params = [])
  {
    try {
      $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

      // RATE LIMITING para eliminaciones de usuarios
      if (!RateLimitService::canPerform('user_delete', $clientIP)) {
        $retryAfter = RateLimitService::getRetryAfter('user_delete', $clientIP);
        SecurityLoggerService::logRateLimitViolation('user_delete', $clientIP, 5, 5);

        return ResponseHelper::fail('Demasiadas eliminaciones. Intente nuevamente más tarde.', 429, [
          'retry_after' => $retryAfter
        ]);
      }

      // VALIDACIÓN DEL ID
      $id = $params['id'] ?? null;
      if (!$id) {
        SecurityLoggerService::logSecurityEvent('user_delete_missing_id', [
          'ip_address' => $clientIP,
          'params' => $params
        ], 'WARNING');

        return ResponseHelper::fail('ID no proporcionado', 400);
      }

      $idValidation = ValidationService::validateInput($id, 'id', [
        'required' => true,
        'type' => 'integer',
        'min' => 1
      ]);

      if (!$idValidation['valid']) {
        SecurityLoggerService::logSecurityEvent('user_delete_invalid_id', [
          'ip_address' => $clientIP,
          'provided_id' => $id,
          'validation_errors' => $idValidation['errors']
        ], 'WARNING');

        return ResponseHelper::fail('ID inválido', 400);
      }

      $userId = (int)$idValidation['sanitized'];

      // VERIFICAR QUE EL USUARIO EXISTA ANTES DE ELIMINAR
      $existingUser = User::getUser($userId);
      if (!$existingUser) {
        SecurityLoggerService::logSecurityEvent('user_delete_not_found', [
          'ip_address' => $clientIP,
          'user_id' => $userId
        ], 'WARNING');

        return ResponseHelper::fail('Usuario no encontrado', 404);
      }

      // REGISTRAR ACCESO AUTORIZADO
      RateLimitService::recordAttempt('user_delete', $clientIP);
      SecurityLoggerService::logSecurityEvent('user_delete_attempt', [
        'ip_address' => $clientIP,
        'user_id' => $userId,
        'user_email' => $existingUser['email'] ?? 'unknown'
      ], 'WARNING'); // WARNING porque es una operación destructiva

      // ELIMINAR USUARIO
      $ok = $this->model->delete($userId);
      if (!$ok) {
        SecurityLoggerService::logSecurityEvent('user_delete_failed', [
          'ip_address' => $clientIP,
          'user_id' => $userId,
          'reason' => 'Database deletion failed'
        ], 'ERROR');

        return ResponseHelper::fail('No se pudo eliminar el usuario', 400);
      }

      Logger::info('User deleted successfully', [
        'user_id' => $userId,
        'user_email' => $existingUser['email'] ?? 'unknown',
        'ip_address' => $clientIP
      ]);

      SecurityLoggerService::logSecurityEvent('user_delete_success', [
        'ip_address' => $clientIP,
        'user_id' => $userId,
        'user_email' => $existingUser['email'] ?? 'unknown'
      ], 'WARNING'); // WARNING porque es una operación destructiva

      return ResponseHelper::success("Usuario $userId eliminado exitosamente", [], 204);
    } catch (\Throwable $e) {
      SecurityLoggerService::logSecurityEvent('user_delete_unexpected_error', [
        'ip_address' => $clientIP,
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ], 'ERROR');

      Logger::error('Unexpected error deleting user', [
        'user_id' => $userId ?? null,
        'error' => $e->getMessage()
      ]);
      return ResponseHelper::error('Error al eliminar usuario', null, 500);
    }
  }

  /**
   * Sanitize orderBy parameters
   */
  private function sanitizeOrder(array $orderBy): array
  {
    // Basic whitelist for common fields
    $allowedFields = ['id', 'email', 'name', 'created_at', 'updated_at'];
    $sanitized = [];

    foreach ($orderBy as $field => $direction) {
      if (in_array($field, $allowedFields) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
        $sanitized[$field] = strtoupper($direction);
      }
    }

    return $sanitized;
  }
}
