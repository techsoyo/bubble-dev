<?php

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class AuthController
{
    /**
     * Login de usuario (staff o candidato)
     */
    public function login(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            // TODO: validar credenciales contra DB
            return ResponseHelper::success("Login exitoso", [
                'token' => 'mocked-jwt-token',
                'user' => $data['email'] ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en login", $e);
        }
    }

    /**
     * Login de candidato
     */
    public function candidateLogin(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            return ResponseHelper::success("Login de candidato exitoso", [
                'token' => 'mocked-jwt-token-candidate',
                'candidate' => $data['email'] ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en login de candidato", $e);
        }
    }

    /**
     * Login de staff
     */
    public function staffLogin(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            return ResponseHelper::success("Login de staff exitoso", [
                'token' => 'mocked-jwt-token-staff',
                'staff' => $data['email'] ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en login de staff", $e);
        }
    }

    /**
     * Cerrar sesión
     */
    public function logout(Request $request, array $params = [])
    {
        try {
            return ResponseHelper::success("Logout exitoso");
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en logout", $e);
        }
    }

    /**
     * Obtener información del usuario autenticado
     */
    public function userInfo(Request $request, array $params = [])
    {
        try {
            return ResponseHelper::success("Información del usuario autenticado", [
                'id' => 1,
                'name' => 'Mocked User',
                'role' => 'candidate'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al obtener user info", $e);
        }
    }

    /**
     * Verificar sesión activa
     */
    public function verifySession(Request $request, array $params = [])
    {
        try {
            return ResponseHelper::success("Sesión válida", [
                'valid' => true
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al verificar sesión", $e);
        }
    }

    /**
     * Generar CSRF Token
     */
    public function csrfToken(Request $request, array $params = [])
    {
        try {
            return ResponseHelper::success("Token CSRF generado", [
                'csrf_token' => bin2hex(random_bytes(16))
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al generar CSRF token", $e);
        }
    }

    /**
     * Validar CSRF Token
     */
    public function validateCsrf(Request $request, array $params = [])
    {
        try {
            $token = $request->getBody()['csrf_token'] ?? null;
            $valid = $token ? true : false; // TODO: lógica real
            return ResponseHelper::success("Validación CSRF", [
                'valid' => $valid
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al validar CSRF", $e);
        }
    }

    /**
     * Login seguro (2FA, etc.)
     */
    public function secureLogin(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            return ResponseHelper::success("Login seguro exitoso", [
                'token' => 'mocked-secure-jwt',
                'user' => $data['email'] ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en login seguro", $e);
        }
    }

    /**
     * Interceptor de autenticación
     */
    public function intercept(Request $request, array $params = [])
    {
        try {
            return ResponseHelper::success("Auth Interceptor OK", [
                'intercepted' => true
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en auth interceptor", $e);
        }
    }

    /**
     * Cambio de contraseña
     */
    public function changePassword(Request $request, array $params = [])
    {
        try {
            $data = $request->getBody();
            return ResponseHelper::success("Contraseña cambiada exitosamente", [
                'user' => $data['email'] ?? 'unknown'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error al cambiar contraseña", $e);
        }
    }

    /**
     * Ping de autenticación
     */
    public function ping(Request $request, array $params = [])
    {
        try {
            return ResponseHelper::success("Ping de autenticación exitoso", [
                'status' => 'ok'
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error("Error en auth ping", $e);
        }
    }
}
