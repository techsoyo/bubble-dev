<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;
use PDOException;

/**
 * Modelo ChatbotOption migrado para extender BaseModel
 * 
 * Representa las opciones de cada nodo del chatbot que los usuarios pueden seleccionar.
 * Incluye funcionalidades avanzadas de cache, validación de flujo y estadísticas.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class ChatbotOption extends BaseModel
{
    /**
     * Tabla asociada al modelo
     */
    protected string $table = 'bt_chatbot_options';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: ChatbotOption
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ['option_text', 'value', 'sort_order', 'metadata']
     * ❌ Campos removidos: ['text', 'action_type', 'action_data', 'order_position']
     * 📊 Total campos fillable: 7
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */
    

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'node_id',
        'option_text',
        'next_node_id',
        'value',
        'is_active',
        'sort_order',
        'metadata',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [
        'action_data',    // internal_logic mapeado
        'created_at',     // debug_info mapeado
        'updated_at'
    ];

    /**
     * Cache para opciones frecuentemente accedidas
     */
    private array $optionsCache = [];

    /**
     * TTL del cache en segundos (5 minutos)
     */
    private const CACHE_TTL = 300;

    /**
     * Obtener opciones de un nodo específico con cache
     * 
     * @param string $nodeId ID del nodo
     * @param bool $activeOnly Solo opciones activas
     * @return array Lista de opciones del nodo
     * 
     * @throws \InvalidArgumentException Si nodeId está vacío
     * @throws \RuntimeException Si hay error en la base de datos
     */
    public function getOptionsByNode(string $nodeId, bool $activeOnly = true): array
    {
        if (empty($nodeId)) {
            throw new \InvalidArgumentException('Node ID cannot be empty');
        }

        $cacheKey = "node_options_{$nodeId}_" . ($activeOnly ? 'active' : 'all');
        
        // Verificar cache
        if ($this->isCacheValid($cacheKey)) {
            Logger::debug('Cache hit for node options', ['node_id' => $nodeId]);
            return $this->optionsCache[$cacheKey]['data'];
        }

        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `node_id` = :node_id";
            $params = [':node_id' => $nodeId];

            if ($activeOnly) {
                $sql .= " AND `is_active` = 1";
            }

            $sql .= " ORDER BY `order_position` ASC";

            $options = $this->query($sql, $params);

            // Decodificar action_data JSON
            $options = array_map(function ($option) {
                if (!empty($option['action_data'])) {
                    $option['action_data'] = json_decode($option['action_data'], true) ?? [];
                }
                return $option;
            }, $options);

            // Guardar en cache
            $this->optionsCache[$cacheKey] = [
                'data' => $options,
                'timestamp' => time()
            ];

            Logger::info('Options retrieved for node', [
                'node_id' => $nodeId,
                'count' => count($options),
                'active_only' => $activeOnly
            ]);

            return $options;
        } catch (PDOException $e) {
            $this->logError('Error retrieving options by node', [
                'node_id' => $nodeId,
                'active_only' => $activeOnly
            ], $e);
            throw new \RuntimeException('Failed to retrieve options by node: ' . $e->getMessage());
        }
    }

    /**
     * Obtener solo opciones activas de todos los nodos con cache
     * 
     * @param array $filters Filtros adicionales
     * @return array Lista de opciones activas
     * 
     * @throws \RuntimeException Si hay error en la base de datos
     */
    public function getActiveOptions(array $filters = []): array
    {
        $cacheKey = 'active_options_' . md5(serialize($filters));
        
        // Verificar cache
        if ($this->isCacheValid($cacheKey)) {
            Logger::debug('Cache hit for active options', ['filters' => $filters]);
            return $this->optionsCache[$cacheKey]['data'];
        }

        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `is_active` = 1";
            $params = [];

            // Aplicar filtros adicionales
            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if ($value !== null && $this->isValidFieldName($field)) {
                        $sql .= " AND `$field` = :filter_$field";
                        $params[":filter_$field"] = $value;
                    }
                }
            }

            $sql .= " ORDER BY `node_id`, `order_position` ASC";

            $options = $this->query($sql, $params);

            // Decodificar action_data JSON
            $options = array_map(function ($option) {
                if (!empty($option['action_data'])) {
                    $option['action_data'] = json_decode($option['action_data'], true) ?? [];
                }
                return $option;
            }, $options);

            // Guardar en cache
            $this->optionsCache[$cacheKey] = [
                'data' => $options,
                'timestamp' => time()
            ];

            Logger::info('Active options retrieved', [
                'count' => count($options),
                'filters' => $filters
            ]);

            return $options;
        } catch (PDOException $e) {
            $this->logError('Error retrieving active options', ['filters' => $filters], $e);
            throw new \RuntimeException('Failed to retrieve active options: ' . $e->getMessage());
        }
    }

    /**
     * Procesar selección de usuario y retornar siguiente acción
     * 
     * @param string $optionId ID de la opción seleccionada
     * @param array $userData Datos del usuario para contexto
     * @return array Información de la siguiente acción
     * 
     * @throws \InvalidArgumentException Si optionId está vacío
     * @throws \RuntimeException Si la opción no existe o hay error
     */
    public function processOptionSelection(string $optionId, array $userData = []): array
    {
        if (empty($optionId)) {
            throw new \InvalidArgumentException('Option ID cannot be empty');
        }

        try {
            $option = $this->findById($optionId);

            if (!$option) {
                throw new \RuntimeException("Option not found: $optionId");
            }

            if (!$option['is_active']) {
                throw new \RuntimeException("Option is not active: $optionId");
            }

            // Decodificar action_data
            $actionData = [];
            if (!empty($option['action_data'])) {
                $actionData = json_decode($option['action_data'], true) ?? [];
            }

            // Preparar respuesta de procesamiento
            $result = [
                'option_id' => $optionId,
                'action_type' => $option['action_type'],
                'next_node_id' => $option['next_node_id'],
                'action_data' => $actionData,
                'processed_at' => date('Y-m-d H:i:s'),
                'user_data' => $userData
            ];

            // Validar flujo si es necesario
            if (!empty($option['next_node_id'])) {
                $isValidFlow = $this->validateOptionFlow($optionId, $option['next_node_id']);
                $result['flow_valid'] = $isValidFlow;
            }

            Logger::info('Option selection processed', [
                'option_id' => $optionId,
                'action_type' => $option['action_type'],
                'next_node_id' => $option['next_node_id']
            ]);

            return $result;
        } catch (PDOException $e) {
            $this->logError('Error processing option selection', [
                'option_id' => $optionId,
                'user_data' => $userData
            ], $e);
            throw new \RuntimeException('Failed to process option selection: ' . $e->getMessage());
        }
    }

    /**
     * Validar flujo de opciones verificando la existencia del nodo siguiente
     * 
     * @param string $optionId ID de la opción
     * @param string $nextNodeId ID del siguiente nodo
     * @return bool True si el flujo es válido
     * 
     * @throws \InvalidArgumentException Si los IDs están vacíos
     * @throws \RuntimeException Si hay error en la validación
     */
    public function validateOptionFlow(string $optionId, string $nextNodeId): bool
    {
        if (empty($optionId) || empty($nextNodeId)) {
            throw new \InvalidArgumentException('Option ID and Next Node ID cannot be empty');
        }

        try {
            // Verificar que la opción existe y está activa
            $option = $this->findById($optionId);
            if (!$option || !$option['is_active']) {
                Logger::warning('Option flow validation failed: option not found or inactive', [
                    'option_id' => $optionId
                ]);
                return false;
            }

            // Verificar que el nodo siguiente existe (usando tabla de nodos)
            $nodeCheckSql = "SELECT COUNT(*) as count FROM `bt_chatbot_nodes` WHERE `id` = :next_node_id AND `is_active` = 1";
            $result = $this->query($nodeCheckSql, [':next_node_id' => $nextNodeId]);
            
            $nodeExists = ($result[0]['count'] ?? 0) > 0;

            Logger::debug('Option flow validation', [
                'option_id' => $optionId,
                'next_node_id' => $nextNodeId,
                'valid' => $nodeExists
            ]);

            return $nodeExists;
        } catch (PDOException $e) {
            $this->logError('Error validating option flow', [
                'option_id' => $optionId,
                'next_node_id' => $nextNodeId
            ], $e);
            throw new \RuntimeException('Failed to validate option flow: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadísticas de uso de opciones
     * 
     * @param int $days Días atrás para calcular estadísticas
     * @return array Estadísticas detalladas
     * 
     * @throws \RuntimeException Si hay error en la consulta
     */
    public function getOptionStats(int $days = 30): array
    {
        try {
            // Estadísticas básicas de opciones
            $basicStatsSql = "SELECT 
                COUNT(*) as total_options,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_options,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_options,
                COUNT(DISTINCT node_id) as unique_nodes
            FROM `{$this->table}`";
            
            $basicStats = $this->query($basicStatsSql)[0] ?? [];

            // Estadísticas por tipo de acción
            $actionStatsSql = "SELECT 
                action_type,
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM `{$this->table}` WHERE is_active = 1), 2) as percentage
            FROM `{$this->table}` 
            WHERE is_active = 1 
            GROUP BY action_type 
            ORDER BY count DESC";
            
            $actionStats = $this->query($actionStatsSql);

            // Estadísticas de uso desde analytics (si existe la tabla)
            $usageStats = [];
            try {
                $usageStatsSql = "SELECT 
                    co.id as option_id,
                    co.text as option_text,
                    co.action_type,
                    COUNT(ca.id) as usage_count,
                    MAX(ca.created_at) as last_used
                FROM `{$this->table}` co
                LEFT JOIN `bt_chatbot_analytics` ca ON ca.option_id = co.id 
                    AND ca.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
                WHERE co.is_active = 1
                GROUP BY co.id, co.text, co.action_type
                ORDER BY usage_count DESC
                LIMIT 10";
                
                $usageStats = $this->query($usageStatsSql, [':days' => $days]);
            } catch (PDOException $e) {
                Logger::warning('Analytics table not available for usage stats', ['error' => $e->getMessage()]);
            }

            $stats = [
                'basic' => $basicStats,
                'by_action_type' => $actionStats,
                'usage' => $usageStats,
                'generated_at' => date('Y-m-d H:i:s'),
                'period_days' => $days
            ];

            Logger::info('Option statistics generated', [
                'total_options' => $basicStats['total_options'] ?? 0,
                'active_options' => $basicStats['active_options'] ?? 0
            ]);

            return $stats;
        } catch (PDOException $e) {
            $this->logError('Error generating option statistics', ['days' => $days], $e);
            throw new \RuntimeException('Failed to generate option statistics: ' . $e->getMessage());
        }
    }

    /**
     * Reordenar opciones de un nodo específico
     * 
     * @param string $nodeId ID del nodo
     * @param array $optionIds Array de IDs en el orden deseado
     * @return bool True si fue exitoso
     * 
     * @throws \InvalidArgumentException Si los parámetros son inválidos
     * @throws \RuntimeException Si hay error en la base de datos
     */
    public function reorderNodeOptions(string $nodeId, array $optionIds): bool
    {
        if (empty($nodeId)) {
            throw new \InvalidArgumentException('Node ID cannot be empty');
        }

        if (empty($optionIds)) {
            throw new \InvalidArgumentException('Option IDs array cannot be empty');
        }

        try {
            $this->db->beginTransaction();

            foreach ($optionIds as $position => $optionId) {
                $sql = "UPDATE `{$this->table}` 
                       SET `order_position` = :position, `updated_at` = NOW() 
                       WHERE `id` = :option_id AND `node_id` = :node_id";
                
                $this->query($sql, [
                    ':position' => $position + 1,
                    ':option_id' => $optionId,
                    ':node_id' => $nodeId
                ]);
            }

            $this->db->commit();
            
            // Limpiar cache relacionado
            $this->clearNodeCache($nodeId);

            Logger::info('Node options reordered successfully', [
                'node_id' => $nodeId,
                'options_count' => count($optionIds)
            ]);

            return true;
        } catch (PDOException $e) {
            $this->db->rollBack();
            $this->logError('Error reordering node options', [
                'node_id' => $nodeId,
                'option_ids' => $optionIds
            ], $e);
            throw new \RuntimeException('Failed to reorder node options: ' . $e->getMessage());
        }
    }

    /**
     * Crear nueva opción con validaciones
     * 
     * @param array $data Datos de la nueva opción
     * @return mixed ID de la nueva opción
     * 
     * @throws \InvalidArgumentException Si los datos son inválidos
     * @throws \RuntimeException Si hay error en la creación
     */
    public function store(array $data)
    {
        // Validaciones específicas
        if (empty($data['node_id'])) {
            throw new \InvalidArgumentException('Node ID is required');
        }

        if (empty($data['text'])) {
            throw new \InvalidArgumentException('Option text is required');
        }

        // Valores por defecto
        $data['action_type'] = $data['action_type'] ?? 'navigate';
        $data['order_position'] = $data['order_position'] ?? $this->getNextOrderPosition($data['node_id']);
        $data['is_active'] = $data['is_active'] ?? 1;

        // Codificar action_data si es array
        if (isset($data['action_data']) && is_array($data['action_data'])) {
            $data['action_data'] = json_encode($data['action_data']);
        }

        try {
            $id = parent::store($data);
            
            // Limpiar cache relacionado
            $this->clearNodeCache($data['node_id']);
            
            return $id;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create option: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar opción con validaciones
     * 
     * @param mixed $id ID de la opción
     * @param array $data Datos a actualizar
     * @return bool True si fue exitoso
     */
    public function update($id, array $data): bool
    {
        // Codificar action_data si es array
        if (isset($data['action_data']) && is_array($data['action_data'])) {
            $data['action_data'] = json_encode($data['action_data']);
        }

        try {
            $result = parent::update($id, $data);
            
            // Limpiar cache relacionado si se actualizó node_id
            if (isset($data['node_id'])) {
                $this->clearNodeCache($data['node_id']);
            }
            
            return $result;
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to update option: ' . $e->getMessage());
        }
    }

    /**
     * Eliminación lógica (soft delete)
     * 
     * @param mixed $id ID de la opción
     * @return bool True si fue exitoso
     */
    public function softDelete($id): bool
    {
        try {
            return $this->update($id, [
                'is_active' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to soft delete option: ' . $e->getMessage());
        }
    }

    /**
     * Obtener siguiente posición de orden para un nodo
     * 
     * @param string $nodeId ID del nodo
     * @return int Siguiente posición disponible
     */
    private function getNextOrderPosition(string $nodeId): int
    {
        try {
            $sql = "SELECT COALESCE(MAX(order_position), 0) + 1 as next_position 
                   FROM `{$this->table}` 
                   WHERE `node_id` = :node_id";
            
            $result = $this->query($sql, [':node_id' => $nodeId]);
            return (int)($result[0]['next_position'] ?? 1);
        } catch (PDOException $e) {
            Logger::warning('Error getting next order position, using default', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            return 1;
        }
    }

    /**
     * Verificar si el cache es válido
     * 
     * @param string $key Clave del cache
     * @return bool True si es válido
     */
    private function isCacheValid(string $key): bool
    {
        return isset($this->optionsCache[$key]) && 
               (time() - $this->optionsCache[$key]['timestamp']) < self::CACHE_TTL;
    }

    /**
     * Limpiar cache relacionado con un nodo
     * 
     * @param string $nodeId ID del nodo
     */
    private function clearNodeCache(string $nodeId): void
    {
        $keysToRemove = array_filter(array_keys($this->optionsCache), function ($key) use ($nodeId) {
            return str_contains($key, "node_options_{$nodeId}_");
        });

        foreach ($keysToRemove as $key) {
            unset($this->optionsCache[$key]);
        }

        Logger::debug('Node cache cleared', ['node_id' => $nodeId, 'keys_removed' => count($keysToRemove)]);
    }

    /**
     * Limpiar todo el cache de opciones
     */
    public function clearCache(): void
    {
        $count = count($this->optionsCache);
        $this->optionsCache = [];
        Logger::info('All options cache cleared', ['entries_cleared' => $count]);
    }

    /**
     * Obtener opción por ID con cache
     * 
     * @param mixed $id ID de la opción
     * @return array|null Datos de la opción o null si no existe
     */
    public function findById($id): ?array
    {
        $cacheKey = "option_{$id}";
        
        // Verificar cache
        if ($this->isCacheValid($cacheKey)) {
            return $this->optionsCache[$cacheKey]['data'];
        }

        $option = parent::findById($id);
        
        if ($option) {
            // Decodificar action_data
            if (!empty($option['action_data'])) {
                $option['action_data'] = json_decode($option['action_data'], true) ?? [];
            }
            
            // Guardar en cache
            $this->optionsCache[$cacheKey] = [
                'data' => $option,
                'timestamp' => time()
            ];
        }

        return $option;
    }

    /**
     * Registrar error con contexto específico del modelo
     */
    private function logError(string $message, array $context, \Exception $e): void
    {
        Logger::error($message, array_merge($context, [
            'model' => static::class,
            'table' => $this->table,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]));
    }
}
