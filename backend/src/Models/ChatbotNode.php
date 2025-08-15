<?php

// backend/src/Models/ChatbotNode.php

namespace Models;

use Exception;
use PDO;

/**
 * Modelo para los nodos del chatbot.
 * Representa cada mensaje, opción, formulario o redirección en el flujo conversacional.
 */
/**
 * Modelo para los nodos del chatbot.
 * Requiere que BaseModel defina la propiedad protegida $db (PDO) y esté correctamente incluida.
 */
class ChatbotNode extends BaseModel
{
    /** @var string|null ID único del nodo */
    public $id;
    /** @var string Tipo de nodo: message|options|form|redirect */
    public $type;
    /** @var string Contenido del nodo (texto, instrucciones, etc.) */
    public $content;
    /** @var mixed|null Metadatos adicionales en formato JSON */
    public $metadata;
    /** @var int Activo (1) o inactivo (0) */
    public $is_active;
    /** @var string|null Fecha de creación */
    public $created_at;
    /** @var string|null Fecha de última actualización */
    public $updated_at;
    /** @var string|null Usuario que creó el nodo */
    public $created_by;

    protected string $table = 'bt_chatbot_nodes';

    /**
     * Constructor
     * @param array $data Datos para inicializar el nodo
     */
    public function __construct($data = [])
    {
        parent::__construct();
        $this->id = $data['id'] ?? null;
        $this->type = $data['type'] ?? 'message';
        $this->content = $data['content'] ?? '';
        $this->metadata = $data['metadata'] ?? null;
        $this->is_active = $data['is_active'] ?? 1;
        $this->created_at = $data['created_at'] ?? null;
        $this->updated_at = $data['updated_at'] ?? null;
        $this->created_by = $data['created_by'] ?? 'system';
    }

    // Métodos CRUD y utilidades heredados de BaseModel

    // Métodos CRUD y utilidades heredados de BaseModel

    /**
     * Obtener todos los nodos activos
     */
    public function getAllActive()
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE is_active = 1 ORDER BY created_at ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar metadata JSON
            foreach ($nodes as &$node) {
                $node['metadata'] = json_decode($node['metadata'], true) ?: [];
            }

            return $nodes;
        } catch (Exception $e) {
            error_log('Error al obtener nodos activos: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener nodo por ID
     */
    public function getById($id)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE id = ? AND is_active = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            $node = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($node) {
                $node['metadata'] = json_decode($node['metadata'], true) ?: [];
            }

            return $node;
        } catch (Exception $e) {
            error_log("Error al obtener nodo por ID $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Crear nuevo nodo
     */
    public function create($data)
    {
        try {
            $sql = "INSERT INTO {$this->table} (id, type, content, metadata, created_by) 
                    VALUES (?, ?, ?, ?, ?)";

            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
              $data['id'],
              $data['type'],
              $data['content'],
              $data['metadata'] ?? null,
              $data['created_by'] ?? 'system'
            ]);
        } catch (Exception $e) {
            error_log('Error al crear nodo: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Actualizar nodo
     */
    public function update($id, array $data): bool
    {
        try {
            $updateFields = [];
            $filteredData = [];

            foreach ($data as $field => $value) {
                if (in_array($field, ['type', 'content', 'metadata', 'is_active'])) {
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
            error_log("Error al actualizar nodo $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar nodo (soft delete)
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
            error_log("Error al eliminar nodo $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Eliminar nodo permanentemente
     */
    public function forceDelete($id): bool
    {
        try {
            // Usar el método del BaseModel para eliminación real
            return parent::delete($id);
        } catch (Exception $e) {
            error_log("Error al eliminar permanentemente nodo $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Buscar nodos por tipo
     */
    public function getByType($type)
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE type = ? AND is_active = 1 ORDER BY created_at ASC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$type]);

            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Decodificar metadata JSON
            foreach ($nodes as &$node) {
                $node['metadata'] = json_decode($node['metadata'], true) ?: [];
            }

            return $nodes;
        } catch (Exception $e) {
            error_log("Error al obtener nodos por tipo $type: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Verificar si un nodo existe
     */
    public function exists($id)
    {
        try {
            $sql = "SELECT COUNT(*) FROM {$this->table} WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$id]);

            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            error_log("Error al verificar existencia del nodo $id: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtener estadísticas de nodos
     */
    public function getStats()
    {
        try {
            $sql = "SELECT 
                        type,
                        COUNT(*) as count,
                        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_count
                    FROM {$this->table} 
                    GROUP BY type";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log('Error al obtener estadísticas de nodos: ' . $e->getMessage());
            throw $e;
        }
    }
}
