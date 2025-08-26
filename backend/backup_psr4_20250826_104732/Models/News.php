<?php declare(strict_types=1);

namespace Models\News.php\Models;

use Utils\Logger;

/**
 * Modelo para las noticias publicadas en la plataforma
 * 
 * Proporciona funcionalidades completas para la gestiÃ³n de contenido de noticias
 * incluyendo publicaciÃ³n, categorizaciÃ³n, bÃºsqueda y cache optimizado.
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
     * ðŸ”§ CORRECCIÃ“N AUTOMÃTICA APLICADA
     * Modelo: News
     * Fecha: 2025-08-23
     * 
     * Cambios realizados:
     * âž• Campos aÃ±adidos: ninguno
     * âŒ Campos removidos: ['category', 'tags', 'published_at', 'featured_image', 'excerpt']
     * ðŸ“Š Total campos fillable: 4
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

    // Estados vÃ¡lidos para noticias
    private const VALID_STATUSES = ['draft', 'published', 'archived', 'scheduled'];

    // Cache TTL para noticias pÃºblicas (5 minutos)
    private const CACHE_TTL = 300;

    /**
     * Obtener solo noticias publicadas con paginaciÃ³n
     * 
     * @param int $page PÃ¡gina a obtener
     * @param int $limit NÃºmero de registros por pÃ¡gina
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

            // AÃ±adir filtro de fecha para noticias ya publicadas
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

            // PaginaciÃ³n
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
     * Obtener noticias por categorÃ­a
     * 
     * @param string $category CategorÃ­a de las noticias
     * @param int $page PÃ¡gina a obtener
     * @param int $limit NÃºmero de registros por pÃ¡gina
     * @param bool $publishedOnly Si solo incluir noticias publicadas
     * @return array Lista de noticias de la categorÃ­a
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

            // Ordenar por fecha de publicaciÃ³n descendente
            $sql .= " ORDER BY `published_at` DESC";

            // PaginaciÃ³n
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
     * @param int $limit NÃºmero mÃ¡ximo de noticias destacadas
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
     * MÃ©todo auxiliar para ejecutar la query de noticias destacadas
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
     * Buscar noticias por tÃ­tulo o contenido
     * 
     * @param string $searchTerm TÃ©rmino de bÃºsqueda
     * @param int $page PÃ¡gina a obtener
     * @param int $limit NÃºmero de registros por pÃ¡gina
     * @param bool $publishedOnly Si solo incluir noticias publicadas
     * @return array Lista de noticias que coinciden con la bÃºsqueda
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

            // Ordenar por relevancia (tÃ­tulo primero, luego por fecha)
            $sql .= " ORDER BY 
                      CASE WHEN `title` LIKE :search_title THEN 1 ELSE 2 END,
                      `published_at` DESC";

            // PaginaciÃ³n
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
     * Obtener noticias recientes con paginaciÃ³n
     * 
     * @param int $days NÃºmero de dÃ­as hacia atrÃ¡s para considerar "reciente"
     * @param int $page PÃ¡gina a obtener
     * @param int $limit NÃºmero de registros por pÃ¡gina
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
            // Calcular fecha lÃ­mite
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

            // Obtener conteo total para paginaciÃ³n
            $countSql = str_replace('SELECT * FROM', 'SELECT COUNT(*) as total FROM', explode(' ORDER BY', $sql)[0]);
            $countResult = $this->query($countSql, array_filter($params, fn($key) => $key !== ':limit' && $key !== ':offset', ARRAY_FILTER_USE_KEY));
            $total = (int)($countResult[0]['total'] ?? 0);

            // Agregar paginaciÃ³n a la query principal
            $offset = ($page - 1) * $limit;
            $sql .= ' LIMIT :limit OFFSET :offset';
            $params[':limit'] = $limit;
            $params[':offset'] = $offset;

            $results = $this->query($sql, $params);

            // Calcular metadata de paginaciÃ³n
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
     * @return bool True si el estado es vÃ¡lido
     */
    public function validateStatus(string $status): bool
    {
        return in_array($status, self::VALID_STATUSES);
    }

    /**
     * Validar fecha de publicaciÃ³n
     * 
     * @param string $publishedAt Fecha de publicaciÃ³n
     * @return bool True si la fecha es vÃ¡lida
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

        // Validar fecha de publicaciÃ³n si estÃ¡ presente
        if (!empty($data['published_at']) && !$this->validatePublishedDate($data['published_at'])) {
            throw new \InvalidArgumentException("Invalid published date format");
        }

        // Si no hay fecha de publicaciÃ³n y el estado es 'published', usar fecha actual
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = date('Y-m-d H:i:s');
        }

        // Generar excerpt automÃ¡tico si no existe
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
     * @return bool True si la actualizaciÃ³n fue exitosa
     */
    public function updateNews($id, array $data): bool
    {
        if (empty($id)) {
            throw new \InvalidArgumentException('News ID cannot be empty');
        }

        if (empty($data)) {
            throw new \InvalidArgumentException('Data cannot be empty');
        }

        // Validar estado si estÃ¡ presente
        if (isset($data['status']) && !$this->validateStatus($data['status'])) {
            throw new \InvalidArgumentException("Invalid status: {$data['status']}");
        }

        // Validar fecha de publicaciÃ³n si estÃ¡ presente
        if (isset($data['published_at']) && !empty($data['published_at']) && !$this->validatePublishedDate($data['published_at'])) {
            throw new \InvalidArgumentException("Invalid published date format");
        }

        // Regenerar excerpt si el contenido cambiÃ³
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
     * @param string|null $publishedAt Fecha de publicaciÃ³n (opcional, usa fecha actual si es null)
     * @return bool True si la publicaciÃ³n fue exitosa
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
     * Generar excerpt automÃ¡tico desde el contenido
     * 
     * @param string $content Contenido completo
     * @param int $maxLength Longitud mÃ¡xima del excerpt
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

        // Truncar en la Ãºltima palabra completa
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
     * @return int NÃºmero de elementos de cache eliminados
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
     * Obtener estadÃ­sticas de noticias por estado
     * 
     * @return array EstadÃ­sticas por estado
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
     * Obtener categorÃ­as de noticias con conteos
     * 
     * @param bool $publishedOnly Si solo contar noticias publicadas
     * @return array Lista de categorÃ­as con conteos
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
     * Generar clave de cache consistente para noticias (usa mÃ©todo de BaseModel)
     * 
     * @param string $prefix Prefijo de la clave
     * @param array $params ParÃ¡metros para incluir en la clave
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
    // CRUD METHODS ESTÃNDAR - BaseModel Template v2.0.0
    // =====================================================

    /**
     * Crear nueva noticia con validaciones CRUD estÃ¡ndar
     *
     * @param array $data Datos de la noticia
     * @return int|false ID de la nueva noticia o false en caso de error
     * @throws InvalidArgumentException Si los datos no son vÃ¡lidos
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
     * Actualizar noticia con validaciones CRUD estÃ¡ndar
     *
     * @param int $id ID de la noticia
     * @param array $data Nuevos datos
     * @return bool
     * @throws InvalidArgumentException Si los datos no son vÃ¡lidos
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
     * Buscar noticias CRUD estÃ¡ndar
     *
     * @param array $criteria Criterios de bÃºsqueda
     * @param int $limit LÃ­mite de resultados
     * @param int $offset Offset para paginaciÃ³n
     * @return array
     */
    public static function searchNewsStandard(array $criteria = [], int $limit = 50, int $offset = 0): array
    {
        try {
            $query = "SELECT * FROM news WHERE 1=1";
            $params = [];

            // Filtro por tÃ­tulo
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
            self::logError('Error en bÃºsqueda de noticias', $criteria, $e);
            return [];
        }
    }

    /**
     * Contar noticias
     *
     * @param array $criteria Criterios de bÃºsqueda
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
     * @param bool $isUpdate Si es una actualizaciÃ³n (permite campos opcionales)
     * @throws InvalidArgumentException Si los datos no son vÃ¡lidos
     */
    private static function validateNewsData(array $data, bool $isUpdate = false): void
    {
        // title es requerido en creaciÃ³n
        if (!$isUpdate && empty($data['title'])) {
            throw new \InvalidArgumentException('El tÃ­tulo es requerido');
        }

        if (isset($data['title'])) {
            if (!is_string($data['title']) || strlen(trim($data['title'])) < 3) {
                throw new \InvalidArgumentException('El tÃ­tulo debe tener al menos 3 caracteres');
            }
            if (strlen($data['title']) > 255) {
                throw new \InvalidArgumentException('El tÃ­tulo no puede exceder los 255 caracteres');
            }
        }

        // content es requerido en creaciÃ³n
        if (!$isUpdate && empty($data['content'])) {
            throw new \InvalidArgumentException('El contenido es requerido');
        }

        if (isset($data['content'])) {
            if (!is_string($data['content']) || strlen(trim($data['content'])) < 10) {
                throw new \InvalidArgumentException('El contenido debe tener al menos 10 caracteres');
            }
        }

        // author_id es requerido en creaciÃ³n
        if (!$isUpdate && empty($data['author_id'])) {
            throw new \InvalidArgumentException('El author_id es requerido');
        }

        if (isset($data['author_id']) && (!is_numeric($data['author_id']) || $data['author_id'] <= 0)) {
            throw new \InvalidArgumentException('El author_id debe ser un nÃºmero entero positivo');
        }

        // status es requerido en creaciÃ³n
        if (!$isUpdate && empty($data['status'])) {
            throw new \InvalidArgumentException('El status es requerido');
        }

        if (isset($data['status'])) {
            if (!in_array($data['status'], self::VALID_STATUSES)) {
                throw new \InvalidArgumentException('Status no vÃ¡lido: ' . $data['status']);
            }
        }
    }

    /**
     * Invalidar cachÃ© relacionado con noticias (versiÃ³n estÃ¡tica)
     */
    private static function invalidateNewsCacheStandard(): void
    {
        try {
            // Crear instancia temporal para acceder a mÃ©todos de instancia
            $tempInstance = new self();
            $deletedCount = $tempInstance->invalidateNewsCache();

            self::logDebug('CachÃ© de noticias invalidado', ['deleted_count' => $deletedCount]);
        } catch (\Exception $e) {
            self::logError('Error al invalidar cachÃ© de noticias', [], $e);
        }
    }
}
