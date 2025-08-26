<?php declare(strict_types=1);
namespace Services;

use Utils\Database;

/**
 * StatisticsService
 * Servicio para recopilar y consultar mÃƒÆ’Ã‚Â©tricas y estadÃƒÆ’Ã‚Â­sticas del sistema.
 */
class StatisticsService
{
    /**
     * Obtiene el nÃƒÆ’Ã‚Âºmero total de usuarios registrados
     */
    public function getTotalUsers(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM users');
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Obtiene el nÃƒÆ’Ã‚Âºmero total de empleos publicados
     */
    public function getTotalJobs(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM jobs');
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Obtiene el nÃƒÆ’Ã‚Âºmero total de postulaciones
     */
    public function getTotalApplications(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM applications');
        return (int)($result[0]['total'] ?? 0);
    }

    // Puedes agregar mÃƒÆ’Ã‚Â¡s mÃƒÆ’Ã‚Â©todos para otras mÃƒÆ’Ã‚Â©tricas relevantes
}
