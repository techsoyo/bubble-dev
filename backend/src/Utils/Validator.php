<?php

declare(strict_types=1);

namespace Utils;

class Validator
{
    private array $data = [];
    private array $errors = [];
    private array $rules = [];

    public function __construct(array $data = [], array $rules = [])
    {
        $this->data  = $data;
        $this->rules = $rules;
    }

    /* ==== Atalajes estí¡ticos ==== */

    public static function requireKeys(array $arr, array $keys): void
    {
        foreach ($keys as $k) {
            if (!array_key_exists($k, $arr)) {
                throw new \InvalidArgumentException("Campo requerido ausente: $k");
            }
        }
    }

    public static function string($v, int $min = 0, int $max = 255, string $field = 'string'): void
    {
        if (!is_string($v)) {
            throw new \InvalidArgumentException("$field debe ser string");
        }
        $len = mb_strlen($v);
        if ($len < $min) {
            throw new \InvalidArgumentException("$field: longitud mí­nima $min");
        }
        if ($len > $max) {
            throw new \InvalidArgumentException("$field: longitud mí¡xima $max");
        }
    }

    public static function optionalString($v, int $min = 0, int $max = 255, string $field = 'string'): void
    {
        if ($v === null || $v === '') {
            return;
        }
        self::string($v, $min, $max, $field);
    }

    public static function date($v, string $field = 'date'): void
    {
        if (!is_string($v) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            throw new \InvalidArgumentException("$field debe tener formato YYYY-MM-DD");
        }
        [$Y, $m, $d] = array_map('intval', explode('-', $v));
        if (!checkdate($m, $d, $Y)) {
            throw new \InvalidArgumentException("$field no es una fecha ví¡lida");
        }
    }

    /** @param string $v @param string[] $allowed */
    public static function enum($v, array $allowed, string $field = 'enum'): void
    {
        if (!is_string($v)) {
            throw new \InvalidArgumentException("$field debe ser string");
        }
        if (!in_array($v, $allowed, true)) {
            $opts = implode(',', $allowed);
            throw new \InvalidArgumentException("$field debe ser uno de: $opts");
        }
    }

    // NUEVO - Validación para INT
    public static function intId($v, string $field = 'id'): void
    {
        if (!is_numeric($v) || (int)$v <= 0) {
            throw new \InvalidArgumentException("$field debe ser un entero positivo");
        }
    }

    // MANTENER para compatibilidad temporal
    public static function uuidLike($v, string $field = 'id'): void
    {
        // Deprecado: será removido en versión futura
        if (!is_string($v) || $v === '' || strlen($v) != 36) {
            throw new \InvalidArgumentException("$field inválido (esperado tipo char(36))");
        }
    }
    public static function email($v, string $field = 'email'): void
    {
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("$field no es un email ví¡lido");
        }
    }

    public static function url($v, string $field = 'url'): void
    {
        if (!filter_var($v, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("$field no es una URL ví¡lida");
        }
    }

    /**
     * Reglas tipo pipe:
     *   ['campo' => 'required|string:1,100|enum:A1,A2,...']
     * Retorna: ['ok'=>bool, 'errors'=>array]
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];

        foreach ($rules as $field => $ruleStr) {
            $value   = $data[$field] ?? null;
            $ruleset = array_filter(explode('|', (string)$ruleStr));
            $required = in_array('required', $ruleset, true);

            if (($value === null || $value === '') && !$required) {
                continue;
            }
            if (($value === null || $value === '') && $required) {
                $errors[$field][] = 'Campo requerido';
                continue;
            }

            foreach ($ruleset as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                if (str_starts_with($rule, 'string')) {
                    $min = 0;
                    $max = 255;
                    if (preg_match('/^string:(\d+),(\d+)$/', $rule, $m)) {
                        $min = (int)$m[1];
                        $max = (int)$m[2];
                    }
                    try {
                        self::string($value, $min, $max, $field);
                    } catch (\Throwable $e) {
                        $errors[$field][] = $e->getMessage();
                    }
                    continue;
                }

                if ($rule === 'date') {
                    try {
                        self::date((string)$value, $field);
                    } catch (\Throwable $e) {
                        $errors[$field][] = $e->getMessage();
                    }
                    continue;
                }

                if ($rule === 'email') {
                    try {
                        self::email((string)$value, $field);
                    } catch (\Throwable $e) {
                        $errors[$field][] = $e->getMessage();
                    }
                    continue;
                }

                if ($rule === 'url') {
                    try {
                        self::url((string)$value, $field);
                    } catch (\Throwable $e) {
                        $errors[$field][] = $e->getMessage();
                    }
                    continue;
                }

                if ($rule === 'uuid') {
                    try {
                        self::uuidLike((string)$value, $field);
                    } catch (\Throwable $e) {
                        $errors[$field][] = $e->getMessage();
                    }
                    continue;
                }

                if ($rule === 'int') {
                    if (filter_var($value, FILTER_VALIDATE_INT) === false) {
                        $errors[$field][] = "$field debe ser entero";
                    }
                    continue;
                }

                if ($rule === 'numeric') {
                    if (!is_numeric($value)) {
                        $errors[$field][] = "$field debe ser numérico";
                    }
                    continue;
                }

                if ($rule === 'array') {
                    if (!is_array($value)) {
                        $errors[$field][] = "$field debe ser array";
                    }
                    continue;
                }

                if (str_starts_with($rule, 'enum:')) {
                    $allowed = explode(',', substr($rule, 5));
                    try {
                        self::enum((string)$value, $allowed, $field);
                    } catch (\Throwable $e) {
                        $errors[$field][] = $e->getMessage();
                    }
                    continue;
                }
            }
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    /* ==== API fluida por instancia ==== */

    public function setRules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function validateData(): bool
    {
        $this->errors = [];
        foreach ($this->rules as $field => $pipeRules) {
            $ruleList = array_filter(explode('|', (string)$pipeRules));
            foreach ($ruleList as $ruleItem) {
                $param = null;
                if (strpos($ruleItem, ':') !== false) {
                    [$ruleItem, $param] = explode(':', $ruleItem, 2);
                }
                $method = 'validate' . ucfirst($ruleItem);
                if (!method_exists($this, $method)) {
                    throw new \Exception("Regla no implementada: $ruleItem");
                }
                if (!$this->$method($field, $param)) {
                    break;
                }
            }
        }
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidData(): array
    {
        if (!empty($this->errors)) {
            return [];
        }
        $valid = [];
        foreach ($this->rules as $field => $_r) {
            if (array_key_exists($field, $this->data)) {
                $valid[$field] = $this->data[$field];
            }
        }
        return $valid;
    }

    public static function make(array $data): self
    {
        return new self($data);
    }
    public function rule(string $field, string $rules): self
    {
        $this->rules[$field] = $rules;
        return $this;
    }
    public function passes(): bool
    {
        return $this->validateData();
    }
    public function fails(): bool
    {
        return !$this->validateData();
    }
    public function first(?string $field = null): ?string
    {
        return $field ? ($this->errors[$field] ?? null) : (!empty($this->errors) ? (string)reset($this->errors) : null);
    }
    public function messages(): array
    {
        return array_values($this->errors);
    }

    /* ==== Reglas instancia ==== */

    private function validateRequired(string $field): bool
    {
        $ok = isset($this->data[$field]) && $this->data[$field] !== '';
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' es obligatorio";
        }
        return $ok;
    }

    private function validateEmail(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = filter_var($this->data[$field], FILTER_VALIDATE_EMAIL) !== false;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser un email ví¡lido";
        }
        return $ok;
    }

    private function validateMin(string $field, ?string $min): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $m = (int)($min ?? 0);
        $ok = mb_strlen((string)$this->data[$field]) >= $m;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe tener al menos $m caracteres";
        }
        return $ok;
    }

    private function validateMax(string $field, ?string $max): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $M = (int)($max ?? 255);
        $ok = mb_strlen((string)$this->data[$field]) <= $M;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe tener como mí¡ximo $M caracteres";
        }
        return $ok;
    }

    private function validateNumeric(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = is_numeric($this->data[$field]);
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser numérico";
        }
        return $ok;
    }

    private function validateDate(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = strtotime((string)$this->data[$field]) !== false;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser una fecha ví¡lida";
        }
        return $ok;
    }

    private function validateMatches(string $field, ?string $otherField): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = $otherField !== null && isset($this->data[$otherField]) && $this->data[$field] === $this->data[$otherField];
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe coincidir con '$otherField'";
        }
        return $ok;
    }

    private function validateUrl(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = filter_var($this->data[$field], FILTER_VALIDATE_URL) !== false;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser una URL ví¡lida";
        }
        return $ok;
    }

    private function validateInteger(string $field, ?string $range = null): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = filter_var($this->data[$field], FILTER_VALIDATE_INT) !== false;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser un entero ví¡lido";
            return false;
        }

        if ($range && strpos($range, ':') !== false) {
            [$min, $max] = explode(':', $range, 2);
            $v = (int)$this->data[$field];
            if ($v < (int)$min || $v > (int)$max) {
                $this->errors[$field] = "El campo '$field' debe estar entre $min y $max";
                return false;
            }
        }
        return true;
    }

    private function validateFloat(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = filter_var($this->data[$field], FILTER_VALIDATE_FLOAT) !== false;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser un decimal ví¡lido";
        }
        return $ok;
    }

    private function validateAlpha(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = ctype_alpha(str_replace(' ', '', (string)$this->data[$field]));
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe contener solo letras";
        }
        return $ok;
    }

    private function validateAlphanumeric(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = ctype_alnum(str_replace(' ', '', (string)$this->data[$field]));
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe contener solo letras y números";
        }
        return $ok;
    }

    private function validateIn(string $field, ?string $values): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $allowed = array_map('trim', explode(',', (string)$values));
        $ok = in_array($this->data[$field], $allowed, true);
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser uno de: " . implode(', ', $allowed);
        }
        return $ok;
    }

    private function validateNotIn(string $field, ?string $values): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $prohibited = array_map('trim', explode(',', (string)$values));
        $ok = !in_array($this->data[$field], $prohibited, true);
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' no puede ser uno de: " . implode(', ', $prohibited);
        }
        return $ok;
    }

    private function validateRegex(string $field, ?string $pattern): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        if ($pattern === null || $pattern === '') {
            return true;
        }
        $ok = (bool)preg_match($pattern, (string)$this->data[$field]);
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' no tiene el formato requerido";
        }
        return $ok;
    }

    private function validateUnique(string $field, ?string $table): bool
    {
        return true; // placeholder
    }

    private function validateJson(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        json_decode((string)$this->data[$field]);
        $ok = json_last_error() === JSON_ERROR_NONE;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe contener JSON ví¡lido";
        }
        return $ok;
    }

    private function validateIp(string $field, ?string $version = null): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $ok = filter_var($this->data[$field], FILTER_VALIDATE_IP) !== false;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser una IP ví¡lida" . ($version ? " ($version)" : '');
        }
        return $ok;
    }

    private function validateBetween(string $field, ?string $range): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        if (!$range || strpos($range, ',') === false) {
            return true;
        }
        [$min, $max] = explode(',', $range, 2);
        $v = is_numeric($this->data[$field]) ? (float)$this->data[$field] : mb_strlen((string)$this->data[$field]);
        $ok = $v >= (float)$min && $v <= (float)$max;
        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe estar entre $min y $max";
        }
        return $ok;
    }

    private function validateSanitized(string $field): bool
    {
        if (isset($this->data[$field]) && is_string($this->data[$field])) {
            $this->data[$field] = htmlspecialchars($this->data[$field], ENT_QUOTES, 'UTF-8');
        }
        return true;
    }

    private function validateNoSqlInjection(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $value = strtolower((string)$this->data[$field]);
        $sqlPatterns = [
            '/(\b(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER|EXEC|UNION)\b)/i',
            '/(\b(OR|AND)\s+\d+\s*=\s*\d+)/i',
            '/(\b(OR|AND)\s+[\'"]?\w+[\'"]?\s*=\s*[\'"]?\w+[\'"]?)/i',
            '/(--|\#|\/\*|\*\/)/i',
            '/(\bxp_cmdshell\b)/i'
        ];
        foreach ($sqlPatterns as $p) {
            if (preg_match($p, $value)) {
                $this->errors[$field] = "El campo '$field' contiene patrones no permitidos";
                return false;
            }
        }
        return true;
    }

    private function validateNoXss(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }
        $value = strtolower((string)$this->data[$field]);
        $xssPatterns = [
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe/i',
            '/<object/i',
            '/<embed/i',
            '/vbscript:/i'
        ];
        foreach ($xssPatterns as $p) {
            if (preg_match($p, $value)) {
                $this->errors[$field] = "El campo '$field' contiene contenido no permitido";
                return false;
            }
        }
        return true;
    }

    private function validateStrongPassword(string $field, ?string $level = 'medium'): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }

        $pwd = (string)$this->data[$field];
        $errors = [];
        $lvl = $level ?: 'medium';

        if ($lvl === 'basic') {
            if (strlen($pwd) < 6) {
                $errors[] = 'al menos 6 caracteres';
            }
        } elseif ($lvl === 'medium') {
            if (strlen($pwd) < 8) {
                $errors[] = 'al menos 8 caracteres';
            }
            if (!preg_match('/[A-Z]/', $pwd)) {
                $errors[] = 'al menos una mayúscula';
            }
            if (!preg_match('/[a-z]/', $pwd)) {
                $errors[] = 'al menos una minúscula';
            }
            if (!preg_match('/[0-9]/', $pwd)) {
                $errors[] = 'al menos un número';
            }
        } else { // strong
            if (strlen($pwd) < 12) {
                $errors[] = 'al menos 12 caracteres';
            }
            if (!preg_match('/[A-Z]/', $pwd)) {
                $errors[] = 'al menos una mayúscula';
            }
            if (!preg_match('/[a-z]/', $pwd)) {
                $errors[] = 'al menos una minúscula';
            }
            if (!preg_match('/[0-9]/', $pwd)) {
                $errors[] = 'al menos un número';
            }
            if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $pwd)) {
                $errors[] = 'al menos un carí¡cter especial';
            }
        }

        if ($errors) {
            $this->errors[$field] = "El campo '$field' debe tener " . implode(', ', $errors);
            return false;
        }
        return true;
    }

    private function validatePhone(string $field): bool
    {
        if (!isset($this->data[$field]) || $this->data[$field] === '') {
            return true;
        }

        $pattern = '/^[\+]?[1-9][\d]{0,15}$/';
        $clean = preg_replace('/[\s\-\(\)]/', '', (string)$this->data[$field]);
        $ok = (bool)preg_match($pattern, $clean);

        if (!$ok) {
            $this->errors[$field] = "El campo '$field' debe ser un teléfono ví¡lido";
        }
        return $ok;
    }

    private function validateFileType(string $field, ?string $allowedTypes): bool
    {
        if (!isset($_FILES[$field])) {
            return true;
        }

        $allowed = array_map('trim', explode(',', (string)$allowedTypes));
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $_FILES[$field]['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed, true)) {
            $this->errors[$field] = "El archivo '$field' debe ser de tipo: " . implode(', ', $allowed);
            return false;
        }
        return true;
    }

    private function validateFileSize(string $field, ?string $maxSize): bool
    {
        if (!isset($_FILES[$field])) {
            return true;
        }
        $max = (int)($maxSize ?? 0);
        if ($max > 0 && (int)$_FILES[$field]['size'] > $max) {
            $MB = round($max / 1024 / 1024, 2);
            $this->errors[$field] = "El archivo '$field' no debe exceder {$MB}MB";
            return false;
        }
        return true;
    }

    /* ==== Sanitización y utilidades ==== */

    public static function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeInput'], $data);
        }
        if (is_string($data)) {
            return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    public static function sanitizeForDatabase($data)
    {
        if (is_array($data)) {
            return array_map([self::class, 'sanitizeForDatabase'], $data);
        }
        if (is_string($data)) {
            return trim(strip_tags($data));
        }
        return $data;
    }

    public static function validateCsrfToken($token, $sessionToken): bool
    {
        if (empty($token) || empty($sessionToken)) {
            return false;
        }
        return hash_equals((string)$sessionToken, (string)$token);
    }

    public static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function validateFileUpload(array $file, array $options = []): array
    {
        $errors = [];
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['File was not uploaded properly'];
        }
        if (isset($options['max_size']) && (int)$file['size'] > (int)$options['max_size']) {
            $MB = round(((int)$options['max_size']) / 1024 / 1024, 2);
            $errors[] = "File size exceeds {$MB}MB";
        }
        if (isset($options['allowed_types'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, (array)$options['allowed_types'], true)) {
                $errors[] = 'File type not allowed';
            }
        }
        if (isset($options['allowed_extensions'])) {
            $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
            if (!in_array($ext, (array)$options['allowed_extensions'], true)) {
                $errors[] = 'File extension not allowed';
            }
        }
        return $errors;
    }
}
