<?php declare(strict_types=1);
namespace Models;

use Utils\Logger;
use Exception;
use PDO;

/**
 * Modelo para los nodos del chatbot optimizado con BaseModel
 * 
 * Representa cada mensaje, opciÃƒÆ’Ã‚Â³n, formulario o redirecciÃƒÆ’Ã‚Â³n en el flujo conversacional.
 * Incluye funcionalidades avanzadas para manejo de ÃƒÆ’Ã‚Â¡rboles jerÃƒÆ’Ã‚Â¡rquicos, cache optimizado,
 * y validaciÃƒÆ’Ã‚Â³n robusta de flujos de conversaciÃƒÆ’Ã‚Â³n.
 *
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class ChatbotNode extends BaseModel
{
    /**
     * Tabla asociada al modelo
     */
    protected string $table = 'chatbot_nodes';
    /*
    
    

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'type',
        'content',
        'metadata',
        'is_active',
        'created_by',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [
        'internal_config',
        'debug_data'
    ];

    /**
     * Cache para ÃƒÆ’Ã‚Â¡rboles de conversaciÃƒÆ’Ã‚Â³n
     */
    private array $nodeTreeCache = [];

    /**
     * TTL del cache en segundos (5 minutos por defecto)
     */
    private const CACHE_TTL = 300;

    /**
     * Tipos de nodo permitidos
     */
    private const VALID_NODE_TYPES = [
        'message',
        'options',
        'form',
        'redirect',
        'condition',
        'action'
    ];

    /**
     * Estados vÃƒÆ’Ã‚Â¡lidos de nodos
     */
    private const VALID_STATUSES = [
        'active',
        'inactive',
        'draft',
        'archived'
    ];

    /**
     * Obtener la estructura jerÃƒÆ’Ã‚Â¡rquica completa del chatbot
     * 
     * Construye el ÃƒÆ’Ã‚Â¡rbol completo de nodos del chatbot con sus relaciones padre-hijo,
     * optimizado con cache para mejorar rendimiento en consultas frecuentes.
     *
     * @param bool $useCache Si debe utilizar cache para la consulta
     * @param bool $activeOnly Si solo debe incluir nodos activos
     * @return array Estructura jerÃƒÆ’Ã‚Â¡rquica de nodos
     * @throws \RuntimeException Si hay error en la consulta
     */
    public function getNodeTree(bool $useCache = true, bool $activeOnly = true): array
    {
        $cacheKey = "node_tree_" . ($activeOnly ? 'active' : 'all');

        // Verificar cache si estÃƒÆ’Ã‚Â¡ habilitado
        if ($useCache && isset($this->nodeTreeCache[$cacheKey])) {
            $cacheData = $this->nodeTreeCache[$cacheKey];
            if (time() - $cacheData['timestamp'] < self::CACHE_TTL) {
                Logger::debug('Retrieved node tree from cache', ['cache_key' => $cacheKey]);
                return $cacheData['data'];
            }
        }

        try {
            $sql = "SELECT * FROM `{$this->table}`";
            $params = [];

            if ($activeOnly) {
                $sql .= " WHERE status = :status";
                $params[':status'] = 'active';
            }

            $sql .= " ORDER BY parent_id ASC, sort_order ASC, name ASC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }

            $stmt->execute();
            $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar metadatos JSON
            foreach ($nodes as &$node) {
                $node['metadata'] = $node['metadata'] ? json_decode($node['metadata'], true) : [];
                $node['conditions'] = $node['conditions'] ? json_decode($node['conditions'], true) : [];
                $node['actions'] = $node['actions'] ? json_decode($node['actions'], true) : [];
            }

            // Construir estructura jerÃƒÆ’Ã‚Â¡rquica
            $tree = $this->buildNodeHierarchy($nodes);

            // Guardar en cache
            if ($useCache) {
                $this->nodeTreeCache[$cacheKey] = [
                    'data' => $tree,
                    'timestamp' => time()
                ];
            }

            Logger::info('Node tree built successfully', [
                'total_nodes' => count($nodes),
                'tree_depth' => $this->calculateTreeDepth($tree),
                'use_cache' => $useCache,
                'active_only' => $activeOnly
            ]);

            return $tree;
        } catch (Exception $e) {
            Logger::error('Error building node tree', [
                'active_only' => $activeOnly,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to build node tree: ' . $e->getMessage());
        }
    }

    /**
     * Encontrar nodos hijos de un nodo especÃƒÆ’Ã‚Â­fico
     *
     * @param string|int $parentId ID del nodo padre
     * @param bool $activeOnly Si solo incluir nodos activos
     * @param bool $recursive Si incluir hijos anidados
     * @return array Lista de nodos hijos
     * @throws \InvalidArgumentException Si el parent_id es invÃƒÆ’Ã‚Â¡lido
     * @throws \RuntimeException Si hay error en la consulta
     */
    public function findChildNodes($parentId, bool $activeOnly = true, bool $recursive = false): array
    {
        if (empty($parentId)) {
            throw new \InvalidArgumentException('Parent ID cannot be empty');
        }

        try {
            if ($recursive) {
                return $this->findChildNodesRecursive($parentId, $activeOnly);
            }

            $sql = "SELECT * FROM `{$this->table}` WHERE parent_id = :parent_id";
            $params = [':parent_id' => $parentId];

            if ($activeOnly) {
                $sql .= " AND status = :status";
                $params[':status'] = 'active';
            }

            $sql .= " ORDER BY sort_order ASC, name ASC";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, $this->getPdoType($value));
            }

            $stmt->execute();
            $childNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar metadatos JSON
            foreach ($childNodes as &$node) {
                $node['metadata'] = $node['metadata'] ? json_decode($node['metadata'], true) : [];
                $node['conditions'] = $node['conditions'] ? json_decode($node['conditions'], true) : [];
                $node['actions'] = $node['actions'] ? json_decode($node['actions'], true) : [];
            }

            Logger::debug('Child nodes found successfully', [
                'parent_id' => $parentId,
                'count' => count($childNodes),
                'active_only' => $activeOnly,
                'recursive' => $recursive
            ]);

            return $childNodes;
        } catch (Exception $e) {
            Logger::error('Error finding child nodes', [
                'parent_id' => $parentId,
                'active_only' => $activeOnly,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to find child nodes: ' . $e->getMessage());
        }
    }

    /**
     * Obtener solo nodos activos con filtros opcionales
     *
     * @param array $filters Filtros adicionales
     * @param array $orderBy Ordenamiento
     * @return array Lista de nodos activos
     * @throws \RuntimeException Si hay error en la consulta
     */
    public function getActiveNodes(array $filters = [], array $orderBy = []): array
    {
        try {
            $baseFilters = ['status' => 'active'];
            $mergedFilters = array_merge($baseFilters, $filters);

            $defaultOrderBy = ['sort_order' => 'ASC', 'name' => 'ASC'];
            $mergedOrderBy = array_merge($defaultOrderBy, $orderBy);

            $nodes = $this->findAll($mergedFilters, 1, self::MAX_LIMIT, $mergedOrderBy);

            // Procesar metadatos JSON para nodos activos
            foreach ($nodes as &$node) {
                if (isset($node['metadata'])) {
                    $node['metadata'] = $node['metadata'] ? json_decode($node['metadata'], true) : [];
                }
                if (isset($node['conditions'])) {
                    $node['conditions'] = $node['conditions'] ? json_decode($node['conditions'], true) : [];
                }
                if (isset($node['actions'])) {
                    $node['actions'] = $node['actions'] ? json_decode($node['actions'], true) : [];
                }
            }

            Logger::debug('Active nodes retrieved successfully', [
                'count' => count($nodes),
                'filters' => $filters
            ]);

            return $nodes;
        } catch (Exception $e) {
            Logger::error('Error getting active nodes', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to get active nodes: ' . $e->getMessage());
        }
    }

    /**
     * Ejecutar la acciÃƒÆ’Ã‚Â³n asociada a un nodo
     *
     * @param string|int $nodeId ID del nodo
     * @param array $context Contexto de la conversaciÃƒÆ’Ã‚Â³n
     * @param array $userInput Input del usuario
     * @return array Resultado de la ejecuciÃƒÆ’Ã‚Â³n
     * @throws \InvalidArgumentException Si los parÃƒÆ’Ã‚Â¡metros son invÃƒÆ’Ã‚Â¡lidos
     * @throws \RuntimeException Si hay error en la ejecuciÃƒÆ’Ã‚Â³n
     */
    public function executeNodeAction($nodeId, array $context = [], array $userInput = []): array
    {
        if (empty($nodeId)) {
            throw new \InvalidArgumentException('Node ID cannot be empty');
        }

        try {
            // Obtener el nodo
            $node = $this->findById($nodeId);
            if (!$node) {
                throw new \RuntimeException("Node not found: $nodeId");
            }

            // Verificar que el nodo estÃƒÆ’Ã‚Â© activo
            if ($node['status'] !== 'active') {
                throw new \RuntimeException("Node is not active: $nodeId");
            }

            // Procesar metadatos JSON
            $actions = $node['actions'] ? json_decode($node['actions'], true) : [];
            $metadata = $node['metadata'] ? json_decode($node['metadata'], true) : [];
            $conditions = $node['conditions'] ? json_decode($node['conditions'], true) : [];

            $result = [
                'node_id' => $nodeId,
                'node_type' => $node['node_type'],
                'execution_time' => date('Y-m-d H:i:s'),
                'success' => false,
                'response' => null,
                'next_node' => null,
                'errors' => []
            ];

            // Evaluar condiciones antes de ejecutar
            if (!empty($conditions)) {
                $conditionResult = $this->evaluateNodeConditions($conditions, $context, $userInput);
                if (!$conditionResult['passed']) {
                    $result['errors'][] = 'Node conditions not met';
                    $result['condition_errors'] = $conditionResult['errors'];
                    return $result;
                }
            }

            // Ejecutar acciones segÃƒÆ’Ã‚Âºn el tipo de nodo
            switch ($node['node_type']) {
                case 'message':
                    $result = $this->executeMessageAction($node, $actions, $context, $userInput, $result);
                    break;

                case 'options':
                    $result = $this->executeOptionsAction($node, $actions, $context, $userInput, $result);
                    break;

                case 'form':
                    $result = $this->executeFormAction($node, $actions, $context, $userInput, $result);
                    break;

                case 'redirect':
                    $result = $this->executeRedirectAction($node, $actions, $context, $userInput, $result);
                    break;

                case 'condition':
                    $result = $this->executeConditionAction($node, $actions, $context, $userInput, $result);
                    break;

                case 'action':
                    $result = $this->executeCustomAction($node, $actions, $context, $userInput, $result);
                    break;

                default:
                    throw new \RuntimeException("Unknown node type: {$node['node_type']}");
            }

            Logger::info('Node action executed successfully', [
                'node_id' => $nodeId,
                'node_type' => $node['node_type'],
                'success' => $result['success']
            ]);

            return $result;
        } catch (Exception $e) {
            Logger::error('Error executing node action', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to execute node action: ' . $e->getMessage());
        }
    }

    /**
     * Validar el flujo de conversaciÃƒÆ’Ã‚Â³n desde un nodo raÃƒÆ’Ã‚Â­z
     *
     * @param string|int $startNodeId ID del nodo inicial
     * @param int $maxDepth Profundidad mÃƒÆ’Ã‚Â¡xima a validar
     * @return array Resultado de la validaciÃƒÆ’Ã‚Â³n
     * @throws \InvalidArgumentException Si los parÃƒÆ’Ã‚Â¡metros son invÃƒÆ’Ã‚Â¡lidos
     * @throws \RuntimeException Si hay error en la validaciÃƒÆ’Ã‚Â³n
     */
    public function validateNodeFlow($startNodeId, int $maxDepth = 50): array
    {
        if (empty($startNodeId)) {
            throw new \InvalidArgumentException('Start node ID cannot be empty');
        }

        if ($maxDepth <= 0 || $maxDepth > 100) {
            throw new \InvalidArgumentException('Max depth must be between 1 and 100');
        }

        try {
            $validation = [
                'is_valid' => true,
                'start_node_id' => $startNodeId,
                'total_nodes_checked' => 0,
                'max_depth_reached' => 0,
                'errors' => [],
                'warnings' => [],
                'orphaned_nodes' => [],
                'circular_references' => [],
                'dead_ends' => [],
                'validation_time' => date('Y-m-d H:i:s')
            ];

            // Obtener todos los nodos activos para validaciÃƒÆ’Ã‚Â³n
            $allNodes = $this->getActiveNodes();
            $nodesMap = [];
            foreach ($allNodes as $node) {
                $nodesMap[$node['id']] = $node;
            }

            // Validar nodo inicial
            if (!isset($nodesMap[$startNodeId])) {
                $validation['is_valid'] = false;
                $validation['errors'][] = "Start node not found or inactive: $startNodeId";
                return $validation;
            }

            // Realizar validaciÃƒÆ’Ã‚Â³n recursiva
            $visitedNodes = [];
            $pathStack = [];

            $this->validateNodeRecursive(
                $startNodeId,
                $nodesMap,
                $visitedNodes,
                $pathStack,
                0,
                $maxDepth,
                $validation
            );

            // Buscar nodos huÃƒÆ’Ã‚Â©rfanos
            $reachableNodes = array_keys($visitedNodes);
            foreach ($allNodes as $node) {
                if (!in_array($node['id'], $reachableNodes)) {
                    $validation['orphaned_nodes'][] = $node['id'];
                    $validation['warnings'][] = "Orphaned node found: {$node['id']} ({$node['name']})";
                }
            }

            // Determinar si la validaciÃƒÆ’Ã‚Â³n es exitosa
            $validation['is_valid'] = empty($validation['errors']);

            Logger::info('Node flow validation completed', [
                'start_node_id' => $startNodeId,
                'is_valid' => $validation['is_valid'],
                'total_nodes_checked' => $validation['total_nodes_checked'],
                'errors_count' => count($validation['errors']),
                'warnings_count' => count($validation['warnings'])
            ]);

            return $validation;
        } catch (Exception $e) {
            Logger::error('Error validating node flow', [
                'start_node_id' => $startNodeId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Failed to validate node flow: ' . $e->getMessage());
        }
    }

    /**
     * Limpiar cache de ÃƒÆ’Ã‚Â¡rboles de conversaciÃƒÆ’Ã‚Â³n
     */
    public function clearNodeTreeCache(): void
    {
        $this->nodeTreeCache = [];
        Logger::debug('Node tree cache cleared');
    }

    /**
     * Obtener estadÃƒÆ’Ã‚Â­sticas del chatbot
     *
     * @return array EstadÃƒÆ’Ã‚Â­sticas completas
     */
    public function getChatbotStats(): array
    {
        try {
            $sql = "SELECT 
                        node_type,
                        status,
                        COUNT(*) as count,
                        AVG(sort_order) as avg_sort_order
                    FROM `{$this->table}` 
                    GROUP BY node_type, status
                    ORDER BY node_type, status";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular totales
            $totalNodes = $this->countAll();
            $activeNodes = $this->countAll(['status' => 'active']);

            return [
                'total_nodes' => $totalNodes,
                'active_nodes' => $activeNodes,
                'inactive_nodes' => $totalNodes - $activeNodes,
                'by_type_and_status' => $stats,
                'generated_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            Logger::error('Error getting chatbot stats', ['error' => $e->getMessage()]);
            throw new \RuntimeException('Failed to get chatbot stats: ' . $e->getMessage());
        }
    }

    // =============================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS PRIVADOS DE UTILIDAD
    // =============================

    /**
     * Construir jerarquÃƒÆ’Ã‚Â­a de nodos recursivamente
     */
    private function buildNodeHierarchy(array $nodes, $parentId = null): array
    {
        $tree = [];

        foreach ($nodes as $node) {
            if ($node['parent_id'] == $parentId) {
                $node['children'] = $this->buildNodeHierarchy($nodes, $node['id']);
                $tree[] = $node;
            }
        }

        return $tree;
    }

    /**
     * Calcular profundidad del ÃƒÆ’Ã‚Â¡rbol
     */
    private function calculateTreeDepth(array $tree, int $currentDepth = 0): int
    {
        $maxDepth = $currentDepth;

        foreach ($tree as $node) {
            if (isset($node['children']) && !empty($node['children'])) {
                $childDepth = $this->calculateTreeDepth($node['children'], $currentDepth + 1);
                $maxDepth = max($maxDepth, $childDepth);
            }
        }

        return $maxDepth;
    }

    /**
     * Buscar nodos hijos de forma recursiva
     */
    private function findChildNodesRecursive($parentId, bool $activeOnly): array
    {
        $allChildren = [];
        $directChildren = $this->findChildNodes($parentId, $activeOnly, false);

        foreach ($directChildren as $child) {
            $allChildren[] = $child;
            $grandChildren = $this->findChildNodesRecursive($child['id'], $activeOnly);
            $allChildren = array_merge($allChildren, $grandChildren);
        }

        return $allChildren;
    }

    /**
     * Evaluar condiciones del nodo
     */
    private function evaluateNodeConditions(array $conditions, array $context, array $userInput): array
    {
        $result = [
            'passed' => true,
            'errors' => []
        ];

        // ImplementaciÃƒÆ’Ã‚Â³n bÃƒÆ’Ã‚Â¡sica de evaluaciÃƒÆ’Ã‚Â³n de condiciones
        foreach ($conditions as $condition) {
            if (!$this->evaluateSingleCondition($condition, $context, $userInput)) {
                $result['passed'] = false;
                $result['errors'][] = "Condition failed: " . json_encode($condition);
            }
        }

        return $result;
    }

    /**
     * Evaluar una condiciÃƒÆ’Ã‚Â³n individual
     */
    private function evaluateSingleCondition(array $condition, array $context, array $userInput): bool
    {
        // ImplementaciÃƒÆ’Ã‚Â³n bÃƒÆ’Ã‚Â¡sica - se puede extender segÃƒÆ’Ã‚Âºn necesidades
        $type = $condition['type'] ?? '';
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? '==';
        $value = $condition['value'] ?? '';

        $actualValue = null;

        switch ($type) {
            case 'context':
                $actualValue = $context[$field] ?? null;
                break;
            case 'user_input':
                $actualValue = $userInput[$field] ?? null;
                break;
            default:
                return false;
        }

        return $this->compareValues($actualValue, $operator, $value);
    }

    /**
     * Comparar valores segÃƒÆ’Ã‚Âºn operador
     */
    private function compareValues($actual, string $operator, $expected): bool
    {
        switch ($operator) {
            case '==':
            case '=':
                return $actual == $expected;
            case '!=':
                return $actual != $expected;
            case '>':
                return $actual > $expected;
            case '>=':
                return $actual >= $expected;
            case '<':
                return $actual < $expected;
            case '<=':
                return $actual <= $expected;
            case 'contains':
                return is_string($actual) && str_contains($actual, $expected);
            case 'not_contains':
                return is_string($actual) && !str_contains($actual, $expected);
            case 'in':
                return is_array($expected) && in_array($actual, $expected);
            case 'not_in':
                return is_array($expected) && !in_array($actual, $expected);
            default:
                return false;
        }
    }

    /**
     * Ejecutar acciÃƒÆ’Ã‚Â³n de mensaje
     */
    private function executeMessageAction(array $node, array $actions, array $context, array $userInput, array $result): array
    {
        $result['success'] = true;
        $result['response'] = [
            'type' => 'message',
            'content' => $actions['message'] ?? $node['description'] ?? '',
            'next_action' => $actions['next_action'] ?? 'wait_input'
        ];

        // Determinar siguiente nodo
        if (isset($actions['next_node_id'])) {
            $result['next_node'] = $actions['next_node_id'];
        }

        return $result;
    }

    /**
     * Ejecutar acciÃƒÆ’Ã‚Â³n de opciones
     */
    private function executeOptionsAction(array $node, array $actions, array $context, array $userInput, array $result): array
    {
        $result['success'] = true;
        $result['response'] = [
            'type' => 'options',
            'content' => $actions['message'] ?? $node['description'] ?? '',
            'options' => $actions['options'] ?? []
        ];

        return $result;
    }

    /**
     * Ejecutar acciÃƒÆ’Ã‚Â³n de formulario
     */
    private function executeFormAction(array $node, array $actions, array $context, array $userInput, array $result): array
    {
        $result['success'] = true;
        $result['response'] = [
            'type' => 'form',
            'content' => $actions['message'] ?? $node['description'] ?? '',
            'form_fields' => $actions['form_fields'] ?? []
        ];

        return $result;
    }

    /**
     * Ejecutar acciÃƒÆ’Ã‚Â³n de redirecciÃƒÆ’Ã‚Â³n
     */
    private function executeRedirectAction(array $node, array $actions, array $context, array $userInput, array $result): array
    {
        $result['success'] = true;
        $result['response'] = [
            'type' => 'redirect',
            'url' => $actions['redirect_url'] ?? '',
            'message' => $actions['message'] ?? 'Redirigiendo...'
        ];

        return $result;
    }

    /**
     * Ejecutar acciÃƒÆ’Ã‚Â³n condicional
     */
    private function executeConditionAction(array $node, array $actions, array $context, array $userInput, array $result): array
    {
        $conditions = $node['conditions'] ? json_decode($node['conditions'], true) : [];
        $conditionResult = $this->evaluateNodeConditions($conditions, $context, $userInput);

        $result['success'] = true;
        $result['response'] = [
            'type' => 'condition',
            'condition_passed' => $conditionResult['passed']
        ];

        // Determinar siguiente nodo basado en la condiciÃƒÆ’Ã‚Â³n
        if ($conditionResult['passed']) {
            $result['next_node'] = $actions['success_node_id'] ?? null;
        } else {
            $result['next_node'] = $actions['failure_node_id'] ?? null;
        }

        return $result;
    }

    /**
     * Ejecutar acciÃƒÆ’Ã‚Â³n personalizada
     */
    private function executeCustomAction(array $node, array $actions, array $context, array $userInput, array $result): array
    {
        $result['success'] = true;
        $result['response'] = [
            'type' => 'custom_action',
            'action_data' => $actions
        ];

        // Procesar acciones personalizadas segÃƒÆ’Ã‚Âºn configuraciÃƒÆ’Ã‚Â³n
        if (isset($actions['webhook_url'])) {
            // Simular llamada a webhook
            $result['response']['webhook_called'] = true;
        }

        return $result;
    }

    /**
     * Validar nodo de forma recursiva
     */
    private function validateNodeRecursive(
        $nodeId,
        array $nodesMap,
        array &$visitedNodes,
        array &$pathStack,
        int $currentDepth,
        int $maxDepth,
        array &$validation
    ): void {
        // Verificar profundidad mÃƒÆ’Ã‚Â¡xima
        if ($currentDepth >= $maxDepth) {
            $validation['warnings'][] = "Maximum depth reached at node: $nodeId";
            $validation['max_depth_reached'] = max($validation['max_depth_reached'], $currentDepth);
            return;
        }

        // Detectar referencias circulares
        if (in_array($nodeId, $pathStack)) {
            $validation['is_valid'] = false;
            $validation['errors'][] = "Circular reference detected: " . implode(' -> ', $pathStack) . " -> $nodeId";
            $validation['circular_references'][] = $nodeId;
            return;
        }

        // Marcar como visitado
        $visitedNodes[$nodeId] = true;
        $pathStack[] = $nodeId;
        $validation['total_nodes_checked']++;

        $node = $nodesMap[$nodeId] ?? null;
        if (!$node) {
            $validation['is_valid'] = false;
            $validation['errors'][] = "Referenced node not found: $nodeId";
            array_pop($pathStack);
            return;
        }

        // Validar estructura del nodo
        $this->validateNodeStructure($node, $validation);

        // Encontrar nodos siguientes y validar recursivamente
        $nextNodes = $this->getNextNodesFromNode($node);

        if (empty($nextNodes)) {
            $validation['dead_ends'][] = $nodeId;
        } else {
            foreach ($nextNodes as $nextNodeId) {
                if (!isset($visitedNodes[$nextNodeId])) {
                    $this->validateNodeRecursive(
                        $nextNodeId,
                        $nodesMap,
                        $visitedNodes,
                        $pathStack,
                        $currentDepth + 1,
                        $maxDepth,
                        $validation
                    );
                }
            }
        }

        array_pop($pathStack);
    }

    /**
     * Validar estructura individual del nodo
     */
    private function validateNodeStructure(array $node, array &$validation): void
    {
        // Validar tipo de nodo
        if (!in_array($node['node_type'], self::VALID_NODE_TYPES)) {
            $validation['errors'][] = "Invalid node type '{$node['node_type']}' in node: {$node['id']}";
        }

        // Validar estado
        if (!in_array($node['status'], self::VALID_STATUSES)) {
            $validation['errors'][] = "Invalid status '{$node['status']}' in node: {$node['id']}";
        }

        // Validar campos requeridos
        if (empty($node['name'])) {
            $validation['warnings'][] = "Node {$node['id']} has no name";
        }

        // Validar JSON vÃƒÆ’Ã‚Â¡lido en campos metadata, conditions, actions
        $jsonFields = ['metadata', 'conditions', 'actions'];
        foreach ($jsonFields as $field) {
            if (!empty($node[$field]) && json_decode($node[$field]) === null && json_last_error() !== JSON_ERROR_NONE) {
                $validation['errors'][] = "Invalid JSON in field '$field' for node: {$node['id']}";
            }
        }
    }

    /**
     * Obtener IDs de nodos siguientes desde un nodo
     */
    private function getNextNodesFromNode(array $node): array
    {
        $nextNodes = [];

        // Buscar en las acciones del nodo
        $actions = $node['actions'] ? json_decode($node['actions'], true) : [];

        if (isset($actions['next_node_id'])) {
            $nextNodes[] = $actions['next_node_id'];
        }

        if (isset($actions['success_node_id'])) {
            $nextNodes[] = $actions['success_node_id'];
        }

        if (isset($actions['failure_node_id'])) {
            $nextNodes[] = $actions['failure_node_id'];
        }

        // Para nodos de opciones, buscar en las opciones
        if (isset($actions['options']) && is_array($actions['options'])) {
            foreach ($actions['options'] as $option) {
                if (isset($option['next_node_id'])) {
                    $nextNodes[] = $option['next_node_id'];
                }
            }
        }

        return array_unique(array_filter($nextNodes));
    }

    /**
     * Override del mÃƒÆ’Ã‚Â©todo store para validaciÃƒÆ’Ã‚Â³n adicional
     */
    public function store(array $data)
    {
        // Validar tipo de nodo
        if (isset($data['node_type']) && !in_array($data['node_type'], self::VALID_NODE_TYPES)) {
            throw new \InvalidArgumentException("Invalid node type: {$data['node_type']}");
        }

        // Validar estado
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES)) {
            throw new \InvalidArgumentException("Invalid status: {$data['status']}");
        }

        // Validar y serializar campos JSON
        $jsonFields = ['metadata', 'conditions', 'actions'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        // Limpiar cache despuÃƒÆ’Ã‚Â©s de crear
        $result = parent::store($data);
        if ($result) {
            $this->clearNodeTreeCache();
        }

        return $result;
    }

    /**
     * Override del mÃƒÆ’Ã‚Â©todo update para validaciÃƒÆ’Ã‚Â³n adicional
     */
    public function update($id, array $data): bool
    {
        // Validar tipo de nodo
        if (isset($data['node_type']) && !in_array($data['node_type'], self::VALID_NODE_TYPES)) {
            throw new \InvalidArgumentException("Invalid node type: {$data['node_type']}");
        }

        // Validar estado
        if (isset($data['status']) && !in_array($data['status'], self::VALID_STATUSES)) {
            throw new \InvalidArgumentException("Invalid status: {$data['status']}");
        }

        // Validar y serializar campos JSON
        $jsonFields = ['metadata', 'conditions', 'actions'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field]);
            }
        }

        // Limpiar cache despuÃƒÆ’Ã‚Â©s de actualizar
        $result = parent::update($id, $data);
        if ($result) {
            $this->clearNodeTreeCache();
        }

        return $result;
    }

    /**
     * Override del mÃƒÆ’Ã‚Â©todo delete para limpiar cache
     */
    public function delete($id): bool
    {
        $result = parent::delete($id);
        if ($result) {
            $this->clearNodeTreeCache();
        }

        return $result;
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de compatibilidad hacia atrÃƒÆ’Ã‚Â¡s - Obtener todos los nodos activos
     */
    public function getAllActive(): array
    {
        return $this->getActiveNodes();
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de compatibilidad hacia atrÃƒÆ’Ã‚Â¡s - Obtener nodo por ID
     */
    public function getById($id): ?array
    {
        return $this->findById($id);
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de compatibilidad hacia atrÃƒÆ’Ã‚Â¡s - Obtener nodos por tipo
     */
    public function getByType(string $type): array
    {
        return $this->findBy('node_type', $type);
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de compatibilidad hacia atrÃƒÆ’Ã‚Â¡s - Verificar existencia
     */
    public function exists($id): bool
    {
        return $this->findById($id) !== null;
    }

    /**
     * MÃƒÆ’Ã‚Â©todo de compatibilidad hacia atrÃƒÆ’Ã‚Â¡s - Obtener estadÃƒÆ’Ã‚Â­sticas
     */
    public function getStats(): array
    {
        return $this->getChatbotStats()['by_type_and_status'] ?? [];
    }

    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS CRUD ENCAPSULADOS ESTÃƒÆ’Ã‚ÂNDAR
    // ==========================================

    /**
     * Crear nuevo chatbot_node con validaciones
     * @param array $data Datos del nuevo chatbot_node
     * @return mixed ID del nuevo chatbot_node o false en caso de error
     */
    public function createChatbotNode(array $data): mixed
    {
        try {
            $this->validateChatbotNodeData($data);
            $id = $this->store($data);
            $this->invalidateChatbotNodeCache();

            Logger::info('ChatbotNode created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating chatbot_node', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener chatbot_node por ID
     * @param mixed $id ID del chatbot_node
     * @return array|null Datos del chatbot_node o null si no existe
     */
    public function getChatbotNode($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving chatbot_node', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar chatbot_node con validaciones
     * @param mixed $id ID del chatbot_node a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualizaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function updateChatbotNode($id, array $data): bool
    {
        try {
            $this->validateChatbotNodeData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateChatbotNodeCache();
                Logger::info('ChatbotNode updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating chatbot_node', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar chatbot_node con validaciones
     * @param mixed $id ID del chatbot_node a eliminar
     * @return bool True si la eliminaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function deleteChatbotNode($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateChatbotNodeCache();
                Logger::info('ChatbotNode deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting chatbot_node', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar chatbot_nodes con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @param int $page PÃƒÆ’Ã‚Â¡gina actual
     * @param int $limit Registros por pÃƒÆ’Ã‚Â¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de chatbot_nodes
     */
    public function searchChatbotNodes(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching chatbot_nodes', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de chatbot_nodes con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @return int NÃƒÆ’Ã‚Âºmero total de chatbot_nodes
     */
    public function countChatbotNodes(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting chatbot_nodes', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS DE VALIDACIÃƒÆ’Ã¢â‚¬Å“N ESPECÃƒÆ’Ã‚ÂFICOS
    // ==========================================

    /**
     * Validar datos especÃƒÆ’Ã‚Â­ficos de chatbot_nodes
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualizaciÃƒÆ’Ã‚Â³n (opcional)
     * @throws \InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    private function validateChatbotNodeData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones especÃƒÆ’Ã‚Â­ficas del modelo
    }

    /**
     * Invalidar cache especÃƒÆ’Ã‚Â­fico de chatbot_nodes
     */
    public function invalidateChatbotNodeCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['chatbot_nodes', 'chatbot_node_core', 'chatbot_node_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating chatbot_node cache', [], $e);
            return 0;
        }
    }
}
