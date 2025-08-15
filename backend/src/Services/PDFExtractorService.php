<?php

namespace Services;

/**
 * Servicio para la extracción de texto de PDF (adaptado de PDFExtractorModel)
 */
class PDFExtractorService
{
    private string $uploadDir;
    private string $cvUploadDir;
    private int $maxFileSize;

    /**
     * Sanitiza recursivamente un array para asegurar que todos los valores sean serializables por JSON
     */
    private function sanitizeForJson($data)
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                $sanitized[$key] = $this->sanitizeForJson($value);
            }
            return $sanitized;
        } elseif (is_string($data)) {
            // Forzar a UTF-8 si no lo está
            if (!mb_check_encoding($data, 'UTF-8')) {
                $data = mb_convert_encoding($data, 'UTF-8', 'auto');
            }
            // Además, eliminar caracteres de control no válidos
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $data);
            return $data;
        } elseif (is_scalar($data) || is_null($data)) {
            return $data === null ? '' : $data;
        } else {
            // Si es un objeto, recurso, closure, etc., devolver string vacío
            return '';
        }
    }

    public function __construct(?string $uploadDir = null, int $maxFileSize = 5242880) // 5MB por defecto
    {
        // Directorio para guardar los archivos de texto extraídos
        $this->uploadDir = $uploadDir ? rtrim($uploadDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : __DIR__ .
            '/../../uploads/textos/';
        // Directorio para guardar los CVs originales
        $this->cvUploadDir = __DIR__ . '/../../uploads/cvs/';
        $this->maxFileSize = $maxFileSize;

        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!file_exists($this->cvUploadDir)) {
            mkdir($this->cvUploadDir, 0755, true);
        }
    }

    public function validateFile(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \Exception(
                'Error en la subida del archivo. Código: '
                    . $file['error']
            );
        }
        if ($file['size'] === 0) {
            throw new \Exception(
                'El archivo está vacío.'
            );
        }
        if ($file['size'] > $this->maxFileSize) {
            throw new \Exception(
                'El archivo excede el tamaño máximo permitido.'
            );
        }
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if (!$finfo) {
            throw new \Exception(
                'No se pudo verificar tipo MIME del archivo.'
            );
        }
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (
            $mimeType !==
            'application/pdf'
        ) {
            throw new \Exception(
                'Solo se permiten archivos PDF. Tipo detectado: '
                    . $mimeType
            );
        }
    }

    /**
     * Copia un PDF ya existente (por ejemplo, el original guardado en /cvs/) a /textos/ para extracción temporal
     * @param string $sourcePath Ruta absoluta del PDF original
     * @return string Ruta absoluta del PDF copiado en /textos/
     */
    public function copyToTextos(string $sourcePath): string
    {
        $tempFilename = uniqid('pdf_', true) . '.pdf';
        $tempFilePath = $this->uploadDir . $tempFilename;
        if (!copy($sourcePath, $tempFilePath)) {
            throw new \Exception('Error al copiar el archivo PDF a /textos/ para extracción.');
        }
        return $tempFilePath;
    }

    public function saveOriginalPdf(array $file): string
    {
        // Asegurar que el directorio de destino existe y tiene permisos
        if (!file_exists($this->cvUploadDir)) {
            mkdir($this->cvUploadDir, 0777, true);
        }
        // Usar el nombre original del archivo subido por el usuario
        $originalFilename = basename($file['name']);
        $originalFilePath = $this->cvUploadDir . $originalFilename;

        // Habilitar el reporte de errores para depuración
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        if (!move_uploaded_file($file['tmp_name'], $originalFilePath)) {
            $lastError = error_get_last();
            $errorMessage = $lastError ? $lastError['message'] : 'Error desconocido al copiar el archivo.';
            error_log('ERROR: Fallo al copiar el PDF original: ' . $errorMessage);
            throw new \Exception(
                'Error al guardar el PDF original en el directorio de CVs: '
                    . $errorMessage
            );
        }
        error_log('DEBUG: Copia exitosa a ' . $originalFilePath);
        return $originalFilePath;
    }

    public function extractText(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \Exception(
                'El archivo PDF no existe.'
            );
        }
        try {
            if (!class_exists(
                'Smalot\\PdfParser\\Parser'
            )) {
                throw new \Exception(
                    'La librer\u00eda smalot\/pdfparser no est\u00e1 instalada.'
                );
            }
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($filePath);
            $text = $pdf->getText();
            if (empty(trim($text))) {
                throw new \Exception(
                    'No se pudo extraer texto del PDF.'
                );
            }
            return $text;
        } catch (\Throwable $e) {
            throw new \Exception(
                'Error al extraer texto del PDF: '
                    . $e->getMessage()
            );
        }
    }

    /**
     * Guarda el texto extraído en un archivo .txt y en un archivo .json
     * @param string $text
     * @return array [txt => nombreArchivoTxt, json => nombreArchivoJson]
     */
    /**
     * Guarda el texto extraído en un archivo .txt y en un archivo .json con metadatos
     * @param string $text
     * @param string|null $originalName
     * @return array [txt => nombreArchivoTxt, json => nombreArchivoJson]
     */
    public function saveTextFiles(string $text, ?string $originalName = null): array
    {
        // Asegurar que el directorio de destino existe y tiene permisos
        if (!file_exists($this->uploadDir)) {
            if (!mkdir($this->uploadDir, 0777, true)) {
                error_log('[PDFExtractorService] No se pudo crear el directorio de textos: ' . $this->uploadDir);
                throw new \Exception('No se pudo crear el directorio de textos.');
            }
        }
        $jsonDir = dirname($this->uploadDir) . '/json/';
        if (!file_exists($jsonDir)) {
            if (!mkdir($jsonDir, 0777, true)) {
                error_log('[PDFExtractorService] No se pudo crear el directorio de JSON: ' . $jsonDir);
                throw new \Exception('No se pudo crear el directorio de JSON.');
            }
        }
        $baseName = $originalName
            ? pathinfo($originalName, PATHINFO_FILENAME) . '_' . date('Y-m-d_H-i-s')
            : 'texto_extraido_' . date('Y-m-d_H-i-s');
        $txtFile = $baseName . '.txt';
        $jsonFile = $baseName . '.json';
        $txtPath = $this->uploadDir . $txtFile;
        $jsonPath = $jsonDir . $jsonFile;

        if (file_put_contents($txtPath, $text) === false) {
            error_log('[PDFExtractorService] Error al guardar el texto extraído en: ' . $txtPath);
            throw new \Exception('Error al guardar el texto extraído (.txt).');
        }

        // --- Generar el JSON con la estructura extraída ---
        $hardSkills = $this->extractHardSkills($text);
        $experience = $this->extractExperience($text);
        $languages = $this->extractLanguages($text);
        $email = $this->extractEmail($text);
        $phone = $this->extractPhone($text);
        $linkedin = $this->extractLinkedin($text);
        $github = $this->extractGithub($text);
        $areaOfInterest = $this->extractAreaOfInterest($text);
        $categoria = $this->determineCategory($text);
        $extractedData = [
            [
                'personal_info' => [
                    'name' => $this->extractName($text),
                    'email' => $email,
                    'phone' => $phone,
                    'linkedin_url' => $linkedin,
                    'github_url' => $github,
                    'website' => $this->extractWebsite($text),
                    'location' => $this->extractLocation($text),
                    'gender' => ''
                ],
                'hard_skills' => $hardSkills,
                'soft_skills' => $this->extractSoftSkills($text),
                'experience' => $experience,
                'education' => $this->extractEducation($text),
                'languages' => $languages,
                'area_of_interest' => $areaOfInterest,
                'job_search_type' => '',
                'work_modality' => '',
                'desired_schedule' => '',
                'desired_salary' => [
                    'min' => 0,
                    'max' => 0,
                    'currency' => ''
                ],
                'availability_date' => '',
                'motivation' => $this->extractMotivation($text),
                'categoria' => $categoria,
                'subcategoria' => '',
                'keywords' => $this->extractKeywords($text),
                'match_score' => 0,
                'insights' => $this->generateInsights([
                    'hard_skills' => is_array($hardSkills) ? $hardSkills : [],
                    'experience' => is_array($experience) ? $experience : [],
                    'languages' => is_array($languages) ? $languages : [],
                    'email' => is_string($email) ? $email : '',
                    'phone' => is_string($phone) ? $phone : '',
                    'linkedin_url' => is_string($linkedin) ? $linkedin : '',
                    'github_url' => is_string($github) ? $github : '',
                    'area_of_interest' => is_string($areaOfInterest) ? $areaOfInterest : '',
                    'work_modality' => '',
                    'categoria' => is_string($categoria) ? $categoria : ''
                ]),
                'cv_file' => $originalName ?? '',
                'consent_gdpr' => false,
                'residence_permit' => '',
                'status' => '',
                'applied_job_id' => '',
                'date_applied' => ''
            ]
        ];


        // --- DEBUG: Volcar estructura antes de codificar ---
        $tmpDumpFile = __DIR__ . '/../../uploads/json/extracted_dump_' . date('Y-m-d_H-i-s') . '.txt';
        file_put_contents($tmpDumpFile, var_export($extractedData, true));
        error_log('[PDFExtractorService] Extracted data dump file: ' . $tmpDumpFile);

        // Registro de logs detallados para monitoreo de errores
        $logFile = __DIR__ . '/../../uploads/json/process_log_' . date('Y-m-d') . '.log';
        $log = function ($msg) use ($logFile) {
            file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
        };
        $log('Iniciando proceso de serialización JSON para ' . ($originalName ?? 'sin_nombre'));

        // Sanitizar la estructura para asegurar serialización JSON

        $extractedData = $this->sanitizeForJson($extractedData);
        $log('extractedData sanitizado: ' . substr(var_export($extractedData, true), 0, 1000));

        $jsonContent = json_encode($extractedData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($jsonContent === false) {
            $errorMsg = '[PDFExtractorService] Error al codificar el JSON: ' . json_last_error_msg();
            error_log($errorMsg);
            $log($errorMsg);
            throw new \Exception('Error al codificar el JSON IA.');
        }
        if (file_put_contents($jsonPath, $jsonContent) === false) {
            $errorMsg = '[PDFExtractorService] Error al guardar el JSON IA en: ' . $jsonPath;
            error_log($errorMsg);
            $log($errorMsg);
            throw new \Exception('Error al guardar el JSON IA (.json).');
        }
        if (!file_exists($jsonPath) || filesize($jsonPath) === 0) {
            $errorMsg = '[PDFExtractorService] El archivo JSON no se creó correctamente: ' . $jsonPath;
            error_log($errorMsg);
            $log($errorMsg);
            throw new \Exception('El archivo JSON no se creó correctamente.');
        }
        $log('JSON generado y guardado correctamente: ' . $jsonPath);

        return [
            'txt' => $txtFile,
            'json' => $jsonFile
        ];
    }

    public function deleteFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    /**
     * Extrae información estructurada del texto del CV
     * @param string $text Texto extraído del CV
     * @param string $cvFileName Nombre del archivo CV
     * @return array Datos estructurados del CV
     */
    public function extractStructuredData(string $text, string $cvFileName): array
    {
        // Implementación básica, en un entorno real se usaría NLP más avanzado
        $data = [
            'personal_info' => [
                'name' => $this->extractName($text),
                'email' => $this->extractEmail($text),
                'phone' => $this->extractPhone($text),
                'linkedin_url' => $this->extractLinkedin($text),
                'github_url' => $this->extractGithub($text),
                'website' => $this->extractWebsite($text),
                'location' => $this->extractLocation($text),
                'gender' => ''
            ],
            'hard_skills' => $this->extractHardSkills($text),
            'soft_skills' => $this->extractSoftSkills($text),
            'experience' => $this->extractExperience($text),
            'education' => $this->extractEducation($text),
            'languages' => $this->extractLanguages($text),
            'area_of_interest' => $this->extractAreaOfInterest($text),
            'job_search_type' => '',
            'work_modality' => '',
            'desired_schedule' => '',
            'desired_salary' => [
                'min' => 0,
                'max' => 0,
                'currency' => ''
            ],
            'availability_date' => '',
            'motivation' => $this->extractMotivation($text),
            'categoria' => $this->determineCategory($text),
            'subcategoria' => '',
            'keywords' => $this->extractKeywords($text),
            'match_score' => 0,
            'cv_file' => $cvFileName
        ];

        // Generar insights automáticos basados en los datos extraídos
        $data['insights'] = $this->generateInsights($data);

        return $data;
    }

    // Métodos auxiliares para la extracción de datos

    private function extractName(string $text): string
    {
        // Implementación simple, se buscan patrones comunes de nombres
        preg_match('/(?:nombre|name)[\s:]+([A-ZÁÉÍÓÚÜÑa-záéíóúüñ\s]{2,30})/i', $text, $matches);
        if (!empty($matches[1])) {
            return trim($matches[1]);
        }

        // Si no hay etiqueta de "nombre", intentar detectar un nombre al inicio del CV
        preg_match('/^([A-ZÁÉÍÓÚÜÑa-záéíóúüñ\s]{2,30})/m', $text, $matches);
        return !empty($matches[1]) ? trim($matches[1]) : '';
    }

    private function extractEmail(string $text): string
    {
        // Buscar patrones de email
        preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches);
        return !empty($matches[0]) ? $matches[0] : '';
    }

    private function extractPhone(string $text): string
    {
        // Buscar patrones de teléfono (internacional y nacional)
        preg_match('/(?:\+\d{1,3}[\s-]?)?\(?(?:\d{1,4})\)?[\s-]?\d{1,4}[\s-]?\d{1,4}[\s-]?\d{1,9}/', $text, $matches);
        return !empty($matches[0]) ? $matches[0] : '';
    }

    private function extractLinkedin(string $text): string
    {
        // Buscar URLs de LinkedIn
        preg_match('/(?:linkedin\.com\/in\/[a-zA-Z0-9_-]+|linkedin\.com\/[a-zA-Z0-9\/_-]+)/', $text, $matches);
        return !empty($matches[0]) ? $matches[0] : '';
    }

    private function extractGithub(string $text): string
    {
        // Buscar URLs de GitHub
        preg_match('/(?:github\.com\/[a-zA-Z0-9_-]+)/', $text, $matches);
        return !empty($matches[0]) ? $matches[0] : '';
    }

    private function extractWebsite(string $text): string
    {
        // Buscar URLs de sitios web (excluyendo LinkedIn y GitHub)
        preg_match('/(?:https?:\/\/)?(?:www\.)?([a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+)(?:\/[^ ]*)?/', $text, $matches);
        if (!empty($matches[0])) {
            // Evitar devolver LinkedIn o GitHub como website
            if (strpos($matches[0], 'linkedin.com') === false && strpos($matches[0], 'github.com') === false) {
                return $matches[0];
            }
        }
        return '';
    }

    private function extractLocation(string $text): string
    {
        // Buscar patrones de ubicación
        preg_match('/(?:ubicación|location|dirección|address)[\s:]+([A-ZÁÉÍÓÚÜÑa-záéíóúüñ\s,.-]{2,50})/i', $text, $matches);
        return !empty($matches[1]) ? trim($matches[1]) : '';
    }

    private function extractHardSkills(string $text): array
    {
        // Lista de habilidades técnicas comunes para buscar
        $commonSkills = [
            'HTML',
            'CSS',
            'JavaScript',
            'TypeScript',
            'Python',
            'Java',
            'C#',
            'C++',
            'React',
            'Angular',
            'Vue',
            'Node.js',
            'Express',
            'Django',
            'Flask',
            'SQL',
            'MySQL',
            'PostgreSQL',
            'MongoDB',
            'Oracle',
            'Firebase',
            'AWS',
            'Azure',
            'Google Cloud',
            'Docker',
            'Kubernetes',
            'Git',
            'SVN',
            'Jira',
            'Confluence',
            'Bitbucket',
            'Photoshop',
            'Illustrator',
            'InDesign',
            'Figma',
            'Sketch',
            'Excel',
            'Word',
            'PowerPoint',
            'Outlook',
            'Google Workspace',
            'SEO',
            'SEM',
            'Google Analytics',
            'Google Ads',
            'Facebook Ads',
            'Adobe Creative Suite',
            'Diseño Gráfico',
            'Branding',
            'Marketing Digital',
            'UX/UI'
        ];

        $skills = [];
        foreach ($commonSkills as $skill) {
            if (stripos($text, $skill) !== false) {
                // Determinar nivel y años de experiencia (si es posible)
                $level = $this->determineSkillLevel($text, $skill);
                $years = $this->determineSkillYears($text, $skill);
                $context = $this->extractSkillContext($text, $skill);

                $skills[] = [
                    'name' => $skill,
                    'level' => $level,
                    'years' => $years,
                    'context' => $context
                ];
            }
        }

        return $skills;
    }

    private function determineSkillLevel(string $text, string $skill): string
    {
        // Determinar nivel basado en patrones en el texto
        $levels = ['Básico', 'Intermedio', 'Avanzado', 'Experto'];
        $foundLevel = 'Intermedio'; // Nivel predeterminado

        foreach ($levels as $level) {
            if (
                stripos($text, "$skill.*$level") !== false ||
                stripos($text, "$level.*$skill") !== false
            ) {
                $foundLevel = $level;
                break;
            }
        }

        return $foundLevel;
    }

    private function determineSkillYears(string $text, string $skill): int
    {
        // Intentar encontrar años de experiencia para la habilidad
        preg_match('/(?:' . preg_quote($skill, '/') . '.*?(\d+).*?(?:años|años de experiencia|years|year))|(?:(\d+).*?(?:años|años de experiencia|years|year).*?' . preg_quote($skill, '/') . ')/is', $text, $matches);

        if (!empty($matches[1])) {
            return (int)$matches[1];
        } elseif (!empty($matches[2])) {
            return (int)$matches[2];
        }

        return 0; // Si no se encuentra, devolver 0
    }

    private function extractSkillContext(string $text, string $skill): string
    {
        // Extraer contexto de la habilidad (50 caracteres antes y después)
        $pos = stripos($text, $skill);
        if ($pos !== false) {
            $start = max(0, $pos - 50);
            $length = strlen($skill) + 100; // 50 antes + longitud skill + 50 después
            $context = substr($text, $start, $length);
            return trim($context);
        }
        return '';
    }

    private function extractSoftSkills(string $text): array
    {
        // Lista de habilidades blandas comunes para buscar
        $commonSoftSkills = [
            'Liderazgo',
            'Trabajo en equipo',
            'Comunicación',
            'Resolución de problemas',
            'Gestión del tiempo',
            'Adaptabilidad',
            'Creatividad',
            'Pensamiento crítico',
            'Inteligencia emocional',
            'Negociación',
            'Empatía',
            'Trabajo bajo presión',
            'Toma de decisiones',
            'Flexibilidad',
            'Proactividad',
            'Organización',
            'Gestión de proyectos',
            'Atención al detalle',
            'Orientación a resultados',
            'Capacidad analítica',
            'Innovación',
            'Trabajo colaborativo',
            'Autonomía'
        ];

        $softSkills = [];
        foreach ($commonSoftSkills as $skill) {
            if (stripos($text, $skill) !== false) {
                $softSkills[] = $skill;
            }
        }

        return $softSkills;
    }

    private function extractExperience(string $text): array
    {
        $experiences = [];

        // Buscar patrones de experiencia laboral
        preg_match_all('/(?:experiencia laboral|experiencia profesional|work experience).*?(?:cargo|puesto|position):?\s*([^\n\r]*?)\n.*?(?:empresa|company):?\s*([^\n\r]*?)\n.*?(?:periodo|fecha|date):?\s*([^\n\r]*?)\n/is', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $position = trim($match[1]);
            $company = trim($match[2]);
            $period = trim($match[3]);

            // Intentar extraer fechas del período
            $dates = $this->extractDatesFromPeriod($period);

            $experiences[] = [
                'position' => $position,
                'company' => $company,
                'start_date' => $dates['start'] ?? '',
                'end_date' => $dates['end'] ?? '',
                'responsibilities' => [],
                'achievements' => []
            ];
        }

        return $experiences;
    }

    private function extractDatesFromPeriod(string $period): array
    {
        $result = ['start' => '', 'end' => ''];

        // Buscar patrones de fechas (MM/YYYY, MM-YYYY, MM.YYYY)
        preg_match_all('/(?:\d{1,2}\/\d{4}|\d{1,2}-\d{4}|\d{1,2}\.\d{4}|\d{4})/', $period, $matches);

        if (count($matches[0]) >= 2) {
            $result['start'] = $this->formatDate($matches[0][0]);
            $result['end'] = $this->formatDate($matches[0][1]);
        } elseif (count($matches[0]) == 1) {
            $result['start'] = $this->formatDate($matches[0][0]);
            if (stripos($period, 'present') !== false || stripos($period, 'actual') !== false) {
                $result['end'] = date('Y-m-d'); // Fecha actual
            }
        }

        return $result;
    }

    private function formatDate(string $dateStr): string
    {
        // Convertir diferentes formatos de fecha a YYYY-MM-DD
        if (preg_match('/^(\d{1,2})[\/\.-](\d{4})$/', $dateStr, $matches)) {
            return sprintf('%04d-%02d-01', $matches[2], $matches[1]);
        } elseif (preg_match('/^(\d{4})$/', $dateStr, $matches)) {
            return sprintf('%s-01-01', $matches[1]);
        }

        return $dateStr;
    }

    private function extractEducation(string $text): array
    {
        $education = [];

        // Buscar patrones de educación
        preg_match_all('/(?:educación|education|formación académica).*?(?:título|degree|grado):?\s*([^\n\r]*?)\n.*?(?:institución|institution|universidad|university):?\s*([^\n\r]*?)\n.*?(?:periodo|fecha|date):?\s*([^\n\r]*?)\n/is', $text, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $degree = trim($match[1]);
            $institution = trim($match[2]);
            $period = trim($match[3]);

            // Intentar extraer fechas del período
            $dates = $this->extractDatesFromPeriod($period);

            $education[] = [
                'degree' => $degree,
                'institution' => $institution,
                'start_date' => $dates['start'] ?? '',
                'end_date' => $dates['end'] ?? ''
            ];
        }

        return $education;
    }

    private function extractLanguages(string $text): array
    {
        $languages = [];
        $commonLanguages = [
            'Español',
            'Inglés',
            'Francés',
            'Alemán',
            'Italiano',
            'Portugués',
            'Chino',
            'Japonés',
            'Ruso',
            'Árabe',
            'Holandés',
            'Catalán',
            'Gallego'
        ];

        foreach ($commonLanguages as $language) {
            if (stripos($text, $language) !== false) {
                $level = $this->determineLanguageLevel($text, $language);
                $languages[] = [
                    'name' => $language,
                    'level' => $level
                ];
            }
        }

        return $languages;
    }

    private function determineLanguageLevel(string $text, string $language): string
    {
        $levels = [
            'Nativo' => ['nativo', 'native', 'lengua materna', 'mother tongue'],
            'Bilingüe' => ['bilingüe', 'bilingual'],
            'Avanzado' => ['avanzado', 'advanced', 'c1', 'c2', 'fluido', 'fluent'],
            'Intermedio' => ['intermedio', 'intermediate', 'b1', 'b2'],
            'Básico' => ['básico', 'basic', 'a1', 'a2', 'elemental']
        ];

        foreach ($levels as $levelName => $keywords) {
            foreach ($keywords as $keyword) {
                if (
                    stripos($text, "$language.*$keyword") !== false ||
                    stripos($text, "$keyword.*$language") !== false
                ) {
                    return $levelName;
                }
            }
        }

        return 'Intermedio'; // Nivel predeterminado
    }

    private function extractAreaOfInterest(string $text): string
    {
        $areas = [
            'Diseño Gráfico',
            'Desarrollo Web',
            'Marketing Digital',
            'Ventas',
            'Recursos Humanos',
            'Finanzas',
            'Contabilidad',
            'Administración',
            'Logística',
            'Educación',
            'Salud',
            'Legal',
            'Consultoría',
            'IT',
            'Tecnología',
            'Ingeniería',
            'UX/UI',
            'Programación'
        ];

        $interests = [];
        foreach ($areas as $area) {
            if (stripos($text, $area) !== false) {
                $interests[] = $area;
            }
        }

        return implode(', ', $interests);
    }

    private function extractMotivation(string $text): string
    {
        // Intentar extraer un párrafo relacionado con motivación o presentación personal
        preg_match('/(?:acerca de mí|sobre mí|perfil|resumen|objetivo profesional|motivación|about me|profile|summary|professional objective|motivation).*?\n(.*?)(?:\n\n|\n[A-ZÁÉÍÓÚÜÑ])/is', $text, $matches);

        if (!empty($matches[1])) {
            return trim($matches[1]);
        }

        // Si no hay sección específica, intentar obtener el primer párrafo del CV
        preg_match('/^(?:\s*\n)*(.+?(?:\n.+?){0,5})\n\n/s', $text, $matches);

        return !empty($matches[1]) ? trim($matches[1]) : '';
    }

    private function determineCategory(string $text): string
    {
        $categories = [
            'Technology' => ['programación', 'developer', 'software', 'web', 'backend', 'frontend', 'fullstack', 'datos', 'data', 'AI', 'ML', 'DevOps', 'Cloud'],
            'Marketing' => ['marketing', 'SEO', 'SEM', 'redes sociales', 'publicidad', 'copywriting', 'content', 'digital'],
            'Design' => ['diseño', 'UX', 'UI', 'gráfico', 'ilustración', 'editorial', 'branding'],
            'Sales' => ['ventas', 'comercial', 'account manager', 'business development', 'customer'],
            'HR' => ['recursos humanos', 'HR', 'selección', 'talento', 'recruitment', 'personas'],
            'Finance' => ['finanzas', 'contabilidad', 'tesorería', 'auditoría', 'banca', 'inversiones'],
            'Legal' => ['legal', 'abogado', 'derecho', 'compliance', 'regulatorio'],
            'Healthcare' => ['salud', 'médico', 'enfermería', 'psicología', 'farmacia'],
            'Education' => ['educación', 'profesor', 'maestro', 'formación', 'docente'],
            'Creativity (Art & Design)' => ['diseño gráfico', 'arte', 'creativity', 'ilustración', 'audiovisual']
        ];

        $matchScores = [];
        foreach ($categories as $category => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                $count = preg_match_all('/\b' . preg_quote($keyword, '/') . '\b/i', $text, $matches);
                $score += $count;
            }
            $matchScores[$category] = $score;
        }

        // Ordenar categorías por puntuación
        arsort($matchScores);

        // Devolver la categoría con mayor puntuación
        $topCategory = key($matchScores);
        return $matchScores[$topCategory] > 0 ? $topCategory : 'General';
    }

    private function extractKeywords(string $text): string
    {
        $extractedSkills = array_map(function ($skill) {
            return $skill['name'];
        }, $this->extractHardSkills($text));

        $softSkills = $this->extractSoftSkills($text);
        $areaOfInterest = $this->extractAreaOfInterest($text);

        $allKeywords = array_merge($extractedSkills, $softSkills, explode(', ', $areaOfInterest));
        $uniqueKeywords = array_unique($allKeywords);

        return implode(', ', array_slice($uniqueKeywords, 0, 10)); // Limitar a 10 keywords
    }

    private function generateInsights(array $data): array
    {
        $strengths = [];
        $gaps = [];
        $recommendations = [];

        // Analizar fortalezas
        if (!empty($data['hard_skills'])) {
            $expertSkills = array_filter($data['hard_skills'], function ($skill) {
                return $skill['level'] == 'Experto' || $skill['level'] == 'Avanzado';
            });

            if (count($expertSkills) > 0) {
                $skills = array_map(function ($skill) {
                    return $skill['name'];
                }, array_slice($expertSkills, 0, 3));

                $strengths[] = 'Dominio en ' . implode(', ', $skills);
            }
        }

        if (!empty($data['experience'])) {
            $yearsOfExperience = $this->calculateTotalExperience($data['experience']);
            if ($yearsOfExperience > 5) {
                $strengths[] = 'Más de ' . $yearsOfExperience . ' años de experiencia profesional';
            }

            if (count($data['experience']) > 0) {
                $lastPosition = $data['experience'][0]['position'] ?? '';
                $lastCompany = $data['experience'][0]['company'] ?? '';
                if ($lastPosition && $lastCompany) {
                    $strengths[] = 'Experiencia como ' . $lastPosition . ' en ' . $lastCompany;
                }
            }
        }

        if (!empty($data['languages']) && count($data['languages']) > 1) {
            $strengths[] = 'Dominio de ' . count($data['languages']) . ' idiomas';
        }

        // Analizar gaps
        if (empty($data['email']) || empty($data['phone'])) {
            $gaps[] = 'Falta información de contacto completa';
        }

        if (empty($data['linkedin_url'])) {
            $gaps[] = 'No se especifica perfil de LinkedIn';
        }

        if (empty($data['github_url']) && stripos($data['area_of_interest'], 'desarrollo') !== false) {
            $gaps[] = 'No se especifica perfil de GitHub para un perfil técnico';
        }

        if (empty($data['work_modality'])) {
            $gaps[] = 'No se especifica preferencia de modalidad de trabajo';
        }

        // Recomendaciones
        $category = $data['categoria'];
        if ($category == 'Technology') {
            $recommendations[] = 'Perfil técnico con aptitudes para roles de desarrollo';
        } elseif ($category == 'Design' || $category == 'Creativity (Art & Design)') {
            $recommendations[] = 'Excelente candidato para posiciones creativas y de diseño';
        } elseif ($category == 'Marketing') {
            $recommendations[] = 'Perfil orientado a marketing digital y comunicación';
        } elseif ($category == 'Sales') {
            $recommendations[] = 'Candidato con experiencia comercial y orientación a resultados';
        }

        if (count($strengths) > 2) {
            $recommendations[] = 'Candidato con perfil sólido en su área de especialización';
        }

        return [
            'strengths' => $strengths,
            'gaps' => $gaps,
            'recommendations' => $recommendations
        ];
    }

    private function calculateTotalExperience(array $experiences): int
    {
        $totalMonths = 0;

        foreach ($experiences as $exp) {
            if (!empty($exp['start_date']) && !empty($exp['end_date'])) {
                $start = new \DateTime($exp['start_date']);
                $end = new \DateTime($exp['end_date']);
                $interval = $start->diff($end);
                $totalMonths += ($interval->y * 12) + $interval->m;
            }
        }

        return floor($totalMonths / 12);
    }
}
