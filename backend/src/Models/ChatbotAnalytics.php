<?php declare(strict_types=1);
namespace Models;

use Exception;
use PDO;
use Utils\Logger;

/**
 * Modelo para el registro de analÃƒÆ’Ã‚Â­ticas del chatbot.
 * Guarda eventos, interacciones y datos de uso con anÃƒÆ’Ã‚Â¡lisis avanzados.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class ChatbotAnalytics extends BaseModel
{
    protected string $table = 'chatbot_analytics';
    /*
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬ÂÃ‚Â§ CORRECCIÃƒÆ’Ã¢â‚¬Å“N AUTOMÃƒÆ’Ã‚ÂTICA APLICADA
     * Modelo: ChatbotAnalytics
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ÃƒÂ¢Ã…Â¾Ã¢â‚¬Â¢ Campos aÃƒÆ’Ã‚Â±adidos: ['user_id', 'event_type', 'event_data', 'processed']
     * ÃƒÂ¢Ã‚ÂÃ…â€™ Campos removidos: ['candidate_id', 'node_id', 'option_id', 'action_type', 'data']
     * ÃƒÂ°Ã…Â¸Ã¢â‚¬Å“Ã…Â  Total campos fillable: 8
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */

    protected string $primaryKey = 'id';

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected array $fillable = [
        'session_id',
        'user_id',
        'event_type',
        'event_data',
        'timestamp',
        'user_agent',
        'ip_address',
        'processed',
    ];

    /**
     * Campos que deben ocultarse en arrays/JSON
     */
    protected array $hidden = [
        'ip_address',
        'user_agent',
        'sensitive_data'
    ];

    /**
     * Cache para reportes analÃƒÆ’Ã‚Â­ticos pesados (tiempo en minutos)
     */
    private const ANALYTICS_CACHE_TTL = 60;
    private const REPORTS_CACHE_TTL = 180;

    /**
     * Tipos de acciÃƒÆ’Ã‚Â³n vÃƒÆ’Ã‚Â¡lidos para anÃƒÆ’Ã‚Â¡lisis
     */
    private const VALID_ACTION_TYPES = [
        'node_visit',
        'option_selected',
        'form_submitted',
        'conversation_started',
        'conversation_ended',
        'error_occurred',
        'timeout',
        'external_redirect'
    ];

    /**
     * Crear nuevo registro de analytics con validaciÃƒÆ’Ã‚Â³n mejorada
     * 
     * @param array $data Datos del evento
     * @return mixed ID del registro creado
     * @throws InvalidArgumentException Si los datos son invÃƒÆ’Ã‚Â¡lidos
     * @throws RuntimeException Si hay error en BD
     */
    public function store($data)
    {
        // Validar datos requeridos
        $requiredFields = ['session_id', 'action_type'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Campo requerido faltante: $field");
            }
        }

        // Validar tipo de acciÃƒÆ’Ã‚Â³n
        if (!in_array($data['action_type'], self::VALID_ACTION_TYPES)) {
            throw new \InvalidArgumentException("Tipo de acciÃƒÆ’Ã‚Â³n invÃƒÆ’Ã‚Â¡lido: {$data['action_type']}");
        }

        // Asegurar timestamp si no se proporciona
        if (empty($data['timestamp'])) {
            $data['timestamp'] = date('Y-m-d H:i:s');
        }

        // Codificar data como JSON si es array
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }

        try {
            $id = parent::store($data);

            Logger::info('Registro de analytics creado', [
                'model' => static::class,
                'id' => $id,
                'session_id' => $data['session_id'],
                'action_type' => $data['action_type']
            ]);

            return $id;
        } catch (Exception $e) {
            Logger::error('Error al crear registro de analytics', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al crear registro de analytics: ' . $e->getMessage());
        }
    }

    /**
     * Obtener flujo completo de conversaciÃƒÆ’Ã‚Â³n por sesiÃƒÆ’Ã‚Â³n
     * 
     * @param string $sessionId ID de la sesiÃƒÆ’Ã‚Â³n
     * @return array Secuencia ordenada de eventos
     * @throws RuntimeException Si hay error en BD
     */
    public function getConversationFlow(string $sessionId): array
    {
        if (empty($sessionId)) {
            throw new \InvalidArgumentException('Session ID no puede estar vacÃƒÆ’Ã‚Â­o');
        }

        $cacheKey = "conversation_flow_$sessionId";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $query = "
                SELECT 
                    id,
                    node_id,
                    option_id,
                    action_type,
                    timestamp,
                    data,
                    candidate_id
                FROM {$this->table} 
                WHERE session_id = :session_id 
                ORDER BY timestamp ASC, id ASC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':session_id', $sessionId, PDO::PARAM_STR);
            $stmt->execute();

            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar y enriquecer datos
            foreach ($events as &$event) {
                if (!empty($event['data'])) {
                    $event['data'] = json_decode($event['data'], true) ?: [];
                }
                $event['duration_from_start'] = $this->calculateDurationFromStart($events[0]['timestamp'], $event['timestamp']);
            }

            // Cache del resultado
            $this->cache[$cacheKey] = $events;

            Logger::debug('Flujo de conversaciÃƒÆ’Ã‚Â³n obtenido', [
                'session_id' => $sessionId,
                'events_count' => count($events)
            ]);

            return $events;
        } catch (Exception $e) {
            Logger::error('Error al obtener flujo de conversaciÃƒÆ’Ã‚Â³n', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al obtener flujo de conversaciÃƒÆ’Ã‚Â³n: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadÃƒÆ’Ã‚Â­sticas por nodo
     * 
     * @param array $filters Filtros opcionales (date_from, date_to, node_ids)
     * @return array EstadÃƒÆ’Ã‚Â­sticas agrupadas por nodo
     * @throws RuntimeException Si hay error en BD
     */
    public function getNodeStatistics(array $filters = []): array
    {
        $cacheKey = 'node_stats_' . md5(json_encode($filters));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $whereConditions = ['node_id IS NOT NULL'];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            if (!empty($filters['node_ids']) && is_array($filters['node_ids'])) {
                $placeholders = str_repeat('?,', count($filters['node_ids']) - 1) . '?';
                $whereConditions[] = "node_id IN ($placeholders)";
                foreach ($filters['node_ids'] as $nodeId) {
                    $params[] = $nodeId;
                }
            }

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT 
                    node_id,
                    COUNT(*) as total_visits,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(DISTINCT candidate_id) as unique_candidates,
                    AVG(CASE WHEN action_type = 'node_visit' THEN 1 ELSE 0 END) as visit_rate,
                    MIN(timestamp) as first_visit,
                    MAX(timestamp) as last_visit,
                    AVG(
                        CASE 
                            WHEN data IS NOT NULL AND JSON_EXTRACT(data, '$.duration') IS NOT NULL 
                            THEN JSON_EXTRACT(data, '$.duration')
                            ELSE NULL 
                        END
                    ) as avg_time_spent
                FROM {$this->table}
                WHERE $whereClause
                GROUP BY node_id
                ORDER BY total_visits DESC
            ";

            $stmt = $this->db->prepare($query);

            // Bind parameters
            $paramIndex = 0;
            foreach ($params as $key => $value) {
                if (is_string($key)) {
                    $stmt->bindValue($key, $value);
                } else {
                    $stmt->bindValue($paramIndex + 1, $value);
                    $paramIndex++;
                }
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enriquecer con mÃƒÆ’Ã‚Â©tricas calculadas
            foreach ($results as &$result) {
                $result['conversion_rate'] = $this->calculateNodeConversionRate($result['node_id'], $filters);
                $result['drop_off_rate'] = $this->calculateNodeDropOffRate($result['node_id'], $filters);
            }

            $this->cache[$cacheKey] = $results;

            Logger::debug('EstadÃƒÆ’Ã‚Â­sticas de nodos obtenidas', [
                'filters' => $filters,
                'nodes_count' => count($results)
            ]);

            return $results;
        } catch (Exception $e) {
            Logger::error('Error al obtener estadÃƒÆ’Ã‚Â­sticas de nodos', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al obtener estadÃƒÆ’Ã‚Â­sticas de nodos: ' . $e->getMessage());
        }
    }

    /**
     * Obtener estadÃƒÆ’Ã‚Â­sticas por opciÃƒÆ’Ã‚Â³n seleccionada
     * 
     * @param array $filters Filtros opcionales
     * @return array EstadÃƒÆ’Ã‚Â­sticas agrupadas por opciÃƒÆ’Ã‚Â³n
     * @throws RuntimeException Si hay error en BD
     */
    public function getOptionStatistics(array $filters = []): array
    {
        $cacheKey = 'option_stats_' . md5(json_encode($filters));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $whereConditions = ['option_id IS NOT NULL', "action_type = 'option_selected'"];
            $params = [];

            // Aplicar filtros de fecha
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT 
                    node_id,
                    option_id,
                    COUNT(*) as selection_count,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(DISTINCT candidate_id) as unique_candidates,
                    MIN(timestamp) as first_selection,
                    MAX(timestamp) as last_selection,
                    ROUND(
                        COUNT(*) * 100.0 / (
                            SELECT COUNT(*) 
                            FROM {$this->table} 
                            WHERE node_id = ca.node_id AND action_type = 'node_visit'
                            AND timestamp >= COALESCE(:date_from2, '1900-01-01')
                            AND timestamp <= COALESCE(:date_to2, '2999-12-31')
                        ), 2
                    ) as selection_percentage
                FROM {$this->table} ca
                WHERE $whereClause
                GROUP BY node_id, option_id
                ORDER BY node_id, selection_count DESC
            ";

            $stmt = $this->db->prepare($query);

            // Bind parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            // Bind additional parameters for subquery
            $stmt->bindValue(':date_from2', $filters['date_from'] ?? '1900-01-01');
            $stmt->bindValue(':date_to2', ($filters['date_to'] ?? '2999-12-31') . ' 23:59:59');

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->cache[$cacheKey] = $results;

            Logger::debug('EstadÃƒÆ’Ã‚Â­sticas de opciones obtenidas', [
                'filters' => $filters,
                'options_count' => count($results)
            ]);

            return $results;
        } catch (Exception $e) {
            Logger::error('Error al obtener estadÃƒÆ’Ã‚Â­sticas de opciones', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al obtener estadÃƒÆ’Ã‚Â­sticas de opciones: ' . $e->getMessage());
        }
    }

    /**
     * Obtener anÃƒÆ’Ã‚Â¡lisis de sesiones con mÃƒÆ’Ã‚Â©tricas avanzadas
     * 
     * @param array $filters Filtros opcionales
     * @return array AnÃƒÆ’Ã‚Â¡lisis de sesiones
     * @throws RuntimeException Si hay error en BD
     */
    public function getSessionAnalytics(array $filters = []): array
    {
        $cacheKey = 'session_analytics_' . md5(json_encode($filters));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $whereConditions = ['1=1'];
            $params = [];

            // Aplicar filtros
            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'MIN(timestamp) >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'MIN(timestamp) <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $havingClause = count($whereConditions) > 1 ? 'HAVING ' . implode(' AND ', array_slice($whereConditions, 1)) : '';

            $query = "
                SELECT 
                    session_id,
                    candidate_id,
                    COUNT(*) as total_events,
                    COUNT(DISTINCT node_id) as nodes_visited,
                    COUNT(CASE WHEN action_type = 'option_selected' THEN 1 END) as options_selected,
                    MIN(timestamp) as session_start,
                    MAX(timestamp) as session_end,
                    TIMESTAMPDIFF(SECOND, MIN(timestamp), MAX(timestamp)) as duration_seconds,
                    CASE 
                        WHEN MAX(action_type = 'conversation_ended') = 1 THEN 'completed'
                        WHEN MAX(action_type = 'error_occurred') = 1 THEN 'error'
                        WHEN MAX(action_type = 'timeout') = 1 THEN 'timeout'
                        ELSE 'abandoned'
                    END as session_status,
                    COUNT(CASE WHEN action_type = 'error_occurred' THEN 1 END) as errors_count
                FROM {$this->table}
                WHERE 1=1
                GROUP BY session_id, candidate_id
                $havingClause
                ORDER BY session_start DESC
                LIMIT 1000
            ";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular mÃƒÆ’Ã‚Â©tricas agregadas
            $analytics = [
                'sessions' => $results,
                'summary' => [
                    'total_sessions' => count($results),
                    'completed_sessions' => count(array_filter($results, fn($s) => $s['session_status'] === 'completed')),
                    'abandoned_sessions' => count(array_filter($results, fn($s) => $s['session_status'] === 'abandoned')),
                    'avg_duration' => count($results) > 0 ? array_sum(array_column($results, 'duration_seconds')) / count($results) : 0,
                    'avg_nodes_per_session' => count($results) > 0 ? array_sum(array_column($results, 'nodes_visited')) / count($results) : 0,
                    'completion_rate' => count($results) > 0 ? (count(array_filter($results, fn($s) => $s['session_status'] === 'completed')) / count($results)) * 100 : 0
                ]
            ];

            $this->cache[$cacheKey] = $analytics;

            Logger::debug('AnÃƒÆ’Ã‚Â¡lisis de sesiones obtenido', [
                'filters' => $filters,
                'sessions_count' => count($results),
                'completion_rate' => $analytics['summary']['completion_rate']
            ]);

            return $analytics;
        } catch (Exception $e) {
            Logger::error('Error al obtener anÃƒÆ’Ã‚Â¡lisis de sesiones', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al obtener anÃƒÆ’Ã‚Â¡lisis de sesiones: ' . $e->getMessage());
        }
    }

    /**
     * Obtener las rutas mÃƒÆ’Ã‚Â¡s frecuentes en el chatbot
     * 
     * @param array $filters Filtros opcionales
     * @param int $limit LÃƒÆ’Ã‚Â­mite de rutas a retornar
     * @return array Rutas mÃƒÆ’Ã‚Â¡s populares
     * @throws RuntimeException Si hay error en BD
     */
    public function getMostPopularPaths(array $filters = [], int $limit = 10): array
    {
        $cacheKey = "popular_paths_" . md5(json_encode($filters) . $limit);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $whereConditions = ['node_id IS NOT NULL'];
            $params = [];

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            // Obtener secuencias de nodos por sesiÃƒÆ’Ã‚Â³n
            $query = "
                WITH session_paths AS (
                    SELECT 
                        session_id,
                        GROUP_CONCAT(
                            DISTINCT node_id 
                            ORDER BY timestamp, id 
                            SEPARATOR ' -> '
                        ) as path,
                        COUNT(*) as path_length,
                        MIN(timestamp) as path_start,
                        MAX(timestamp) as path_end
                    FROM {$this->table}
                    WHERE $whereClause
                    GROUP BY session_id
                    HAVING path_length >= 2
                )
                SELECT 
                    path,
                    COUNT(*) as frequency,
                    AVG(path_length) as avg_length,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    MIN(path_start) as first_occurrence,
                    MAX(path_end) as last_occurrence
                FROM session_paths
                GROUP BY path
                ORDER BY frequency DESC
                LIMIT :limit
            ";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Enriquecer con anÃƒÆ’Ã‚Â¡lisis adicional
            foreach ($results as &$result) {
                $pathNodes = explode(' -> ', $result['path']);
                $result['path_nodes'] = $pathNodes;
                $result['path_depth'] = count($pathNodes);
                $result['completion_rate'] = $this->calculatePathCompletionRate($result['path'], $filters);
            }

            $this->cache[$cacheKey] = $results;

            Logger::debug('Rutas populares obtenidas', [
                'filters' => $filters,
                'limit' => $limit,
                'paths_count' => count($results)
            ]);

            return $results;
        } catch (Exception $e) {
            Logger::error('Error al obtener rutas populares', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al obtener rutas populares: ' . $e->getMessage());
        }
    }

    /**
     * Calcular tasa de abandono general y por nodo
     * 
     * @param array $filters Filtros opcionales
     * @return array MÃƒÆ’Ã‚Â©tricas de abandono
     * @throws RuntimeException Si hay error en BD
     */
    public function getAbandonmentRate(array $filters = []): array
    {
        $cacheKey = 'abandonment_rate_' . md5(json_encode($filters));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            $whereConditions = ['1=1'];
            $params = [];

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT 
                    'overall' as scope,
                    COUNT(DISTINCT session_id) as total_sessions,
                    COUNT(DISTINCT CASE 
                        WHEN session_id NOT IN (
                            SELECT DISTINCT session_id 
                            FROM {$this->table} 
                            WHERE action_type = 'conversation_ended'
                            AND $whereClause
                        ) THEN session_id 
                    END) as abandoned_sessions,
                    ROUND(
                        COUNT(DISTINCT CASE 
                            WHEN session_id NOT IN (
                                SELECT DISTINCT session_id 
                                FROM {$this->table} 
                                WHERE action_type = 'conversation_ended'
                                AND $whereClause
                            ) THEN session_id 
                        END) * 100.0 / COUNT(DISTINCT session_id), 2
                    ) as abandonment_rate
                FROM {$this->table}
                WHERE $whereClause

                UNION ALL

                SELECT 
                    CONCAT('node_', node_id) as scope,
                    COUNT(DISTINCT session_id) as total_sessions,
                    COUNT(DISTINCT CASE 
                        WHEN session_id NOT IN (
                            SELECT DISTINCT s2.session_id 
                            FROM {$this->table} s2 
                            WHERE s2.node_id != s1.node_id 
                            AND s2.timestamp > s1.timestamp 
                            AND s2.session_id = s1.session_id
                            AND $whereClause
                        ) THEN session_id 
                    END) as abandoned_sessions,
                    ROUND(
                        COUNT(DISTINCT CASE 
                            WHEN session_id NOT IN (
                                SELECT DISTINCT s2.session_id 
                                FROM {$this->table} s2 
                                WHERE s2.node_id != s1.node_id 
                                AND s2.timestamp > s1.timestamp 
                                AND s2.session_id = s1.session_id
                                AND $whereClause
                            ) THEN session_id 
                        END) * 100.0 / COUNT(DISTINCT session_id), 2
                    ) as abandonment_rate
                FROM {$this->table} s1
                WHERE node_id IS NOT NULL AND $whereClause
                GROUP BY node_id
                HAVING total_sessions >= 10
                ORDER BY abandonment_rate DESC
            ";

            $stmt = $this->db->prepare($query);

            // Bind parameters for each part of the UNION
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }

            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->cache[$cacheKey] = $results;

            Logger::debug('Tasa de abandono calculada', [
                'filters' => $filters,
                'scopes_count' => count($results)
            ]);

            return $results;
        } catch (Exception $e) {
            Logger::error('Error al calcular tasa de abandono', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al calcular tasa de abandono: ' . $e->getMessage());
        }
    }

    /**
     * Generar reporte completo de analÃƒÆ’Ã‚Â­ticas
     * 
     * @param array $filters Filtros opcionales
     * @return array Reporte completo con todas las mÃƒÆ’Ã‚Â©tricas
     * @throws RuntimeException Si hay error en BD
     */
    public function generateAnalyticsReport(array $filters = []): array
    {
        $cacheKey = 'analytics_report_' . md5(json_encode($filters));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        try {
            Logger::info('Generando reporte completo de analytics', ['filters' => $filters]);

            $report = [
                'period' => [
                    'from' => $filters['date_from'] ?? null,
                    'to' => $filters['date_to'] ?? null,
                    'generated_at' => date('Y-m-d H:i:s')
                ],
                'session_analytics' => $this->getSessionAnalytics($filters),
                'node_statistics' => $this->getNodeStatistics($filters),
                'option_statistics' => $this->getOptionStatistics($filters),
                'popular_paths' => $this->getMostPopularPaths($filters, 15),
                'abandonment_rates' => $this->getAbandonmentRate($filters),
                'performance_metrics' => $this->calculatePerformanceMetrics($filters)
            ];

            // Cache del reporte con TTL extendido
            $this->cache[$cacheKey] = $report;

            Logger::info('Reporte de analytics generado exitosamente', [
                'filters' => $filters,
                'report_sections' => array_keys($report),
                'cache_key' => $cacheKey
            ]);

            return $report;
        } catch (Exception $e) {
            Logger::error('Error al generar reporte de analytics', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al generar reporte de analytics: ' . $e->getMessage());
        }
    }

    /**
     * Limpiar datos antiguos de analytics con polÃƒÆ’Ã‚Â­ticas de retenciÃƒÆ’Ã‚Â³n
     * 
     * @param int $daysToKeep DÃƒÆ’Ã‚Â­as a conservar (por defecto 90)
     * @return int NÃƒÆ’Ã‚Âºmero de registros eliminados
     * @throws RuntimeException Si hay error en BD
     */
    public function cleanOldData(int $daysToKeep = 90): int
    {
        if ($daysToKeep < 1) {
            throw new \InvalidArgumentException('Los dÃƒÆ’Ã‚Â­as a conservar deben ser mayor a 0');
        }

        try {
            $this->db->beginTransaction();

            // Contar registros antes de eliminar
            $countQuery = "SELECT COUNT(*) as total FROM {$this->table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute([$daysToKeep]);
            $totalToDelete = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Eliminar registros antiguos
            $deleteQuery = "DELETE FROM {$this->table} WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)";
            $deleteStmt = $this->db->prepare($deleteQuery);
            $result = $deleteStmt->execute([$daysToKeep]);

            $deletedRows = $deleteStmt->rowCount();

            // Limpiar cache despuÃƒÆ’Ã‚Â©s de la eliminaciÃƒÆ’Ã‚Â³n
            $this->cache = [];

            $this->db->commit();

            Logger::info('Limpieza de analytics completada', [
                'days_kept' => $daysToKeep,
                'total_to_delete' => $totalToDelete,
                'deleted_rows' => $deletedRows,
                'cache_cleared' => true
            ]);

            return $deletedRows;
        } catch (Exception $e) {
            $this->db->rollBack();

            Logger::error('Error al limpiar datos antiguos de analytics', [
                'days_to_keep' => $daysToKeep,
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException('Error al limpiar datos antiguos: ' . $e->getMessage());
        }
    }

    /**
     * MÃƒÆ’Ã‚Â©todos privados de apoyo
     */

    /**
     * Calcular duraciÃƒÆ’Ã‚Â³n desde el inicio de la sesiÃƒÆ’Ã‚Â³n
     * 
     * @param string $startTime Timestamp de inicio
     * @param string $currentTime Timestamp actual
     * @return int DuraciÃƒÆ’Ã‚Â³n en segundos
     */
    private function calculateDurationFromStart(string $startTime, string $currentTime): int
    {
        return strtotime($currentTime) - strtotime($startTime);
    }

    /**
     * Calcular tasa de conversiÃƒÆ’Ã‚Â³n para un nodo especÃƒÆ’Ã‚Â­fico
     * 
     * @param string $nodeId ID del nodo
     * @param array $filters Filtros aplicados
     * @return float Tasa de conversiÃƒÆ’Ã‚Â³n
     */
    private function calculateNodeConversionRate(string $nodeId, array $filters = []): float
    {
        try {
            $whereConditions = ['node_id = :node_id'];
            $params = [':node_id' => $nodeId];

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT 
                    COUNT(DISTINCT session_id) as total_visits,
                    COUNT(DISTINCT CASE 
                        WHEN session_id IN (
                            SELECT session_id 
                            FROM {$this->table} 
                            WHERE action_type = 'conversation_ended'
                            AND $whereClause
                        ) THEN session_id 
                    END) as completed_sessions
                FROM {$this->table}
                WHERE $whereClause
            ";

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['total_visits'] > 0
                ? round(($result['completed_sessions'] / $result['total_visits']) * 100, 2)
                : 0.0;
        } catch (Exception $e) {
            Logger::warning('Error calculando tasa de conversiÃƒÆ’Ã‚Â³n de nodo', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calcular tasa de abandono para un nodo especÃƒÆ’Ã‚Â­fico
     * 
     * @param string $nodeId ID del nodo
     * @param array $filters Filtros aplicados
     * @return float Tasa de abandono
     */
    private function calculateNodeDropOffRate(string $nodeId, array $filters = []): float
    {
        try {
            $whereConditions = ['node_id = :node_id'];
            $params = [':node_id' => $nodeId];

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT 
                    COUNT(DISTINCT session_id) as total_visits,
                    COUNT(DISTINCT CASE 
                        WHEN session_id NOT IN (
                            SELECT DISTINCT session_id 
                            FROM {$this->table} s2
                            WHERE s2.node_id != :node_id2 
                            AND s2.timestamp > s1.timestamp 
                            AND s2.session_id = s1.session_id
                            AND $whereClause
                        ) THEN session_id 
                    END) as dropped_off
                FROM {$this->table} s1
                WHERE $whereClause
            ";

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':node_id2', $nodeId);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['total_visits'] > 0
                ? round(($result['dropped_off'] / $result['total_visits']) * 100, 2)
                : 0.0;
        } catch (Exception $e) {
            Logger::warning('Error calculando tasa de abandono de nodo', [
                'node_id' => $nodeId,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calcular tasa de completaciÃƒÆ’Ã‚Â³n para una ruta especÃƒÆ’Ã‚Â­fica
     * 
     * @param string $path Ruta de nodos
     * @param array $filters Filtros aplicados
     * @return float Tasa de completaciÃƒÆ’Ã‚Â³n
     */
    private function calculatePathCompletionRate(string $path, array $filters = []): float
    {
        try {
            // Esta es una implementaciÃƒÆ’Ã‚Â³n simplificada
            // En producciÃƒÆ’Ã‚Â³n podrÃƒÆ’Ã‚Â­a ser mÃƒÆ’Ã‚Â¡s sofisticada
            $pathNodes = explode(' -> ', $path);
            $lastNode = end($pathNodes);

            return $this->calculateNodeConversionRate($lastNode, $filters);
        } catch (Exception $e) {
            Logger::warning('Error calculando tasa de completaciÃƒÆ’Ã‚Â³n de ruta', [
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            return 0.0;
        }
    }

    /**
     * Calcular mÃƒÆ’Ã‚Â©tricas de rendimiento general
     * 
     * @param array $filters Filtros aplicados
     * @return array MÃƒÆ’Ã‚Â©tricas de rendimiento
     */
    private function calculatePerformanceMetrics(array $filters = []): array
    {
        try {
            $whereConditions = ['1=1'];
            $params = [];

            if (!empty($filters['date_from'])) {
                $whereConditions[] = 'timestamp >= :date_from';
                $params[':date_from'] = $filters['date_from'];
            }

            if (!empty($filters['date_to'])) {
                $whereConditions[] = 'timestamp <= :date_to';
                $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
            }

            $whereClause = implode(' AND ', $whereConditions);

            $query = "
                SELECT 
                    COUNT(*) as total_events,
                    COUNT(DISTINCT session_id) as unique_sessions,
                    COUNT(DISTINCT candidate_id) as unique_users,
                    COUNT(DISTINCT DATE(timestamp)) as active_days,
                    AVG(
                        CASE WHEN action_type = 'node_visit' 
                        AND JSON_EXTRACT(data, '$.response_time') IS NOT NULL
                        THEN JSON_EXTRACT(data, '$.response_time')
                        END
                    ) as avg_response_time,
                    COUNT(CASE WHEN action_type = 'error_occurred' THEN 1 END) as total_errors,
                    ROUND(
                        COUNT(CASE WHEN action_type = 'error_occurred' THEN 1 END) * 100.0 / COUNT(*), 
                        4
                    ) as error_rate
                FROM {$this->table}
                WHERE $whereClause
            ";

            $stmt = $this->db->prepare($query);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $metrics = $stmt->fetch(PDO::FETCH_ASSOC);

            // AÃƒÆ’Ã‚Â±adir mÃƒÆ’Ã‚Â©tricas calculadas
            $metrics['events_per_session'] = $metrics['unique_sessions'] > 0
                ? round($metrics['total_events'] / $metrics['unique_sessions'], 2)
                : 0;

            $metrics['sessions_per_user'] = $metrics['unique_users'] > 0
                ? round($metrics['unique_sessions'] / $metrics['unique_users'], 2)
                : 0;

            $metrics['events_per_day'] = $metrics['active_days'] > 0
                ? round($metrics['total_events'] / $metrics['active_days'], 2)
                : 0;

            return $metrics;
        } catch (Exception $e) {
            Logger::warning('Error calculando mÃƒÆ’Ã‚Â©tricas de rendimiento', [
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);

            return [
                'total_events' => 0,
                'unique_sessions' => 0,
                'unique_users' => 0,
                'error_rate' => 0,
                'avg_response_time' => 0
            ];
        }
    }


    // ==========================================
    // MÃƒÆ’Ã¢â‚¬Â°TODOS CRUD ENCAPSULADOS ESTÃƒÆ’Ã‚ÂNDAR
    // ==========================================

    /**
     * Crear nuevo chatbot_analytics con validaciones
     * @param array $data Datos del nuevo chatbot_analytics
     * @return mixed ID del nuevo chatbot_analytics o false en caso de error
     */
    public function createChatbotAnalytics(array $data): mixed
    {
        try {
            $this->validateChatbotAnalyticsData($data);
            $id = $this->store($data);
            $this->invalidateChatbotAnalyticsCache();

            Logger::info('ChatbotAnalytics created successfully', [
                'model' => static::class,
                'id' => $id
            ]);

            return $id;
        } catch (\Exception $e) {
            Logger::error('Error creating chatbot_analytics', [
                'model' => static::class,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtener chatbot_analytics por ID
     * @param mixed $id ID del chatbot_analytics
     * @return array|null Datos del chatbot_analytics o null si no existe
     */
    public function getChatbotAnalytics($id): ?array
    {
        try {
            return $this->findById($id);
        } catch (\Exception $e) {
            Logger::error('Error retrieving chatbot_analytics', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Actualizar chatbot_analytics con validaciones
     * @param mixed $id ID del chatbot_analytics a actualizar
     * @param array $data Nuevos datos
     * @return bool True si la actualizaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function updateChatbotAnalytics($id, array $data): bool
    {
        try {
            $this->validateChatbotAnalyticsData($data, $id);
            $result = $this->update($id, $data);

            if ($result) {
                $this->invalidateChatbotAnalyticsCache();
                Logger::info('ChatbotAnalytics updated successfully', [
                    'model' => static::class,
                    'id' => $id,
                    'fields' => array_keys($data)
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating chatbot_analytics', [
                'model' => static::class,
                'id' => $id,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Eliminar chatbot_analytics con validaciones
     * @param mixed $id ID del chatbot_analytics a eliminar
     * @return bool True si la eliminaciÃƒÆ’Ã‚Â³n fue exitosa
     */
    public function deleteChatbotAnalytics($id): bool
    {
        try {
            $result = $this->delete($id);

            if ($result) {
                $this->invalidateChatbotAnalyticsCache();
                Logger::info('ChatbotAnalytics deleted successfully', [
                    'model' => static::class,
                    'id' => $id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error deleting chatbot_analytics', [
                'model' => static::class,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Buscar chatbot_analytics con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @param int $page PÃƒÆ’Ã‚Â¡gina actual
     * @param int $limit Registros por pÃƒÆ’Ã‚Â¡gina
     * @param array $orderBy Criterios de ordenamiento
     * @return array Array de chatbot_analytics
     */
    public function searchChatbotAnalyticss(array $filters = [], int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = []): array
    {
        try {
            return $this->findAll($filters, $page, $limit, $orderBy);
        } catch (\Exception $e) {
            Logger::error('Error searching chatbot_analytics', [
                'model' => static::class,
                'filters' => $filters,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Contar total de chatbot_analytics con filtros
     * @param array $filters Filtros de bÃƒÆ’Ã‚Âºsqueda
     * @return int NÃƒÆ’Ã‚Âºmero total de chatbot_analytics
     */
    public function countChatbotAnalyticss(array $filters = []): int
    {
        try {
            return $this->countAll($filters);
        } catch (\Exception $e) {
            Logger::error('Error counting chatbot_analytics', [
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
     * Validar datos especÃƒÆ’Ã‚Â­ficos de chatbot_analytics
     * @param array $data Datos a validar
     * @param mixed $id ID para validaciones de actualizaciÃƒÆ’Ã‚Â³n (opcional)
     * @throws \InvalidArgumentException Si los datos no son vÃƒÆ’Ã‚Â¡lidos
     */
    private function validateChatbotAnalyticsData(array $data, $id = null): void
    {
        // TODO: Implementar validaciones especÃƒÆ’Ã‚Â­ficas del modelo
    }

    /**
     * Invalidar cache especÃƒÆ’Ã‚Â­fico de chatbot_analytics
     */
    public function invalidateChatbotAnalyticsCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['chatbot_analytics', 'chatbot_analytics_core', 'chatbot_analytics_list']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating chatbot_analytics cache', [], $e);
            return 0;
        }
    }
}
