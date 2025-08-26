<?php

declare(strict_types=1);

namespace Models;

use Utils\Logger;

/**
 * Modelo para las noticias publicadas en la plataforma
 * 
 * Proporciona funcionalidades completas para la gestión de contenido de noticias
 * incluyendo publicación, categorización, búsqueda y cache optimizado.
 * 
 * @package Models
 * @author Bubble of Talents Development Team
 * @version 2.0.0
 * @since 2025-08-23
 */
class News extends BaseModel
{
    protected string $table = 'news';
    /*
     * 🔧 CORRECCIÓN AUTOMÁTICA APLICADA
     * Modelo: News
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * ➕ Campos añadidos: ninguno
     * ❌ Campos removidos: ['category', 'tags', 'published_at', 'featured_image', 'excerpt']
     * 📊 Total campos fillable: 4
     * 
     * Los campos fillable ahora coinciden exactamente con las columnas
     * disponibles en la tabla de base de datos (excluyendo id, created_at, updated_at).
     */


    protected array $fillable = [
        'title',
        'content',
        'author_id',
        'status',
    ];

    protected array $hidden = [
        'internal_notes',
        'draft_content'
    ];

    // Estados válidos para noticias
    private const VALID_STATUSES = ['draft', 'published', 'archived', 'scheduled'];

    // Cache TTL para noticias públicas (5 minutos)
    private const CACHE_TTL = 300;

    /**
     * Obtener solo noticias publicadas con paginación
     * 
     * @param int $page Página a obtener
     * @param int $limit Número de registros por página
     * @param array $orderBy Ordenamiento ['field' => 'direction']
     * @return array Lista de noticias publicadas
     */
    public function getPublishedNews(int $page = 1, int $limit = self::DEFAULT_LIMIT, array $orderBy = ['published_at' => 'DESC']): array
    {
        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        try {
            $filters = [
                'status' => 'published'
            ];

            // Añadir filtro de fecha para noticias ya publicadas
            $currentTime = date('Y-m-d H:i:s');
            $customWhere = "published_at <= :current_time";

            $sql = "SELECT * FROM `{$this->table}` WHERE `status` = :status AND `published_at` <= :current_time";
            $params = [
                ':status' => 'published',
                ':current_time' => $currentTime
            ];

            // Ordenamiento
            if (!empty($orderBy)) {
                $orderClauses = [];
                foreach ($orderBy as $field => $direction) {
                    if ($this->isValidFieldName($field) && in_array(strtoupper($direction), ['ASC', 'DESC'])) {
                        $orderClauses[] = "`$field` " . strtoupper($direction);
                    }
                }
                if (!empty($orderClauses)) {
                    $sql .= ' ORDER BY ' . implode(', ', $orderClauses);
                }
            }

            // Paginación
            $offset = ($page - 1) * $limit;
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $results = $this->query($sql, $params);

            $this->logDebug('Published news retrieved successfully', [
                'count' => count($results),
                'page' => $page,
                'limit' => $limit
            ]);

            return $this->hideFields($results);
        } catch (\Exception $e) {
            $this->logError('Error getting published news', [
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get published news: ' . $e->getMessage());
        }
    }

    /**
     * Obtener noticias por categoría
     * 
     * @param string $category Categoría de las noticias
     * @param int $page Página a obtener
     * @param int $limit Número de registros por página
     * @param bool $publishedOnly Si solo incluir noticias publicadas
     * @return array Lista de noticias de la categoría
     */
    public function getNewsByCategory(string $category, int $page = 1, int $limit = self::DEFAULT_LIMIT, bool $publishedOnly = true): array
    {
        if (empty($category)) {
            throw new \InvalidArgumentException('Category cannot be empty');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        try {
            $sql = "SELECT * FROM `{$this->table}` WHERE `category` = :category";
            $params = [':category' => $category];

            if ($publishedOnly) {
                $currentTime = date('Y-m-d H:i:s');
                $sql .= " AND `status` = :status AND `published_at` <= :current_time";
                $params[':status'] = 'published';
                $params[':current_time'] = $currentTime;
            }

            // Ordenar por fecha de publicación descendente
            $sql .= " ORDER BY `published_at` DESC";

            // Paginación
            $offset = ($page - 1) * $limit;
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $results = $this->query($sql, $params);

            $this->logDebug('News by category retrieved successfully', [
                'category' => $category,
                'count' => count($results),
                'published_only' => $publishedOnly
            ]);

            return $this->hideFields($results);
        } catch (\Exception $e) {
            $this->logError('Error getting news by category', [
                'category' => $category,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get news by category: ' . $e->getMessage());
        }
    }

    /**
     * Obtener noticias destacadas (featured)
     * 
     * @param int $limit Número máximo de noticias destacadas
     * @param bool $publishedOnly Si solo incluir noticias publicadas
     * @return array Lista de noticias destacadas
     */
    public function getFeaturedNews(int $limit = 5, bool $publishedOnly = true): array
    {
        if ($limit < 1 || $limit > 50) {
            throw new \InvalidArgumentException('Limit must be between 1 and 50');
        }

        // Generar clave de cache
        $cacheKey = $this->buildNewsCacheKey('featured_news', [
            'limit' => $limit,
            'published_only' => $publishedOnly
        ]);

        try {
            // Intentar obtener desde cache
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::get($cacheKey, self::CACHE_TTL, function () use ($limit, $publishedOnly) {
                    return $this->executeFeaturedNewsQuery($limit, $publishedOnly);
                });
            }

            // Fallback sin cache
            return $this->executeFeaturedNewsQuery($limit, $publishedOnly);
        } catch (\Exception $e) {
            $this->logError('Error getting featured news', [
                'limit' => $limit,
                'published_only' => $publishedOnly
            ], $e);
            // Fallback directo
            return $this->executeFeaturedNewsQuery($limit, $publishedOnly);
        }
    }

    /**
     * Método auxiliar para ejecutar la query de noticias destacadas
     */
    private function executeFeaturedNewsQuery(int $limit, bool $publishedOnly): array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `featured_image` IS NOT NULL AND `featured_image` != ''";
        $params = [];

        if ($publishedOnly) {
            $currentTime = date('Y-m-d H:i:s');
            $sql .= " AND `status` = :status AND `published_at` <= :current_time";
            $params[':status'] = 'published';
            $params[':current_time'] = $currentTime;
        }

        $sql .= " ORDER BY `published_at` DESC LIMIT :limit";
        $params[':limit'] = $limit;

        $results = $this->query($sql, $params);
        return $this->hideFields($results);
    }

    /**
     * Buscar noticias por título o contenido
     * 
     * @param string $searchTerm Término de búsqueda
     * @param int $page Página a obtener
     * @param int $limit Número de registros por página
     * @param bool $publishedOnly Si solo incluir noticias publicadas
     * @return array Lista de noticias que coinciden con la búsqueda
     */
    public function searchNews(string $searchTerm, int $page = 1, int $limit = self::DEFAULT_LIMIT, bool $publishedOnly = true): array
    {
        if (empty($searchTerm)) {
            throw new \InvalidArgumentException('Search term cannot be empty');
        }

        if (strlen($searchTerm) < 3) {
            throw new \InvalidArgumentException('Search term must be at least 3 characters long');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        try {
            $searchWildcard = "%{$searchTerm}%";

            $sql = "SELECT * FROM `{$this->table}` WHERE 
                    (`title` LIKE :search_title OR `content` LIKE :search_content OR `excerpt` LIKE :search_excerpt)";

            $params = [
                ':search_title' => $searchWildcard,
                ':search_content' => $searchWildcard,
                ':search_excerpt' => $searchWildcard
            ];

            if ($publishedOnly) {
                $currentTime = date('Y-m-d H:i:s');
                $sql .= " AND `status` = :status AND `published_at` <= :current_time";
                $params[':status'] = 'published';
                $params[':current_time'] = $currentTime;
            }

            // Ordenar por relevancia (título primero, luego por fecha)
            $sql .= " ORDER BY 
                      CASE WHEN `title` LIKE :search_title THEN 1 ELSE 2 END,
                      `published_at` DESC";

            // Paginación
            $offset = ($page - 1) * $limit;
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $results = $this->query($sql, $params);

            $this->logDebug('News search completed successfully', [
                'search_term' => $searchTerm,
                'count' => count($results),
                'page' => $page,
                'limit' => $limit
            ]);

            return $this->hideFields($results);
        } catch (\Exception $e) {
            $this->logError('Error searching news', [
                'search_term' => $searchTerm,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to search news: ' . $e->getMessage());
        }
    }

    /**
     * Obtener noticias recientes con paginación
     * 
     * @param int $days Número de días hacia atrás para considerar "reciente"
     * @param int $page Página a obtener
     * @param int $limit Número de registros por página
     * @param bool $publishedOnly Si solo incluir noticias publicadas
     * @return array Array con datos paginados y metadata
     */
    public function getRecentNews(int $days = 30, int $page = 1, int $limit = self::DEFAULT_LIMIT, bool $publishedOnly = true): array
    {
        if ($days < 1 || $days > 365) {
            throw new \InvalidArgumentException('Days must be between 1 and 365');
        }

        if ($page < 1) {
            throw new \InvalidArgumentException('Page must be 1 or greater');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException('Limit must be between 1 and ' . self::MAX_LIMIT);
        }

        try {
            // Calcular fecha límite
            $fromDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
            $currentTime = date('Y-m-d H:i:s');

            // Query para datos
            $sql = "SELECT * FROM `{$this->table}` WHERE `published_at` >= :from_date";
            $params = [':from_date' => $fromDate];

            if ($publishedOnly) {
                $sql .= " AND `status` = :status AND `published_at` <= :current_time";
                $params[':status'] = 'published';
                $params[':current_time'] = $currentTime;
            }

            $sql .= " ORDER BY `published_at` DESC";

            // Obtener conteo total para paginación
            $countSql = str_replace('SELECT * FROM', 'SELECT COUNT(*) as total FROM', explode(' ORDER BY', $sql)[0]);
            $countResult = $this->query($countSql, array_filter($params, fn($key) => $key !== ':limit' && $key !== ':offset', ARRAY_FILTER_USE_KEY));
            $total = (int)($countResult[0]['total'] ?? 0);

            // Agregar paginación a la query principal
            $offset = ($page - 1) * $limit;
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $results = $this->query($sql, $params);

            // Calcular metadata de paginación
            $totalPages = (int) ceil($total / $limit);

            $response = [
                'data' => $this->hideFields($results),
                'pagination' => [
                    'current_page' => $page,
                    'total' => $total,
                    'per_page' => $limit,
                    'total_pages' => $totalPages,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ],
                'meta' => [
                    'days_range' => $days,
                    'from_date' => $fromDate,
                    'published_only' => $publishedOnly
                ]
            ];

            $this->logDebug('Recent news retrieved successfully', [
                'days' => $days,
                'count' => count($results),
                'total' => $total,
                'page' => $page
            ]);

            return $response;
        } catch (\Exception $e) {
            $this->logError('Error getting recent news', [
                'days' => $days,
                'page' => $page,
                'limit' => $limit
            ], $e);
            throw new \RuntimeException('Failed to get recent news: ' . $e->getMessage());
        }
    }

    /**
     * Validar estado de noticia antes de guardar
     * 
     * @param string $status Estado a validar
     * @return bool True si el estado es válido
     */
    public function validateStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES);
    }

    /**
     * Validar fecha de publicación
     * 
     * @param string $publishedAt Fecha de publicación
     * @return bool True si la fecha es válida
     */
    public function validatePublishedDate(string $publishedAt): bool
    {
        $date = \DateTime::createFromFormat('Y-m-d H:i:s', $publishedAt);
        return $date && $date->format('Y-m-d H:i:s') === $publishedAt;
    }

    /**
     * Crear nueva noticia con validaciones
     * 
     * @param array $data Datos de la noticia
     * @return mixed ID de la noticia creada
     */
    public function createNews(array $data)
    {
        // Validar datos requeridos
        $requiredFields = ['title', 'content', 'author_id', 'status'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' is required");
            }
        }

        // Validar estado
        if (!$this->validateStatus($data['status'])) {
            throw new \InvalidArgumentException("Invalid status: {$data['status']}");
        }

        // Validar fecha de publicación si está presente
        if (!empty($data['published_at']) && !$this->validatePublishedDate($data['published_at'])) {
            throw new \InvalidArgumentException("Invalid published date format");
        }

        // Si no hay fecha de publicación y el estado es 'published', usar fecha actual
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        // Generar excerpt automático si no existe
        if (empty($data['excerpt']) && !empty($data['content'])) {
            $data['excerpt'] = $this->generateExcerpt($data['content']);
        }

        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        try {
            $newsId = $this->store($filtered);

            // Invalidar cache de noticias
            $this->invalidateNewsCache();

            Logger::info('News created successfully', [
                'news_id' => $newsId,
                'title' => $filtered['title'] ?? null,
                'status' => $filtered['status'] ?? null
            ]);

            return $newsId;
        } catch (\Exception $e) {
            $this->logError('Error creating news', ['data' => $data], $e);
            throw new \RuntimeException('Failed to create news: ' . $e->getMessage());
        }
    }

    /**
     * Actualizar noticia con validaciones
     * 
     * @param mixed $id ID de la noticia
     * @param array $data Datos a actualizar
     * @return bool True si la actualización fue exitosa
     */
    public function updateNews($id, array $data): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('News ID cannot be empty');
        }

        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        // Validar estado si está presente
        if (isset($data['status']) && !$this->validateStatus($data['status'])) {
            throw new \InvalidArgumentException("Invalid status: {$data['status']}");
        }

        // Validar fecha de publicación si está presente
        if (isset($data['published_at']) && !empty($data['published_at']) && !$this->validatePublishedDate($data['published_at'])) {
            throw new \InvalidArgumentException("Invalid published date format");
        }

        // Regenerar excerpt si el contenido cambió
        if (isset($data['content']) && !isset($data['excerpt'])) {
            $data['excerpt'] = $this->generateExcerpt($data['content']);
        }

        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($this->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }

        try {
            $result = $this->update($id, $filtered);

            // Invalidar cache de noticias
            $this->invalidateNewsCache();

            Logger::info('News updated successfully', [
                'news_id' => $id,
                'updated_fields' => array_keys($filtered)
            ]);

            return $result;
        } catch (\Exception $e) {
            $this->logError('Error updating news', [
                'news_id' => $id,
                'data' => $data
            ], $e);
            throw new \RuntimeException('Failed to update news: ' . $e->getMessage());
        }
    }

    /**
     * Publicar noticia programada
     * 
     * @param mixed $id ID de la noticia
     * @param string|null $publishedAt Fecha de publicación (opcional, usa fecha actual si es null)
     * @return bool True si la publicación fue exitosa
     */
    public function publishNews($id, string $publishedAt = null): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('News ID cannot be empty');
        }

        $publishDate = $publishedAt ?? date('Y-m-d H:i:s');

        if (!$this->validatePublishedDate($publishDate)) {
            throw new \InvalidArgumentException("Invalid published date format");
        }

        $data = [
            'status' => 'published',
            'published_at' => $publishDate
        ];

        return $this->updateNews($id, $data);
    }

    /**
     * Archivar noticia
     * 
     * @param mixed $id ID de la noticia
     * @return bool True si el archivado fue exitoso
     */
    public function archiveNews($id): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('News ID cannot be empty');
        }

        $data = ['status' => 'archived'];
        return $this->updateNews($id, $data);
    }

    /**
     * Generar excerpt automático desde el contenido
     * 
     * @param string $content Contenido completo
     * @param int $maxLength Longitud máxima del excerpt
     * @return string Excerpt generado
     */
    private function generateExcerpt(string $content, int $maxLength = 200): string
    {
        // Limpiar HTML y espacios extra
        $plainText = strip_tags($content);
        $plainText = preg_replace('/\s+/', ' ', trim($plainText));

        if (strlen($plainText) <= $maxLength) {
            return $plainText;
        }

        // Truncar en la última palabra completa
        $excerpt = substr($plainText, 0, $maxLength);
        $lastSpace = strrpos($excerpt, ' ');

        if ($lastSpace !== false) {
            $excerpt = substr($excerpt, 0, $lastSpace);
        }

        return $excerpt . '...';
    }

    /**
     * Invalidar cache de noticias
     * 
     * @return int Número de elementos de cache eliminados
     */
    public function invalidateNewsCache(): int
    {
        try {
            if (class_exists('\Utils\Cache')) {
                return \Utils\Cache::deleteByTags(['news', 'featured_news', 'recent_news']);
            }
            return 0;
        } catch (\Exception $e) {
            $this->logError('Error invalidating news cache', [], $e);
            return 0;
        }
    }

    /**
     * Obtener estadísticas de noticias por estado
     * 
     * @return array Estadísticas por estado
     */
    public function getNewsStats(): array
    {
        try {
            $sql = "SELECT 
                        `status`,
                        COUNT(*) as count,
                        MAX(`published_at`) as last_published
                    FROM `{$this->table}` 
                    GROUP BY `status`";

            $results = $this->query($sql, []);

            $stats = [];
            foreach ($results as $row) {
                $stats[$row['status']] = [
                    'count' => (int)$row['count'],
                    'last_published' => $row['last_published']
                ];
            }

            return $stats;
        } catch (\Exception $e) {
            $this->logError('Error getting news stats', [], $e);
            return [];
        }
    }

    /**
     * Obtener categorías de noticias con conteos
     * 
     * @param bool $publishedOnly Si solo contar noticias publicadas
     * @return array Lista de categorías con conteos
     */
    public function getCategoriesWithCount(bool $publishedOnly = true): array
    {
        try {
            $sql = "SELECT 
                        `category`,
                        COUNT(*) as count
                    FROM `{$this->table}` 
                    WHERE `category` IS NOT NULL AND `category` != ''";

            $params = [];

            if ($publishedOnly) {
                $currentTime = date('Y-m-d H:i:s');
                $sql .= " AND `status` = :status AND `published_at` <= :current_time";
                $params[':status'] = 'published';
                $params[':current_time'] = $currentTime;
            }

            $sql .= " GROUP BY `category` ORDER BY `count` DESC, `category` ASC";

            $results = $this->query($sql, $params);

            return array_map(function ($row) {
                return [
                    'category' => $row['category'],
                    'count' => (int)$row['count']
                ];
            }, $results);
        } catch (\Exception $e) {
            $this->logError('Error getting categories with count', ['published_only' => $publishedOnly], $e);
            return [];
        }
    }

    /**
     * Generar clave de cache consistente para noticias (usa método de BaseModel)
     * 
     * @param string $prefix Prefijo de la clave
     * @param array $params Parámetros para incluir en la clave
     * @return string Clave de cache generada
     */
    private function buildNewsCacheKey(string $prefix, array $params = []): string
    {
        $key = "news_{$prefix}";
        if (!empty($params)) {
            $key .= '_' . md5(serialize($params));
        }
        return $key;
    }

    // =====================================================
    // CRUD METHODS ESTÁNDAR - BaseModel Template v2.0.0
    // =====================================================

    /**
     * Crear nueva noticia con validaciones CRUD estándar
     *
     * @param array $data Datos de la noticia
     * @return int|false ID de la nueva noticia o false en caso de error
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function createNewsStandard(array $data)
    {
        self::validateNewsData($data);

        $news = new self();
        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($news->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $id = $news->store($filtered);

        if (!$id) {
            throw new \Exception('Error al crear la noticia');
        }

        self::invalidateNewsCacheStandard();

        return $id;
    }

    /**
     * Obtener noticia por ID
     *
     * @param int $id ID de la noticia
     * @return array|null
     */
    public static function getNewsStandard(int $id): ?array
    {
        try {
            $instance = new self();
            return $instance->findById($id);
        } catch (\Exception $e) {
            self::logError('Error al obtener noticia', ['id' => $id], $e);
            return null;
        }
    }

    /**
     * Actualizar noticia con validaciones CRUD estándar
     *
     * @param int $id ID de la noticia
     * @param array $data Nuevos datos
     * @return bool
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    public static function updateNewsStandard(int $id, array $data): bool
    {
        self::validateNewsData($data, true);

        $news = new self();
        $existingNews = $news->findById($id);
        if (!$existingNews) {
            throw new \InvalidArgumentException("Noticia con ID {$id} no encontrada");
        }

        // Filtrar solo los campos permitidos por $fillable
        $filtered = [];
        foreach ($news->fillable as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = is_string($data[$field]) ? trim($data[$field]) : $data[$field];
            }
        }
        $success = $news->update($id, $filtered);

        if ($success) {
            self::invalidateNewsCacheStandard();
        }

        return $success;
    }

    /**
     * Eliminar noticia
     *
     * @param int $id ID de la noticia
     * @return bool
     */
    public static function deleteNewsStandard(int $id): bool
    {
        try {
            $news = new self();
            $existingNews = $news->findById($id);
            if (!$existingNews) {
                return false;
            }

            $success = $news->delete($id);

            if ($success) {
                self::invalidateNewsCacheStandard();
            }

            return $success;
        } catch (\Exception $e) {
            self::logError('Error al eliminar noticia', ['id' => $id], $e);
            return false;
        }
    }

    /**
     * Buscar noticias CRUD estándar
     *
     * @param array $criteria Criterios de búsqueda
     * @param int $limit Límite de resultados
     * @param int $offset Offset para paginación
     * @return array
     */
    public static function searchNewsStandard(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "SELECT * FROM news WHERE 1=1";
            $params = [];

            // Filtro por título
            if (!empty($criteria['title'])) {
                $query .= " AND title LIKE :title";
                $params['title'] = '%' . $criteria['title'] . '%';
            }

            // Filtro por contenido
            if (!empty($criteria['content'])) {
                $query .= " AND content LIKE :content";
                $params['content'] = '%' . $criteria['content'] . '%';
            }

            // Filtro por author_id
            if (!empty($criteria['author_id'])) {
                $query .= " AND author_id = :author_id";
                $params['author_id'] = $criteria['author_id'];
            }

            // Filtro por status
            if (!empty($criteria['status'])) {
                $query .= " AND status = :status";
                $params['status'] = $criteria['status'];
            }

            $query .= " ORDER BY created_at DESC";
            $query .= " LIMIT :limit OFFSET :offset";
            $params['limit'] = $limit;
            $params['offset'] = $offset;

            $news = new self();
            return $news->query($query, $params);
        } catch (\Exception $e) {
            self::logError('Error en búsqueda de noticias', $criteria, $e);
            return [];
        }
    }

    /**
     * Contar noticias
     *
     * @param array $criteria Criterios de búsqueda
     * @return int
     */
    public static function countNewsStandard(array $criteria = []): int
    {
        try {
            $query = "SELECT COUNT(*) as total FROM news WHERE 1=1";
            $params = [];

            // Aplicar los mismos filtros que en searchNews
            if (!empty($criteria['title'])) {
                $query .= " AND title LIKE :title";
                $params['title'] = '%' . $criteria['title'] . '%';
            }

            if (!empty($criteria['content'])) {
                $query .= " AND content LIKE :content";
                $params['content'] = '%' . $criteria['content'] . '%';
            }

            if (!empty($criteria['author_id'])) {
                $query .= " AND author_id = :author_id";
                $params['author_id'] = $criteria['author_id'];
            }

            if (!empty($criteria['status'])) {
                $query .= " AND status = :status";
                $params['status'] = $criteria['status'];
            }

            $news = new self();
            $result = $news->query($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (\Exception $e) {
            self::logError('Error al contar noticias', $criteria, $e);
            return 0;
        }
    }

    /**
     * Validar datos de noticia
     *
     * @param array $data Datos a validar
     * @param bool $isUpdate Si es una actualización (permite campos opcionales)
     * @throws InvalidArgumentException Si los datos no son válidos
     */
    private static function validateNewsData(array $data, bool $isUpdate = false): void
    {
        // title es requerido en creación
        if (!$isUpdate && empty($data['title'])) {
            throw new \InvalidArgumentException('El título es requerido');
        }

        if (isset($data['title'])) {
            if (!is_string($data['title']) || strlen(trim($data['title'])) < 3) {
                throw new \InvalidArgumentException('El título debe tener al menos 3 caracteres');
            }
            if (strlen($data['title']) > 255) {
                throw new \InvalidArgumentException('El título no puede exceder los 255 caracteres');
            }
        }

        // content es requerido en creación
        if (!$isUpdate && empty($data['content'])) {
            throw new \InvalidArgumentException('El contenido es requerido');
        }

        if (isset($data['content'])) {
            if (!is_string($data['content']) || strlen(trim($data['content'])) < 10) {
                throw new \InvalidArgumentException('El contenido debe tener al menos 10 caracteres');
            }
        }

        // author_id es requerido en creación
        if (!$isUpdate && empty($data['author_id'])) {
            throw new \InvalidArgumentException('El author_id es requerido');
        }

        if (isset($data['author_id']) && (!is_numeric($data['author_id']) || $data['author_id'] <= 0)) {
            throw new \InvalidArgumentException('El author_id debe ser un número entero positivo');
        }

        // status es requerido en creación
        if (!$isUpdate && empty($data['status'])) {
            throw new \InvalidArgumentException('El status es requerido');
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], self::VALID_STATUSES)) {
                throw new \InvalidArgumentException('Status no válido: ' . $data['status']);
            }
        }
    }

    /**
     * Invalidar caché relacionado con noticias (versión estática)
     */
    private static function invalidateNewsCacheStandard(): void
    {
        try {
            // Crear instancia temporal para acceder a métodos de instancia
            $tempInstance = new self();
            $deletedCount = $tempInstance->invalidateNewsCache();

            self::logDebug('Caché de noticias invalidado', ['deleted_count' => $deletedCount]);
        } catch (\Exception $e) {
            self::logError('Error al invalidar caché de noticias', [], $e);
        }
    }
}
