<?php

declare(strict_types=1);

namespace Services;

use Utils\Database;

/**
 * StatisticsService
 * Servicio para recopilar y consultar métricas y estadí­sticas del sistema.
 */
class StatisticsService
{
    /**
     * Obtiene el número total de usuarios registrados
     */
    public function getTotalUsers(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM users');
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Obtiene el número total de empleos publicados
     */
    public function getTotalJobs(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM jobs');
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Obtiene el número total de postulaciones
     */
    public function getTotalApplications(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM applications');
        return (int)($result[0]['total'] ?? 0);
    }

    // Puedes agregar  más métodos para otras métricas relevantes
}
