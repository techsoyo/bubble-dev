<?php declare(strict_types=1);
namespace Services\CV;

/**
 * Servicio avanzado para el anÃƒÂ¡lisis y extracciÃƒÂ³n de informaciÃƒÂ³n de CVs
 */
class AdvancedCVParser
{
    private $cvText;
    private $patterns = [];

    public function __construct(string $cvText = '')
    {
        $this->cvText = $cvText;
        $this->initPatterns();
    }

    /**
     * Establece el texto del CV a analizar
     */
    public function setText(string $cvText): self
    {
        $this->cvText = $cvText;
        return $this;
    }

    /**
     * Inicializa los patrones de expresiones regulares para la extracciÃƒÂ³n
     */
    private function initPatterns(): void
    {
        $this->patterns = [
          'nombre' => [
            '/(?:Nombre|Nombre completo)[:\s]+([A-Z\u00C0-\u017F][a-z\u00C0-\u017F]+(?: [A-Z][a-z\u00C0-\u017F]+)+)/u',
            '/^([A-Z\u00C0-\u017F][a-z\u00C0-\u017F]+(?: [A-Z][a-z\u00C0-\u017F]+){1,3})\s/mu'
          ],
          'email' => [
            '/[\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,6}/',
            '/(?:Email|Correo|E-mail)[:\s]*([\w._%+-]+@[\w.-]+\.[a-zA-Z]{2,6})/i'
          ],
          'telefono' => [
            '/(?:Tel[ÃƒÂ©e]fono|Phone)[:\s]*(\+?\d{1,4}[\s.-]?\d{2,4}[\s.-]?\d{2,4}[\s.-]?\d{2,4})/i',
            '/(\+?\d{1,4}[\s.-]?\d{2,4}[\s.-]?\d{2,4}[\s.-]?\d{2,4})/'
          ],
          'linkedin' => [
            '/(?:linkedin\.com\/in\/[\w\-]+)/i',
            '/(?:Linkedin|Perfil)[:\s]*(linkedin\.com\/in\/[\w\-]+)/i'
          ],
          'ubicacion' => [
            '/(?:Ubicaci[ÃƒÂ³o]n|Direcci[ÃƒÂ³o]n|Ciudad)[:\s]+([A-Z][a-z\u00C0-\u017F]+(?: [A-Z][a-z\u00C0-\u017F]+)*,?\s*(?:[A-Z][a-z\u00C0-\u017F]+)?)/u',
            '/(?:CP|C\.P\.|CÃƒÂ³digo postal)[:\s]*(\d{5})/'
          ]
        ];
    }

    /**
     * Analiza el CV completo y extrae toda la informaciÃƒÂ³n disponible
     */
    public function analyzeCV(): array
    {
        // Aplicar limpieza bÃƒÂ¡sica al texto
        $cleanText = $this->cleanText($this->cvText);

        // Extraer informaciÃƒÂ³n bÃƒÂ¡sica
        $result = [
          'nombre' => $this->extractPattern('nombre', $cleanText),
          'email' => $this->extractPattern('email', $cleanText),
          'telefono' => $this->extractPattern('telefono', $cleanText),
          'linkedin' => $this->extractPattern('linkedin', $cleanText),
          'ubicacion_actual' => $this->extractPattern('ubicacion', $cleanText),
          'formacion' => $this->extractEducation($cleanText),
          'experiencia' => $this->extractExperience($cleanText),
          'tecnologias' => $this->extractSkills($cleanText),
          'idiomas' => $this->extractLanguages($cleanText),
          'certificaciones' => $this->extractCertifications($cleanText),
          'categoria' => $this->categorizeProfile($cleanText),
          'subcategoria' => '',
          'resumen' => $this->generateSummary($cleanText)
        ];

        // Asignar subcategorÃƒÂ­a basada en la categorÃƒÂ­a
        $result['subcategoria'] = $this->assignSubcategory($result['categoria'], $cleanText);

        return $result;
    }

    /**
     * Limpia el texto del CV para eliminar formato y caracteres especiales
     */
    private function cleanText(string $text): string
    {
        // Normalizar saltos de lÃƒÂ­nea
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Eliminar mÃƒÂºltiples espacios
        $text = preg_replace('/\s{2,}/', ' ', $text);

        // Eliminar marcas de agua comunes
        $text = preg_replace('/www\.cv-maker\.com|cvonline\.com|Indeed\.com|www\.linkedin\.com/i', '', $text);

        // Eliminar caracteres de control excepto saltos de lÃƒÂ­nea
        $text = preg_replace('/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F]/', '', $text);

        return trim($text);
    }

    /**
     * Extrae informaciÃƒÂ³n usando patrones de expresiones regulares
     */
    private function extractPattern(string $type, string $text): string
    {
        if (!isset($this->patterns[$type])) {
            return '';
        }

        foreach ($this->patterns[$type] as $pattern) {
            if (preg_match($pattern, $text, $matches)) {
                return trim($matches[1] ?? $matches[0]);
            }
        }

        return '';
    }

    /**
     * Extrae informaciÃƒÂ³n educativa del CV
     */
    private function extractEducation(string $text): array
    {
        $education = [];

        // Encontrar secciÃƒÂ³n de educaciÃƒÂ³n
        if (preg_match('/(?:Formaci[ÃƒÂ³o]n|Educaci[ÃƒÂ³o]n|Estudios)[:\s]+([\s\S]+?)(?:Experiencia|Habilidades|Idiomas|$)/i', $text, $section)) {
            $educationText = $section[1];

            // Buscar patrones de estudios (grado, mÃƒÂ¡ster, tÃƒÂ­tulo)
            preg_match_all('/(?:(?:19|20)\d{2}[\s-]+(?:19|20)\d{2}|(?:19|20)\d{2}[\s-]+(?:Actualidad|Presente|Actual))\s+([^,\n]+),?\s+([^,\n]+)/i', $educationText, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $period = trim($match[0]);
                $title = trim($match[1]);
                $institution = trim($match[2]);

                // Extraer fechas
                preg_match('/(?:19|20)(\d{2})[\s-]+(?:(19|20)(\d{2})|(?:Actualidad|Presente|Actual))/i', $period, $dates);

                $startYear = isset($dates[1]) ? '20' . $dates[1] : '';
                $endYear = isset($dates[3]) ? '20' . $dates[3] : (preg_match('/Actualidad|Presente|Actual/i', $period) ? 'Presente' : '');

                $education[] = [
                  'titulo' => $title,
                  'institucion' => $institution,
                  'fecha_inicio' => $startYear,
                  'fecha_fin' => $endYear
                ];
            }
        }

        return $education;
    }

    /**
     * Extrae experiencia laboral del CV
     */
    private function extractExperience(string $text): array
    {
        $experience = [];

        // Encontrar secciÃƒÂ³n de experiencia
        if (preg_match('/(?:Experiencia|Historial laboral|Trayectoria)[:\s]+([\s\S]+?)(?:Formaci[ÃƒÂ³o]n|Educaci[ÃƒÂ³o]n|Estudios|Habilidades|$)/i', $text, $section)) {
            $experienceText = $section[1];

            // Buscar patrones de experiencia (empresa, puesto, periodo)
            preg_match_all('/(?:(?:19|20)\d{2}[\s-]+(?:19|20)\d{2}|(?:19|20)\d{2}[\s-]+(?:Actualidad|Presente|Actual))\s+([^,\n]+),?\s+([^,\n]+)(?:[\s\S]*?)((?:Responsabilidades|Funciones|Tareas)[:.\s]+([\s\S]*?)(?=(?:19|20)\d{2}|$))?/i', $experienceText, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $period = trim($match[0]);
                $company = trim($match[1]);
                $position = trim($match[2]);
                $functions = isset($match[4]) ? trim($match[4]) : '';

                // Extraer fechas
                preg_match('/(?:19|20)(\d{2})[\s-]+(?:(19|20)(\d{2})|(?:Actualidad|Presente|Actual))/i', $period, $dates);

                $startYear = isset($dates[1]) ? '20' . $dates[1] : '';
                $endYear = isset($dates[3]) ? '20' . $dates[3] : (preg_match('/Actualidad|Presente|Actual/i', $period) ? 'Presente' : '');

                $experience[] = [
                  'empresa' => $company,
                  'puesto' => $position,
                  'fecha_inicio' => $startYear,
                  'fecha_fin' => $endYear,
                  'funciones' => $functions
                ];
            }
        }

        return $experience;
    }

    /**
     * Extrae habilidades y tecnologÃƒÂ­as del CV
     */
    private function extractSkills(string $text): array
    {
        $skills = [];

        // Buscar secciones comunes de habilidades
        if (preg_match('/(?:Skills|Habilidades|Competencias|Tecnolog[ÃƒÂ­i]as)[:\s]+([\s\S]+?)(?:Idiomas|Experiencia|Formaci[ÃƒÂ³o]n|$)/i', $text, $section)) {
            $skillsText = $section[1];

            // Dividir por separadores comunes
            $skillsArray = preg_split('/[,Ã¢â‚¬Â¢\n\-]+/', $skillsText);

            foreach ($skillsArray as $skill) {
                $skill = trim($skill);
                if (strlen($skill) > 2 && !preg_match('/^\d+$/', $skill)) {
                    $skills[] = $skill;
                }
            }
        }

        // Buscar tecnologÃƒÂ­as especÃƒÂ­ficas en todo el texto
        $techKeywords = [
          'HTML',
          'CSS',
          'JavaScript',
          'TypeScript',
          'React',
          'Angular',
          'Vue',
          'Node',
          'PHP',
          'Python',
          'Java',
          'C#',
          '.NET',
          'SQL',
          'MySQL',
          'PostgreSQL',
          'MongoDB',
          'AWS',
          'Azure',
          'Docker',
          'Kubernetes',
          'Git',
          'Photoshop',
          'Illustrator',
          'InDesign',
          'Figma',
          'Sketch',
          'XD',
          'WordPress',
          'SEO',
          'SEM',
          'Google Analytics',
          'Social Media',
          'Marketing Digital'
        ];

        foreach ($techKeywords as $tech) {
            if (preg_match('/\b' . preg_quote($tech, '/') . '\b/i', $text) && !in_array($tech, $skills)) {
                $skills[] = $tech;
            }
        }

        return array_unique($skills);
    }

    /**
     * Extrae idiomas del CV
     */
    private function extractLanguages(string $text): array
    {
        $languages = [];

        // Buscar secciÃƒÂ³n de idiomas
        if (preg_match('/(?:Idiomas|Lenguas|Languages)[:\s]+([\s\S]+?)(?:Skills|Habilidades|Competencias|Experiencia|Formaci[ÃƒÂ³o]n|$)/i', $text, $section)) {
            $languagesText = $section[1];

            // Buscar patrones de idiomas con niveles
            preg_match_all('/(?:Espa[ÃƒÂ±n]ol|Ingl[ÃƒÂ©e]s|Franc[ÃƒÂ©e]s|Alem[aÃƒÂ¡]n|Italiano|Portugu[ÃƒÂ©e]s|Catal[aÃƒÂ¡]n|Chino|Ruso)[:\s]*(?:Nativo|BilingÃƒÂ¼e|Fluido|Avanzado|Intermedio|BÃƒÂ¡sico|A1|A2|B1|B2|C1|C2)/i', $languagesText, $matches);

            foreach ($matches[0] as $match) {
                $parts = preg_split('/[:\s]+/', trim($match), 2);
                if (count($parts) >= 2) {
                    $languages[] = [
                      'idioma' => trim($parts[0]),
                      'nivel' => trim($parts[1])
                    ];
                } else {
                    $languages[] = [
                      'idioma' => trim($match),
                      'nivel' => ''
                    ];
                }
            }
        }

        // Si no se encontraron idiomas estructurados, buscar referencias sueltas
        if (empty($languages)) {
            $languageKeywords = ['EspaÃƒÂ±ol', 'InglÃƒÂ©s', 'FrancÃƒÂ©s', 'AlemÃƒÂ¡n', 'Italiano', 'PortuguÃƒÂ©s', 'CatalÃƒÂ¡n'];

            foreach ($languageKeywords as $lang) {
                if (preg_match('/\b' . preg_quote($lang, '/') . '\b/i', $text)) {
                    $languages[] = [
                      'idioma' => $lang,
                      'nivel' => ''
                    ];
                }
            }
        }

        return $languages;
    }

    /**
     * Extrae certificaciones del CV
     */
    private function extractCertifications(string $text): array
    {
        $certifications = [];

        // Buscar secciÃƒÂ³n de certificaciones
        if (preg_match('/(?:Certificaciones|Certificados|Cursos)[:\s]+([\s\S]+?)(?:Idiomas|Skills|Habilidades|Experiencia|Formaci[ÃƒÂ³o]n|$)/i', $text, $section)) {
            $certText = $section[1];

            // Dividir por lÃƒÂ­neas o puntos
            $certLines = preg_split('/[\nÃ¢â‚¬Â¢\-]+/', $certText);

            foreach ($certLines as $line) {
                $line = trim($line);
                if (strlen($line) > 5 && !preg_match('/^\d+$/', $line)) {
                    $certifications[] = $line;
                }
            }
        }

        return $certifications;
    }

    /**
     * Categoriza el perfil del candidato
     */
    private function categorizeProfile(string $text): string
    {
        $categories = [
          'Management' => ['Account Manager', 'Director', 'Gerente', 'Coordinador', 'Jefe', 'LÃƒÂ­der', 'Manager', 'Strategist', 'Planner'],
          'Creativity (Art & Design)' => ['Copywriter', 'Art Director', 'DiseÃƒÂ±ador GrÃƒÂ¡fico', 'Graphic Designer', 'Content Creator', 'Content Strategist', 'Creativo', 'Ilustrador'],
          'Digital & Technology' => ['UX', 'UI', 'Developer', 'Desarrollador', 'Programador', 'Frontend', 'Backend', 'Full Stack', 'Digital Project Manager', 'Ingeniero'],
          'Audiovisual & Production' => ['Video', 'Producer', 'Motion Graphics', 'PostproducciÃƒÂ³n', 'Editor', 'Audiovisual', 'Multimedia'],
          'Events & Experiences' => ['Event', 'Eventos', 'Experiencial', 'Production Coordinator', 'ProducciÃƒÂ³n'],
          'Communication & PR' => ['PR', 'Media Relations', 'Community Manager', 'Content Manager', 'ComunicaciÃƒÂ³n', 'Periodista'],
          'Paid Media & Performance' => ['Google Ads', 'Meta', 'TikTok', 'Performance', 'Email Marketing', 'CRM', 'Digital Analytics', 'SEO', 'SEM'],
          'Internships/Junior' => ['Intern', 'PrÃƒÂ¡cticas', 'Becario', 'Junior', 'Trainee', 'Estudiante']
        ];

        $matchCounts = [];
        foreach ($categories as $category => $keywords) {
            $matchCounts[$category] = 0;
            foreach ($keywords as $keyword) {
                $count = preg_match_all('/\b' . preg_quote($keyword, '/') . '\b/i', $text, $matches);
                $matchCounts[$category] += $count;
            }
        }

        // Obtener la categorÃƒÂ­a con mÃƒÂ¡s coincidencias
        arsort($matchCounts);
        $topCategories = array_keys($matchCounts);

        return $topCategories[0] ?? 'Digital & Technology'; // CategorÃƒÂ­a por defecto si no hay coincidencias
    }

    /**
     * Asigna una subcategorÃƒÂ­a basada en la categorÃƒÂ­a principal
     */
    private function assignSubcategory(string $category, string $text): string
    {
        $subcategories = [
          'Management' => [
            'Account Manager' => ['Account Manager', 'Cliente', 'Cuenta'],
            'Account Director' => ['Director', 'Account Director'],
            'Medical Strategist/Planner' => ['Medical', 'Healthcare', 'Salud', 'Planner', 'Estratega'],
            'Scientific Account Executive' => ['Scientific', 'CientÃƒÂ­fico', 'Account Executive']
          ],
          'Creativity (Art & Design)' => [
            'Copywriter (health)' => ['Copywriter', 'Redactor', 'Copy', 'Contenidos'],
            'Art Director' => ['Art Director', 'Director de Arte'],
            'Graphic Designer' => ['Graphic Designer', 'DiseÃƒÂ±ador GrÃƒÂ¡fico', 'GrÃƒÂ¡fico'],
            'Content Creator/Content Strategist' => ['Content', 'Contenido', 'Estrategia de Contenido']
          ],
          'Digital & Technology' => [
            'UX/UI Designer' => ['UX', 'UI', 'User Experience', 'User Interface', 'DiseÃƒÂ±ador de Experiencia'],
            'Front-end/Web Developer' => ['Front', 'Frontend', 'HTML', 'CSS', 'JavaScript'],
            'Mobile Developer (iOS/Android)' => ['Mobile', 'iOS', 'Android', 'Swift', 'Kotlin', 'React Native'],
            'Digital Project Manager' => ['Project Manager', 'Digital Project', 'Gestor de Proyectos']
          ]
        ];

        if (!isset($subcategories[$category])) {
            return '';
        }

        $matchCounts = [];
        foreach ($subcategories[$category] as $subcategory => $keywords) {
            $matchCounts[$subcategory] = 0;
            foreach ($keywords as $keyword) {
                $count = preg_match_all('/\b' . preg_quote($keyword, '/') . '\b/i', $text, $matches);
                $matchCounts[$subcategory] += $count;
            }
        }

        // Obtener la subcategorÃƒÂ­a con mÃƒÂ¡s coincidencias
        arsort($matchCounts);
        $topSubcategories = array_keys($matchCounts);

        return $topSubcategories[0] ?? '';
    }

    /**
     * Genera un resumen del perfil del candidato
     */
    private function generateSummary(string $text): string
    {
        // Extraer una versiÃƒÂ³n resumida para presentaciÃƒÂ³n
        $lines = explode("\n", $text);
        $filteredLines = array_filter($lines, function ($line) {
            return strlen(trim($line)) > 10 && !preg_match('/^\s*[Ã¢â‚¬Â¢\-]\s*$/', $line);
        });

        $summary = implode(' ', array_slice($filteredLines, 0, 5));
        $summary = preg_replace('/\s{2,}/', ' ', $summary);

        // Limitar a 150 caracteres
        return substr($summary, 0, 150) . (strlen($summary) > 150 ? '...' : '');
    }
}
