<?php

namespace Middleware;

use Utils\JWT;

/**
 * Autorización mínima para endpoints de candidato.
 * - Admin/superadmin: acceso total
 * - Recruiter: permitido (si quieres endurecer, abajo hay hook para mirar BD)
 * - El propio candidato (sub/user_id == candidateId): permitido
 * - Resto: 403 en escritura, 403 en lectura (ajusta a tu política)
 */
class SecurityMiddleware
{
    /**
     * Lanza excepción si el usuario no es admin/superadmin
     */
    public static function assertAdmin($authUser): void
    {
        if (!$authUser || !isset($authUser['role']) || !in_array($authUser['role'], ['admin', 'superadmin'], true)) {
            throw new \RuntimeException('Permisos de administrador requeridos');
        }
    }
    // helpers locales si el bootstrap no los define
    private static function db(): \PDO
    {
        return $GLOBALS['pdo'];
    }
    private static function T(string $name): string
    {
        return 'bt_' . $name;
    }

    /**
     * Lectura: si no viene $authUser, intenta JWT; si falla, por defecto deniega (403).
     * Cambia la política si quieres lectura pública.
     */
    public static function assertReadAccessForCandidate($candidateId, ?array $authUser = null): void
    {
        if ($authUser === null) {
            try {
                $authUser = JWT::requireAuth();
            } catch (\Throwable $e) {
                throw new \RuntimeException('No autorizado');
            }
        }

        if (self::canAccessCandidate($candidateId, $authUser, false)) {
            return;
        }
        throw new \RuntimeException('Permisos insuficientes');
    }

    /**
     * Escritura: requiere JWT y verificación estricta.
     */
    public static function assertWriteAccessForCandidate($candidateId, ?array $authUser): void
    {
        if ($authUser === null) {
            throw new \RuntimeException('No autorizado');
        }
        if (!self::canAccessCandidate($candidateId, $authUser, true)) {
            throw new \RuntimeException('Permisos insuficientes');
        }
    }

    /**
     * Lógica central de autorización.
     * $candidateId puede ser int o uuid (string). Comparamos como string.
     */
    private static function canAccessCandidate($candidateId, array $authUser, bool $write): bool
    {
        $role   = $authUser['role'] ?? $authUser['scope'] ?? 'user';
        $userId = $authUser['sub']  ?? $authUser['user_id'] ?? $authUser['id'] ?? null;

        // Admins: barra libre
        if (in_array($role, ['admin', 'superadmin'], true)) {
            return true;
        }

        // El propio candidato (mismo id)
        if ($userId !== null && (string)$userId === (string)$candidateId) {
            return true;
        }

        // Recruiters: permitido. Si quieres endurecer, habilita check por departamento:
        if ($role === 'recruiter') {
            // return self::recruiterCanAccessCandidate($candidateId, $authUser, $write);
            return true;
        }

        // Por defecto, denegar
        return false;
    }

    /**
     * Hook opcional para validar recruiter por departamento/cartera.
     * Desactivado por defecto para no romper mientras cerramos backend.
     */
    private static function recruiterCanAccessCandidate($candidateId, array $authUser, bool $write): bool
    {
        try {
            $db  = self::db();
            $rid = $authUser['recruiter_id'] ?? $authUser['id'] ?? null;
            if (!$rid) {
                return false;
            }

            // Ejemplo: recruiter asignado en routing
            $sql = 'SELECT 1
                      FROM ' . self::T('candidate_routing') . '
                     WHERE candidate_id = ?
                       AND recruiter_id = ?
                  ORDER BY assigned_at DESC
                     LIMIT 1';
            $st  = $db->prepare($sql);
            $st->execute([(string)$candidateId, $rid]);
            if ($st->fetchColumn()) {
                return true;
            }

            // O por pertenencia al mismo departamento del último routing
            $sql = 'SELECT r.department_id
                      FROM ' . self::T('candidate_routing') . ' r
                  ORDER BY r.assigned_at DESC
                     LIMIT 1';
            // Aquí podrías cruzar con la tabla de perfiles del recruiter
            // y validar que gestiona ese department_id.

            // Por simplicidad, negar si no hay match
            return false;
        } catch (\Throwable $e) {
            // En duda, negar
            return false;
        }
    }
}
