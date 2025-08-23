<?php

declare(strict_types=1);

namespace Models;

use Exception;
use PDO;

/**
 * Modelo para el registro de analíticas del chatbot.
 * Guarda eventos, interacciones y datos de uso.
 */
class ChatbotAnalytics extends BaseModel
{
    protected string $table = 'bt_chatbot_analytics';
    protected string $primaryKey = 'id';
    /**
     * Crear nuevo registro de analytics
     */
    public function store($data)
    {
        try {
            $sql = "INSERT INTO {$this->table} (conversation_id, event_name, event_data, node_id, option_id, user_ip, user_agent, session_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                $data['conversation_id'],
                $data['event_name'],
                $data['event_data'] ?? null,
                $data['node_id'] ?? null,
                $data['option_id'] ?? null,
                $data['user_ip'] ?? null,
                $data['user_agent'] ?? null,
                $data['session_id'] ?? null
            ]);
        } catch (Exception $e) {
            error_log('Error al crear registro de analytics: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener eventos por conversación
     */

    /**
     * Limpiar datos antiguos de analytics
     */


    /**
     * Construir filtro de fechas para consultas
     */
    private function buildDateFilter($dateFrom, $dateTo)
    {
        $conditions = [];

        if ($dateFrom) {
            $conditions[] = 'timestamp >= :date_from';
        }

        if ($dateTo) {
            $conditions[] = 'timestamp <= :date_to';
        }

        return empty($conditions) ? '' : ' WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * Vincular parámetros de fecha a la consulta
     */
    private function bindDateParams($stmt, $dateFrom, $dateTo)
    {
        if ($dateFrom) {
            $stmt->bindValue(':date_from', $dateFrom);
        }

        if ($dateTo) {
            $stmt->bindValue(':date_to', $dateTo . ' 23:59:59');
        }
    }

    /**
     * Obtener eventos por conversación
     */
    public function getConversationEvents($conversationId)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE conversation_id = ? ORDER BY timestamp ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$conversationId]);

            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar event_data JSON
            foreach ($events as &$event) {
                $event['event_data'] = json_decode($event['event_data'], true) ?: [];
            }

            return $events;
        } catch (Exception $e) {
            error_log("Error al obtener eventos de conversación $conversationId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Limpiar datos antiguos de analytics
     */
    public function cleanOldData($daysToKeep = 90)
    {
        try {
            $sql = "DELETE FROM {$this->table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([$daysToKeep]);

            $deletedRows = $stmt->rowCount();
            error_log("Limpieza de analytics completada: $deletedRows registros eliminados");

            return $deletedRows;
        } catch (Exception $e) {
            error_log('Error al limpiar datos antiguos de analytics: ' . $e->getMessage());
            throw $e;
        }
    }
}
