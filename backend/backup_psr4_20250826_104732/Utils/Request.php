<?php declare(strict_types=1);

namespace Utils\Request.php\Utils;

class Request
{
    private array $query;
    private array $body;
    public  array $files;
    private array $headers;
    private array $cookies;
    private string $method;
    private string $uri;
    private ?array $user = null;

    public function __construct(
        array $query = [],
        array $body = [],
        array $files = [],
        array $headers = [],
        array $cookies = [],
        string $method = 'GET',
        string $uri = '/'
    ) {
        $this->query   = $query;
        $this->body    = $body;
        $this->files   = $files;
        $this->headers = $headers;
        $this->cookies = $cookies;
        $this->method  = $method;
        $this->uri     = $uri;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
    public function getUri(): string
    {
        return $this->uri;
    }

    public function getQuery(?string $key = null, $default = null)
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function getBody(): array
    {
        return $this->body;
    }

    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->body))  return $this->body[$key];
        if (array_key_exists($key, $this->query)) return $this->query[$key];
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function header(string $name, $default = null)
    {
        $name = strtolower($name);
        foreach ($this->headers as $k => $v) {
            if (strtolower($k) === $name) return $v;
        }
        return $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('Authorization');
        if ($auth && stripos($auth, 'Bearer ') === 0) {
            return trim(substr($auth, 7));
        }
        return null;
    }

    /**
     * Establecer la informaciÃ³n del usuario autenticado
     */
    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    /**
     * Obtener la informaciÃ³n del usuario autenticado
     */
    public function getUser(): ?array
    {
        return $this->user;
    }

    /**
     * MÃ©todo estÃ¡tico para obtener datos JSON del cuerpo de la peticiÃ³n
     */
    public static function json(): array
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return [];
        }

        $decoded = json_decode($input, true);
        return is_array($decoded) ? $decoded : [];
    }
}
