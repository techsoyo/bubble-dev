<?php

namespace Models;

use Exception;
use PDO;

/**
 * Modelo para las opciones de cada nodo del chatbot.
 * Representa cada opción seleccionable por el usuario.
 */
class ChatbotOption extends BaseModel
{
    /** @var string|null ID único de la opción */
    public $id;
    /** @var string|null ID del nodo al que pertenece la opción */
    public $node_id;
    /** @var string Texto mostrado al usuario */
    public $text;
    /** @var string|null ID del siguiente nodo (si aplica) */
    public $next_node_id;
    /** @var string Tipo de acción: navigate|submit|restart|end */
    public $action_type;
    /** @var mixed|null Datos adicionales de la acción (JSON) */
    public $action_data;
    /** @var int Orden de aparición */
    public $order_position;
    /** @var int Activo (1) o inactivo (0) */
    public $is_active;
    /** @var string|null Fecha de creación */
    public $created_at;
    /** @var string|null Fecha de última actualización */
    public $updated_at;

    protected string $table = 'bt_chatbot_options';
    protected string $primaryKey = 'id';

    /**
     * Constructor
     * @param array $data Datos para inicializar la opción
     */
    public function __construct($data = [])
    {
        parent::__construct();
        $this->id = $data['id'] ?? null;
        $this->node_id = $data['node_id'] ?? null;
        $this->text = $data['text'] ?? '';
        $this->next_node_id = $data['next_node_id'] ?? null;
        $this->action_type = $data['action_type'] ?? 'navigate';
        $this->action_data = $data['action_data'] ?? null;
        $this->order_position = $data['order_position'] ?? 1;
        $this->is_active = $data['is_active'] ?? 1;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
    }

    // Métodos CRUD y utilidades heredados de BaseModel
    /**
     * Obtener todas las opciones activas
     */
    public function getAllActive()
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY node_id, order_position ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar action_data JSON
            foreach ($options as &$option) {
                $option['action_data'] = json_decode($option['action_data'], true) ?: [];
            }

            return $options;
        } catch (Exception $e) {
            error_log('Error al obtener opciones activas: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener opciones por ID de nodo
     */
    public function getByNodeId($nodeId)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE node_id = ? AND is_active = 1 ORDER BY order_position ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$nodeId]);

            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar action_data JSON
            foreach ($options as &$option) {
                $option['action_data'] = json_decode($option['action_data'], true) ?: [];
            }

            return $options;
        } catch (Exception $e) {
            error_log("Error al obtener opciones para nodo $nodeId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener opción por ID
     */
    public function getById($id)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $option = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($option) {
                $option['action_data'] = json_decode($option['action_data'], true) ?: [];
            }

            return $option;
        } catch (Exception $e) {
            error_log("Error al obtener opción por ID $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nueva opción
     */
    public function store($data)
    {
        try {
            $sql = "INSERT INTO {$this->table} (id, node_id, text, next_node_id, action_type, action_data, order_position) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                $data['id'],
                $data['node_id'],
                $data['text'],
                $data['next_node_id'] ?? null,
                $data['action_type'],
                $data['action_data'] ?? null,
                $data['order_position'] ?? 1
            ]);
        } catch (Exception $e) {
            error_log('Error al crear opción: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar opción
     */
    public function update($id, array $data): bool
    {
        try {
            $filteredData = [];

            foreach ($data as $field => $value) {
                if (in_array($field, ['node_id', 'text', 'next_node_id', 'action_type', 'action_data', 'order_position', 'is_active'])) {
                    $filteredData[$field] = $value;
                }
            }

            if (empty($filteredData)) {
                return false;
            }

            // Agregar timestamp de actualización
            $filteredData['updated_at'] = date('Y-m-d H:i:s');

            // Usar el método del BaseModel
            return parent::update($id, $filteredData);
        } catch (Exception $e) {
            error_log("Error al actualizar opción $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar opción (soft delete)
     */
    public function delete($id): bool
    {
        try {
            // Para soft delete, usar update en lugar de delete
            return $this->update($id, [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error al eliminar opción $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar opción permanentemente
     */
    public function forceDelete($id): bool
    {
        try {
            // Usar el método del BaseModel para eliminación real
            return parent::delete($id);
        } catch (Exception $e) {
            error_log("Error al eliminar permanentemente opción $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener opciones por tipo de acción
     */
    public function getByActionType($actionType)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE action_type = ? AND is_active = 1 ORDER BY node_id, order_position ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$actionType]);

            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar action_data JSON
            foreach ($options as &$option) {
                $option['action_data'] = json_decode($option['action_data'], true) ?: [];
            }

            return $options;
        } catch (Exception $e) {
            error_log("Error al obtener opciones por tipo de acción $actionType: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Reordenar opciones de un nodo
     */
    public function reorderNodeOptions($nodeId, $optionIds)
    {
        try {
            $this->db->beginTransaction();

            foreach ($optionIds as $position => $optionId) {
                $sql = "UPDATE {$this->table} SET order_position = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE id = ? AND node_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([$position + 1, $optionId, $nodeId]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error al reordenar opciones del nodo $nodeId: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar si una opción existe
     */
    public function exists($id)
    {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error al verificar existencia de la opción $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de opciones
     */
    public function getStats()
    {
        try {
            $sql = "SELECT 
                        action_type,
                        COUNT(*) as count,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                    FROM {$this->table} 
                    GROUP BY action_type";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error al obtener estadísticas de opciones: ' . $e->getMessage());
            throw $e;
        }
    }
}
