<?php

declare(strict_types=1);

// backend/src/Utils/InputValidator.php

namespace Utils;

/**
 * Validador y saneador de entradas (PHP 8.x, sin deprecateds)
 */
class InputValidator
{
    /** @var array<string,string> */
    private array $errors = [];

    /* ===== Helpers internos ===== */

    private function mbLen(string $s): int
    {
        return \function_exists('mb_strlen') ? \mb_strlen($s, 'UTF-8') : \strlen($s);
    }

    private function mbCut(string $s, int $max): string
    {
        if ($max <= 0) {
            return $s;
        }
        return \function_exists('mb_substr') ? \mb_substr($s, 0, $max, 'UTF-8') : \substr($s, 0, $max);
    }

    private function add(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }

    /* ===== Validaciones ===== */

    public function validateRequired(mixed $value, string $fieldName = 'field'): bool
    {
        if (is_string($value)) {
            $value = trim($value);
        }
        $empty = $value === null
          || $value === ''
          || (is_array($value) && count($value) === 0);
        if ($empty) {
            $this->add($fieldName, "El campo $fieldName es requerido");
            return false;
        }
        return true;
    }

    public function validateAlphanumeric(mixed $value, string $fieldName = 'field'): bool
    {
        $v = (string)($value ?? '');
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $v)) {
            $this->add($fieldName, "El campo $fieldName solo puede contener letras, números, guiones y guiones bajos");
            return false;
        }
        return true;
    }

    public function validateEmail(mixed $value, string $fieldName = 'email'): bool
    {
        $v = is_string($value) ? trim($value) : '';
        if (filter_var($v, FILTER_VALIDATE_EMAIL) === false) {
            $this->add($fieldName, "El campo $fieldName debe ser un email válido");
            return false;
        }
        return true;
    }

    /** YYYY-MM-DD estricta */
    public function validateDate(mixed $value, string $fieldName = 'date'): bool
    {
        $v = (string)($value ?? '');
        $dt = \DateTime::createFromFormat('Y-m-d', $v);
        if (!$dt || $dt->format('Y-m-d') !== $v) {
            $this->add($fieldName, "El campo $fieldName debe tener formato YYYY-MM-DD");
            return false;
        }
        return true;
    }

    /** YYYY-MM-DD HH:MM:SS estricta */
    public function validateDateTime(mixed $value, string $fieldName = 'datetime'): bool
    {
        $v = (string)($value ?? '');
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $v);
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $v) {
            $this->add($fieldName, "El campo $fieldName debe tener formato YYYY-MM-DD HH:MM:SS");
            return false;
        }
        return true;
    }

    public function validateMinLength(mixed $value, int $min, string $fieldName = 'field'): bool
    {
        $v = (string)($value ?? '');
        if ($this->mbLen($v) < $min) {
            $this->add($fieldName, "El campo $fieldName debe tener al menos $min caracteres");
            return false;
        }
        return true;
    }

    public function validateMaxLength(mixed $value, int $max, string $fieldName = 'field'): bool
    {
        $v = (string)($value ?? '');
        if ($this->mbLen($v) > $max) {
            $this->add($fieldName, "El campo $fieldName no puede tener más de $max caracteres");
            return false;
        }
        return true;
    }

    public function validateInteger(mixed $value, string $fieldName = 'field'): bool
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->add($fieldName, "El campo $fieldName debe ser un número entero");
            return false;
        }
        return true;
    }

    public function validateFloat(mixed $value, string $fieldName = 'field'): bool
    {
        if (filter_var($value, FILTER_VALIDATE_FLOAT) === false) {
            $this->add($fieldName, "El campo $fieldName debe ser un número decimal");
            return false;
        }
        return true;
    }

    public function validateInArray(mixed $value, array $allowedValues, string $fieldName = 'field'): bool
    {
        if (!in_array($value, $allowedValues, true)) {
            $allowed = implode(', ', array_map(static fn ($x) => (string)$x, $allowedValues));
            $this->add($fieldName, "El campo $fieldName debe ser uno de: $allowed");
            return false;
        }
        return true;
    }

    public function validateJson(mixed $value, string $fieldName = 'field'): bool
    {
        if (!is_string($value)) {
            $this->add($fieldName, "El campo $fieldName debe ser un JSON válido");
            return false;
        }
        json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->add($fieldName, "El campo $fieldName debe ser un JSON válido");
            return false;
        }
        return true;
    }

    public function validateUrl(mixed $value, string $fieldName = 'url'): bool
    {
        $v = is_string($value) ? trim($value) : '';
        if (!filter_var($v, FILTER_VALIDATE_URL)) {
            $this->add($fieldName, "El campo $fieldName debe ser una URL válida");
            return false;
        }
        $scheme = parse_url($v, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            $this->add($fieldName, "El campo $fieldName debe usar http o https");
            return false;
        }
        return true;
    }

    public function validateBoolean(mixed $value, string $fieldName = 'field'): bool
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($bool === null) {
            $this->add($fieldName, "El campo $fieldName debe ser un valor booleano");
            return false;
        }
        return true;
    }

    public function validateRange(mixed $value, float|int $min, float|int $max, string $fieldName = 'field'): bool
    {
        if (!is_numeric($value)) {
            $this->add($fieldName, "El campo $fieldName debe ser numérico");
            return false;
        }
        $num = $value + 0;
        if ($num < $min || $num > $max) {
            $this->add($fieldName, "El campo $fieldName debe estar entre $min y $max");
            return false;
        }
        return true;
    }

    public function validateRegex(mixed $value, string $pattern, string $fieldName = 'field', ?string $message = null): bool
    {
        $v = (string)($value ?? '');
        if (!preg_match($pattern, $v)) {
            $this->add($fieldName, $message ?: "El campo $fieldName no tiene el formato correcto");
            return false;
        }
        return true;
    }

    /* ===== Saneadores ===== */

    /**
     * Sanea texto:
     * - Elimina etiquetas HTML
     * - Decodifica entidades (&nbsp; &amp; &quot; …)
     * - Sustituye saltos/tabs por espacio y compacta
     * - Elimina NBSP/zero-width/control chars
     * - Normaliza Unicode a NFC (si intl está disponible)
     * - Trunca a $maxLen (por defecto 2000)
     */
    public function sanitizeText(mixed $value, int $maxLen = 2000): string
    {
        $str = (string)($value ?? '');

        // 1) Quitar HTML
        $str = strip_tags($str);

        // 2) Decodificar entidades
        $str = html_entity_decode($str, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        // 3) Normalizar saltos/tabs → espacio
        $str = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $str);

        // 4) NBSP → espacio
        $str = preg_replace('/\x{00A0}+/u', ' ', $str) ?? $str;

        // 5) Eliminar zero-width (ZWSP/ZWJ/ZWNJ/BOM)
        $str = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $str) ?? $str;

        // 6) Eliminar controles (manteniendo espacios normales)
        $str = preg_replace('/\p{C}+/u', '', $str) ?? $str;

        // 7) Compactar espacios
        $str = preg_replace('/\s+/u', ' ', $str) ?? $str;

        // 8) Trim
        $str = trim($str);

        // 9) Normalización Unicode (si intl está cargado)
        if (class_exists('\Normalizer')) {
            $str = \Normalizer::normalize($str, \Normalizer::FORM_C) ?? $str;
        }

        // 10) Truncado
        if ($maxLen > 0 && $this->mbLen($str) > $maxLen) {
            $str = $this->mbCut($str, $maxLen);
        }

        return $str;
    }

    /** Alias por compatibilidad histórica */
    public function sanitizeString(mixed $value): string
    {
        return $this->sanitizeText($value);
    }

    public function sanitizeEmail(mixed $value): string
    {
        $v = is_string($value) ? trim($value) : '';
        return (string)filter_var($v, FILTER_SANITIZE_EMAIL);
    }

    public function sanitizeInt(mixed $value): int
    {
        return (int)filter_var((string)$value, FILTER_SANITIZE_NUMBER_INT);
    }

    public function sanitizeFloat(mixed $value): float
    {
        return (float)filter_var((string)$value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /* ===== Errores ===== */

    /** @return array<string,string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /**
     * Validación por reglas simples
     * @param array<string,mixed> $data
     * @param array<string,array<string,mixed>> $rules
     */
    public function validateFields(array $data, array $rules): bool
    {
        $isValid = true;

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule => $params) {
                switch ($rule) {
                    case 'required':
                        if ($params && !$this->validateRequired($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'email':
                        if ($value !== null && $value !== '' && !$this->validateEmail($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'min_length':
                        if ($value !== null && !$this->validateMinLength($value, (int)$params, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'max_length':
                        if ($value !== null && !$this->validateMaxLength($value, (int)$params, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'integer':
                        if ($value !== null && !$this->validateInteger($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'in_array':
                        if ($value !== null && !$this->validateInArray($value, (array)$params, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'regex':
                        if ($value !== null && !$this->validateRegex($value, (string)$params, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'date':
                        if ($value !== null && !$this->validateDate($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'datetime':
                        if ($value !== null && !$this->validateDateTime($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'url':
                        if ($value !== null && !$this->validateUrl($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'boolean':
                        if ($value !== null && !$this->validateBoolean($value, $field)) {
                            $isValid = false;
                        }
                        break;

                    case 'range':
                        if (is_array($params) && count($params) === 2) {
                            [$min, $max] = array_values($params);
                            if (!$this->validateRange($value, $min, $max, $field)) {
                                $isValid = false;
                            }
                        }
                        break;
                }
            }
        }

        return $isValid;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] = $message;
    }
}
