<?php declare(strict_types=1);

namespace Services\StatisticsService.php\Services;

use Utils\Database;

/**
 * StatisticsService
 * Servicio para recopilar y consultar mÃ©tricas y estadÃ­sticas del sistema.
 */
class StatisticsService
{
    /**
     * Obtiene el nÃºmero total de usuarios registrados
     */
    public function getTotalUsers(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM users');
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Obtiene el nÃºmero total de empleos publicados
     */
    public function getTotalJobs(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM jobs');
        return (int)($result[0]['total'] ?? 0);
    }

    /**
     * Obtiene el nÃºmero total de postulaciones
     */
    public function getTotalApplications(): int
    {
        $db = Database::getInstance();
        $result = $db->query('SELECT COUNT(*) as total FROM applications');
        return (int)($result[0]['total'] ?? 0);
    }

    // Puedes agregar mÃ¡s mÃ©todos para otras mÃ©tricas relevantes
}
