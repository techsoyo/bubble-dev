<?php

namespace Services\Matching;

/**
 * Servicio para el matching entre candidatos y ofertas de trabajo
 * Implementación en PHP puro sin dependencias externas
 */
class JobMatchingService
{
    /**
     * Evalúa la coincidencia entre un candidato y una oferta de trabajo
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos de la oferta de trabajo
     * @return array Resultado de la evaluación
     */
    public function evaluateMatch($candidateData, $jobData)
    {
        // Calcular coincidencias por categorías
        $skillsMatch = $this->calculateSkillsMatch($candidateData, $jobData);
        $experienceMatch = $this->calculateExperienceMatch($candidateData, $jobData);
        $educationMatch = $this->calculateEducationMatch($candidateData, $jobData);
        $languageMatch = $this->calculateLanguageMatch($candidateData, $jobData);

        // Ponderación para el puntaje final
        $weights = [
          'skills' => 0.4,
          'experience' => 0.3,
          'education' => 0.2,
          'language' => 0.1
        ];

        // Calcular porcentaje final ponderado
        $finalPercentage = round(
            ($skillsMatch['percentage'] * $weights['skills']) +
            ($experienceMatch['percentage'] * $weights['experience']) +
            ($educationMatch['percentage'] * $weights['education']) +
            ($languageMatch['percentage'] * $weights['language'])
        );

        // Determinar fortalezas y debilidades
        $strengths = [];
        $weaknesses = [];

        if ($skillsMatch['percentage'] >= 70) {
            $strengths[] = 'Habilidades técnicas adecuadas para el puesto';
        } else {
            $weaknesses[] = 'Habilidades técnicas que pueden necesitar desarrollo';
        }

        if ($experienceMatch['percentage'] >= 70) {
            $strengths[] = 'Experiencia relevante para la posición';
        } else {
            $weaknesses[] = 'Podría necesitar más experiencia en el sector';
        }

        if ($educationMatch['percentage'] >= 70) {
            $strengths[] = 'Formación académica adecuada';
        } else {
            $weaknesses[] = 'Podría beneficiarse de formación adicional';
        }

        if ($languageMatch['percentage'] >= 70) {
            $strengths[] = 'Cumple con los requisitos de idiomas';
        } else {
            $weaknesses[] = 'Podría mejorar sus habilidades lingüísticas';
        }

        // Generar recomendaciones
        $recommendations = $this->generateRecommendations($finalPercentage, $strengths, $weaknesses);

        return [
          'match_percentage' => $finalPercentage,
          'strengths' => $strengths,
          'weaknesses' => $weaknesses,
          'recommendations' => $recommendations,
          'details' => [
            'skills_match' => $skillsMatch,
            'experience_match' => $experienceMatch,
            'education_match' => $educationMatch,
            'language_match' => $languageMatch
          ]
        ];
    }

    /**
     * Calcula la coincidencia de habilidades
     */
    private function calculateSkillsMatch($candidateData, $jobData)
    {
        $candidateSkills = $this->extractSkillsArray($candidateData);
        $jobSkills = $this->extractJobSkillsArray($jobData);

        if (empty($jobSkills)) {
            return ['percentage' => 100, 'matches' => [], 'missing' => []];
        }

        $matches = [];
        $missing = [];

        foreach ($jobSkills as $jobSkill) {
            $found = false;
            foreach ($candidateSkills as $candidateSkill) {
                // Comparar normalizado para mayor precisión
                if ($this->areSkillsRelated($jobSkill, $candidateSkill)) {
                    $matches[] = $jobSkill;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $missing[] = $jobSkill;
            }
        }

        $percentage = empty($jobSkills) ? 100 : round((count($matches) / count($jobSkills)) * 100);

        return [
          'percentage' => $percentage,
          'matches' => $matches,
          'missing' => $missing
        ];
    }

    /**
     * Determina si dos habilidades están relacionadas o son equivalentes
     */
    private function areSkillsRelated($skill1, $skill2)
    {
        // Normalizar skills para comparación
        $skill1 = $this->normalizeText($skill1);
        $skill2 = $this->normalizeText($skill2);

        // Verificar coincidencia exacta
        if ($skill1 === $skill2) {
            return true;
        }

        // Verificar si una contiene a la otra
        if (strpos($skill1, $skill2) !== false || strpos($skill2, $skill1) !== false) {
            return true;
        }

        // Verificar acrónimos comunes
        $acronyms = [
          'js' => 'javascript',
          'ts' => 'typescript',
          'react' => 'reactjs',
          'vue' => 'vuejs',
          'node' => 'nodejs',
          'ps' => 'photoshop',
          'ai' => 'illustrator',
          'xd' => 'adobe xd'
        ];

        // Normalizar con acrónimos
        $skill1Normalized = isset($acronyms[$skill1]) ? $acronyms[$skill1] : $skill1;
        $skill2Normalized = isset($acronyms[$skill2]) ? $acronyms[$skill2] : $skill2;

        if ($skill1Normalized === $skill2Normalized) {
            return true;
        }

        // Verificar similitud por distancia de Levenshtein para typos
        if (levenshtein($skill1, $skill2) <= 2 && strlen($skill1) > 3 && strlen($skill2) > 3) {
            return true;
        }

        // Agrupaciones de tecnologías relacionadas
        $relatedSkills = [
          ['html', 'css', 'web', 'frontend'],
          ['javascript', 'typescript', 'ecmascript'],
          ['react', 'angular', 'vue', 'frontend frameworks'],
          ['php', 'laravel', 'symfony', 'codeigniter'],
          ['java', 'spring', 'j2ee'],
          ['python', 'django', 'flask'],
          ['sql', 'mysql', 'postgresql', 'oracle', 'databases'],
          ['photoshop', 'illustrator', 'indesign', 'adobe suite'],
          ['figma', 'sketch', 'xd', 'design tools']
        ];

        // Verificar si ambas habilidades pertenecen al mismo grupo
        foreach ($relatedSkills as $group) {
            if (in_array($skill1, $group) && in_array($skill2, $group)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcula la coincidencia de experiencia
     */
    private function calculateExperienceMatch($candidateData, $jobData)
    {
        // Extraer años de experiencia del candidato
        $yearsOfExperience = $this->calculateYearsOfExperience($candidateData);

        // Extraer requisitos de experiencia del trabajo
        $requiredYears = $this->extractRequiredYearsOfExperience($jobData);

        // Si no hay requisito específico, se considera cumplido
        if ($requiredYears === 0) {
            return ['percentage' => 100, 'candidate_years' => $yearsOfExperience, 'required_years' => 0];
        }

        // Calcular porcentaje de coincidencia
        $percentage = min(100, round(($yearsOfExperience / $requiredYears) * 100));

        return [
          'percentage' => $percentage,
          'candidate_years' => $yearsOfExperience,
          'required_years' => $requiredYears
        ];
    }

    /**
     * Calcula la coincidencia de formación educativa
     */
    private function calculateEducationMatch($candidateData, $jobData)
    {
        // Extraer nivel educativo del candidato
        $candidateEducationLevel = $this->extractEducationLevel($candidateData);

        // Extraer nivel educativo requerido para el trabajo
        $requiredEducationLevel = $this->extractRequiredEducationLevel($jobData);

        // Niveles educativos en orden ascendente
        $educationLevels = [
          'ninguno' => 0,
          'secundaria' => 1,
          'tecnico' => 2,
          'grado' => 3,
          'licenciatura' => 3,
          'master' => 4,
          'postgrado' => 4,
          'doctorado' => 5
        ];

        // Si no hay requisito específico, se considera cumplido
        if ($requiredEducationLevel === 'ninguno') {
            return ['percentage' => 100, 'candidate_level' => $candidateEducationLevel, 'required_level' => $requiredEducationLevel];
        }

        // Obtener valores numéricos de los niveles
        $candidateValue = $educationLevels[$candidateEducationLevel] ?? 0;
        $requiredValue = $educationLevels[$requiredEducationLevel] ?? 0;

        // Calcular porcentaje
        $percentage = ($candidateValue >= $requiredValue) ? 100 : round(($candidateValue / $requiredValue) * 100);

        return [
          'percentage' => $percentage,
          'candidate_level' => $candidateEducationLevel,
          'required_level' => $requiredEducationLevel
        ];
    }

    /**
     * Calcula la coincidencia de idiomas
     */
    private function calculateLanguageMatch($candidateData, $jobData)
    {
        // Extraer idiomas del candidato
        $candidateLanguages = isset($candidateData['idiomas']) ? $candidateData['idiomas'] : [];

        // Convertir a formato normalizado
        $normalizedCandidateLanguages = [];
        foreach ($candidateLanguages as $lang) {
            if (is_array($lang)) {
                $language = strtolower($lang['idioma'] ?? '');
                $level = strtolower($lang['nivel'] ?? '');
                $normalizedCandidateLanguages[$language] = $level;
            } else {
                $normalizedCandidateLanguages[strtolower($lang)] = '';
            }
        }

        // Extraer idiomas requeridos para el trabajo
        $requiredLanguages = $this->extractRequiredLanguages($jobData);

        // Si no hay requisito específico, se considera cumplido
        if (empty($requiredLanguages)) {
            return ['percentage' => 100, 'matches' => [], 'missing' => []];
        }

        // Niveles de idioma en orden ascendente
        $languageLevels = [
          '' => 0,
          'basico' => 1,
          'básico' => 1,
          'elemental' => 1,
          'a1' => 1,
          'a2' => 2,
          'intermedio' => 3,
          'b1' => 3,
          'b2' => 4,
          'avanzado' => 5,
          'c1' => 5,
          'c2' => 6,
          'nativo' => 7,
          'bilingue' => 7,
          'bilingüe' => 7
        ];

        $matches = [];
        $missing = [];

        foreach ($requiredLanguages as $reqLang => $reqLevel) {
            $reqLangNormalized = strtolower($reqLang);
            $reqLevelValue = $languageLevels[strtolower($reqLevel)] ?? 0;

            $found = false;
            foreach ($normalizedCandidateLanguages as $candLang => $candLevel) {
                if ($reqLangNormalized === $candLang || $this->areLanguagesRelated($reqLangNormalized, $candLang)) {
                    $candLevelValue = $languageLevels[strtolower($candLevel)] ?? 0;

                    if ($candLevelValue >= $reqLevelValue) {
                        $matches[] = $reqLang;
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $missing[] = $reqLang;
            }
        }

        $percentage = empty($requiredLanguages) ? 100 : round((count($matches) / count($requiredLanguages)) * 100);

        return [
          'percentage' => $percentage,
          'matches' => $matches,
          'missing' => $missing
        ];
    }

    /**
     * Determina si dos idiomas están relacionados o son equivalentes
     */
    private function areLanguagesRelated($lang1, $lang2)
    {
        // Normalizar idiomas para comparación
        $lang1 = $this->normalizeText($lang1);
        $lang2 = $this->normalizeText($lang2);

        // Verificar coincidencia exacta
        if ($lang1 === $lang2) {
            return true;
        }

        // Equivalencias de idiomas
        $equivalences = [
          'espanol' => ['spanish', 'castellano', 'español'],
          'ingles' => ['english', 'inglés'],
          'frances' => ['french', 'francés'],
          'aleman' => ['german', 'alemán'],
          'italiano' => ['italian'],
          'portugues' => ['portuguese', 'portugués'],
          'catalan' => ['catalán', 'catala']
        ];

        // Verificar equivalencias
        foreach ($equivalences as $base => $variants) {
            if (($lang1 === $base || in_array($lang1, $variants)) &&
              ($lang2 === $base || in_array($lang2, $variants))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extrae array de habilidades del candidato
     */
    private function extractSkillsArray($candidateData)
    {
        $skills = [];

        // Intentar extraer de diferentes campos posibles
        if (isset($candidateData['tecnologias']) && is_array($candidateData['tecnologias'])) {
            $skills = array_merge($skills, $candidateData['tecnologias']);
        }

        if (isset($candidateData['skills']) && is_array($candidateData['skills'])) {
            $skills = array_merge($skills, $candidateData['skills']);
        }

        if (isset($candidateData['habilidades']) && is_array($candidateData['habilidades'])) {
            $skills = array_merge($skills, $candidateData['habilidades']);
        }

        // Si está en otro formato, adaptarlo
        if (empty($skills) && isset($candidateData['skills']) && is_string($candidateData['skills'])) {
            $skills = array_map('trim', explode(',', $candidateData['skills']));
        }

        // Normalizar skills
        return array_map([$this, 'normalizeText'], array_unique(array_filter($skills)));
    }

    /**
     * Extrae habilidades requeridas de la oferta de trabajo
     */
    private function extractJobSkillsArray($jobData)
    {
        $skills = [];

        // Intentar extraer de diferentes campos posibles
        if (isset($jobData['requisitos_tecnicos']) && is_array($jobData['requisitos_tecnicos'])) {
            $skills = array_merge($skills, $jobData['requisitos_tecnicos']);
        }

        if (isset($jobData['skills_requeridos']) && is_array($jobData['skills_requeridos'])) {
            $skills = array_merge($skills, $jobData['skills_requeridos']);
        }

        if (isset($jobData['tecnologias']) && is_array($jobData['tecnologias'])) {
            $skills = array_merge($skills, $jobData['tecnologias']);
        }

        // Si está en otro formato, adaptarlo
        if (empty($skills) && isset($jobData['requisitos']) && is_string($jobData['requisitos'])) {
            // Extraer skills de la descripción de requisitos
            preg_match_all('/\b(?:HTML|CSS|JavaScript|TypeScript|React|Angular|Vue|Node|PHP|Python|Java|C#|\.NET|SQL|MySQL|PostgreSQL|MongoDB|AWS|Azure|Docker|Git|Photoshop|Illustrator|InDesign|Figma|Sketch|XD|WordPress|SEO|SEM)\b/i', $jobData['requisitos'], $matches);
            $skills = array_merge($skills, $matches[0]);
        }

        // Normalizar skills
        return array_map([$this, 'normalizeText'], array_unique(array_filter($skills)));
    }

    /**
     * Calcula los años de experiencia del candidato
     */
    private function calculateYearsOfExperience($candidateData)
    {
        $totalMonths = 0;
        $currentYear = date('Y');

        if (isset($candidateData['experiencia']) && is_array($candidateData['experiencia'])) {
            foreach ($candidateData['experiencia'] as $experience) {
                $startYear = isset($experience['fecha_inicio']) ? (int)$experience['fecha_inicio'] : 0;
                $endYear = isset($experience['fecha_fin']) && $experience['fecha_fin'] !== 'Presente' ?
                  (int)$experience['fecha_fin'] : $currentYear;

                if ($startYear > 0 && $endYear >= $startYear) {
                    $totalMonths += ($endYear - $startYear) * 12;
                }
            }
        }

        return round($totalMonths / 12, 1);
    }

    /**
     * Extrae los años de experiencia requeridos para el trabajo
     */
    private function extractRequiredYearsOfExperience($jobData)
    {
        $requiredYears = 0;

        if (isset($jobData['experiencia_requerida'])) {
            if (is_numeric($jobData['experiencia_requerida'])) {
                $requiredYears = (float)$jobData['experiencia_requerida'];
            } else {
                // Intentar extraer años de una cadena de texto
                preg_match('/(\d+)[\s]*(?:año|anio|year)/i', $jobData['experiencia_requerida'], $matches);
                if (isset($matches[1])) {
                    $requiredYears = (float)$matches[1];
                }
            }
        } elseif (isset($jobData['requisitos']) && is_string($jobData['requisitos'])) {
            // Buscar referencias a años de experiencia en los requisitos
            preg_match('/(?:con|al menos|mínimo|minimo|se requiere)[\s]*(\d+)[\s]*(?:año|anio|year)/i', $jobData['requisitos'], $matches);
            if (isset($matches[1])) {
                $requiredYears = (float)$matches[1];
            }
        }

        return $requiredYears;
    }

    /**
     * Extrae el nivel educativo del candidato
     */
    private function extractEducationLevel($candidateData)
    {
        $educationKeywords = [
          'doctorado' => ['doctorado', 'phd', 'doctor', 'ph.d'],
          'master' => ['master', 'máster', 'maestría', 'maestria', 'mba'],
          'postgrado' => ['postgrado', 'postgrad', 'posgrado'],
          'licenciatura' => ['licenciatura', 'licenciado', 'grado', 'graduado'],
          'grado' => ['grado universitario', 'degree', 'diplomatura'],
          'tecnico' => ['tecnico', 'técnico', 'fp', 'formación profesional', 'ciclo formativo'],
          'secundaria' => ['bachillerato', 'secundaria', 'high school', 'eso']
        ];

        $highestLevel = 'ninguno';
        $highestLevelRank = -1;
        $educationLevelRanks = [
          'doctorado' => 6,
          'master' => 5,
          'postgrado' => 4,
          'licenciatura' => 3,
          'grado' => 2,
          'tecnico' => 1,
          'secundaria' => 0,
          'ninguno' => -1
        ];

        if (isset($candidateData['formacion']) && is_array($candidateData['formacion'])) {
            foreach ($candidateData['formacion'] as $education) {
                $title = isset($education['titulo']) ? strtolower($education['titulo']) : '';

                foreach ($educationKeywords as $level => $keywords) {
                    foreach ($keywords as $keyword) {
                        if (strpos($title, $keyword) !== false) {
                            $rank = $educationLevelRanks[$level];
                            if ($rank > $highestLevelRank) {
                                $highestLevel = $level;
                                $highestLevelRank = $rank;
                            }
                            break;
                        }
                    }
                }
            }
        }

        return $highestLevel;
    }

    /**
     * Extrae el nivel educativo requerido para el trabajo
     */
    private function extractRequiredEducationLevel($jobData)
    {
        $educationKeywords = [
          'doctorado' => ['doctorado', 'phd', 'doctor', 'ph.d'],
          'master' => ['master', 'máster', 'maestría', 'maestria', 'mba'],
          'postgrado' => ['postgrado', 'postgrad', 'posgrado'],
          'licenciatura' => ['licenciatura', 'licenciado', 'grado', 'graduado'],
          'grado' => ['grado universitario', 'degree', 'diplomatura'],
          'tecnico' => ['tecnico', 'técnico', 'fp', 'formación profesional', 'ciclo formativo'],
          'secundaria' => ['bachillerato', 'secundaria', 'high school', 'eso']
        ];

        $requiredLevel = 'ninguno';

        if (isset($jobData['formacion_requerida']) && is_string($jobData['formacion_requerida'])) {
            $formationText = strtolower($jobData['formacion_requerida']);

            foreach ($educationKeywords as $level => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($formationText, $keyword) !== false) {
                        $requiredLevel = $level;
                        break 2; // Salir de ambos bucles
                    }
                }
            }
        } elseif (isset($jobData['requisitos']) && is_string($jobData['requisitos'])) {
            $requisitosText = strtolower($jobData['requisitos']);

            foreach ($educationKeywords as $level => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($requisitosText, $keyword) !== false) {
                        $requiredLevel = $level;
                        break 2; // Salir de ambos bucles
                    }
                }
            }
        }

        return $requiredLevel;
    }

    /**
     * Extrae los idiomas requeridos para el trabajo
     */
    private function extractRequiredLanguages($jobData)
    {
        $requiredLanguages = [];

        // Palabras clave para niveles de idioma
        $levelKeywords = [
          'nativo' => ['nativo', 'native', 'lengua materna'],
          'bilingue' => ['bilingüe', 'bilingue', 'bilingual'],
          'c2' => ['c2', 'proficiency', 'dominio'],
          'c1' => ['c1', 'advanced', 'avanzado'],
          'b2' => ['b2', 'upper intermediate', 'intermedio alto'],
          'b1' => ['b1', 'intermediate', 'intermedio'],
          'a2' => ['a2', 'elementary', 'elemental'],
          'a1' => ['a1', 'beginner', 'principiante'],
          'basico' => ['básico', 'basico', 'basic']
        ];

        // Idiomas comunes
        $commonLanguages = [
          'español' => ['español', 'spanish', 'castellano'],
          'inglés' => ['inglés', 'ingles', 'english'],
          'francés' => ['francés', 'frances', 'french'],
          'alemán' => ['alemán', 'aleman', 'german'],
          'italiano' => ['italiano', 'italian'],
          'portugués' => ['portugués', 'portugues', 'portuguese'],
          'catalán' => ['catalán', 'catalan', 'catalá']
        ];

        // Buscar en requisitos de idioma específicos
        if (isset($jobData['idiomas_requeridos']) && is_array($jobData['idiomas_requeridos'])) {
            foreach ($jobData['idiomas_requeridos'] as $langReq) {
                if (is_array($langReq) && isset($langReq['idioma'])) {
                    $requiredLanguages[$langReq['idioma']] = $langReq['nivel'] ?? 'basico';
                } elseif (is_string($langReq)) {
                    $requiredLanguages[$langReq] = 'basico';
                }
            }
        }
        // Buscar en descripción general o requisitos
        elseif (isset($jobData['requisitos']) && is_string($jobData['requisitos'])) {
            $requisitosText = strtolower($jobData['requisitos']);

            foreach ($commonLanguages as $language => $variants) {
                foreach ($variants as $variant) {
                    if (strpos($requisitosText, $variant) !== false) {
                        // Intentar encontrar el nivel requerido
                        $level = 'basico'; // Nivel por defecto

                        foreach ($levelKeywords as $levelKey => $levelVariants) {
                            foreach ($levelVariants as $levelVariant) {
                                if (strpos($requisitosText, $levelVariant) !== false) {
                                    $level = $levelKey;
                                    break 2; // Salir de ambos bucles de nivel
                                }
                            }
                        }

                        $requiredLanguages[$language] = $level;
                        break; // Pasar al siguiente idioma
                    }
                }
            }
        }

        return $requiredLanguages;
    }

    /**
     * Genera recomendaciones basadas en los resultados del matching
     */
    private function generateRecommendations($percentage, $strengths, $weaknesses)
    {
        if ($percentage >= 85) {
            return 'Candidato ideal para el puesto. Recomendado para entrevista inmediata.';
        } elseif ($percentage >= 70) {
            return 'Buen candidato. Recomendado para entrevista, con enfoque en validar las áreas de ' . implode(', ', $weaknesses);
        } elseif ($percentage >= 50) {
            return 'Candidato a considerar, pero verificar su capacidad en: ' . implode(', ', $weaknesses);
        } else {
            return 'No recomendado para esta posición debido a baja coincidencia en requisitos clave.';
        }
    }

    /**
     * Normaliza texto para comparaciones
     */
    private function normalizeText($text)
    {
        if (!is_string($text)) {
            return '';
        }

        // Convertir a minúsculas
        $text = mb_strtolower($text, 'UTF-8');

        // Eliminar acentos
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        // Eliminar caracteres especiales
        $text = preg_replace('/[^a-z0-9]/', '', $text);

        return $text;
    }

    /**
     * Filtra candidatos según su coincidencia con una oferta de trabajo
     *
     * @param array $candidates Lista de candidatos
     * @param array $jobData Datos de la oferta de trabajo
     * @param int $threshold Umbral mínimo de coincidencia (0-100)
     * @return array Candidatos filtrados con puntuación
     */
    public function filterCandidatesByMatch($candidates, $jobData, $threshold = 60)
    {
        $results = [];

        foreach ($candidates as $candidate) {
            try {
                $matchResult = $this->evaluateMatch($candidate, $jobData);

                // Solo incluir candidatos que superen el umbral
                if (isset($matchResult['match_percentage']) && $matchResult['match_percentage'] >= $threshold) {
                    $results[] = [
                      'candidate' => $candidate,
                      'match' => $matchResult
                    ];
                }
            } catch (\Exception $e) {
                // Log error pero continuar con otros candidatos
                error_log('[BACKEND] Error evaluando candidato: ' . $e->getMessage());
                continue;
            }
        }

        // Ordenar por coincidencia (de mayor a menor)
        usort($results, function ($a, $b) {
            return $b['match']['match_percentage'] - $a['match']['match_percentage'];
        });

        return $results;
    }

    /**
     * Recomienda trabajos para un candidato específico
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobs Lista de ofertas de trabajo
     * @param int $limit Número máximo de recomendaciones
     * @param int $threshold Umbral mínimo de coincidencia (0-100)
     * @return array Trabajos recomendados con puntuación
     */
    public function recommendJobs($candidateData, $jobs, $limit = 5, $threshold = 50)
    {
        $recommendations = [];

        foreach ($jobs as $job) {
            try {
                $matchResult = $this->evaluateMatch($candidateData, $job);

                // Solo incluir trabajos que superen el umbral
                if (isset($matchResult['match_percentage']) && $matchResult['match_percentage'] >= $threshold) {
                    $recommendations[] = [
                      'job' => $job,
                      'match' => $matchResult
                    ];
                }

                // Limitar el número de evaluaciones para rendimiento
                if (count($recommendations) >= $limit * 2) {
                    break;
                }
            } catch (\Exception $e) {
                // Log error pero continuar con otros trabajos
                error_log('[BACKEND] Error evaluando trabajo: ' . $e->getMessage());
                continue;
            }
        }

        // Ordenar por coincidencia (de mayor a menor)
        usort($recommendations, function ($a, $b) {
            return $b['match']['match_percentage'] - $a['match']['match_percentage'];
        });

        // Limitar el número de recomendaciones
        return array_slice($recommendations, 0, $limit);
    }

    /**
     * Genera una explicación detallada de por qué un candidato coincide con un trabajo
     *
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos de la oferta de trabajo
     * @return array Explicación detallada
     */
    public function explainMatching($candidateData, $jobData)
    {
        try {
            $matchResult = $this->evaluateMatch($candidateData, $jobData);
            $percentage = $matchResult['match_percentage'] ?? 0;

            // Generar explicación basada en el porcentaje y datos disponibles
            $explanation = [
              'percentage' => $percentage,
              'level' => $this->getMatchLevel($percentage),
              'summary' => $this->generateMatchSummary($percentage, $candidateData, $jobData),
              'strengths' => $matchResult['strengths'] ?? [],
              'weaknesses' => $matchResult['weaknesses'] ?? [],
              'recommendations' => $matchResult['recommendations'] ?? ''
            ];

            return $explanation;
        } catch (\Exception $e) {
            return [
              'percentage' => 0,
              'level' => 'error',
              'summary' => 'Error al calcular matching: ' . $e->getMessage(),
              'strengths' => [],
              'weaknesses' => [],
              'recommendations' => 'No se pudo generar recomendación'
            ];
        }
    }

    /**
     * Determina el nivel de matching
     *
     * @param int $percentage Porcentaje de coincidencia
     * @return string Nivel de matching
     */
    private function getMatchLevel($percentage)
    {
        if ($percentage >= 80) {
            return 'excellent';
        }
        if ($percentage >= 60) {
            return 'good';
        }
        if ($percentage >= 40) {
            return 'fair';
        }
        return 'poor';
    }

    /**
     * Genera un resumen del matching
     *
     * @param int $percentage Porcentaje de coincidencia
     * @param array $candidateData Datos del candidato
     * @param array $jobData Datos del trabajo
     * @return string Resumen del matching
     */
    private function generateMatchSummary($percentage, $candidateData, $jobData)
    {
        $candidateName = $candidateData['nombre'] ?? $candidateData['name'] ?? 'Candidato';
        $jobTitle = $jobData['title'] ?? $jobData['titulo'] ?? 'Posición';

        if ($percentage >= 80) {
            return "Excelente coincidencia: {$candidateName} es un candidato altamente recomendado para {$jobTitle}.";
        } elseif ($percentage >= 60) {
            return "Buena coincidencia: {$candidateName} cumple con la mayoría de requisitos para {$jobTitle}.";
        } elseif ($percentage >= 40) {
            return "Coincidencia moderada: {$candidateName} tiene potencial para {$jobTitle} con algo de desarrollo.";
        } else {
            return "Baja coincidencia: {$candidateName} no cumple con los requisitos principales de {$jobTitle}.";
        }
    }

    /**
     * Realiza un análisis batch de múltiples candidatos vs múltiples trabajos
     *
     * @param array $candidates Lista de candidatos
     * @param array $jobs Lista de trabajos
     * @param int $threshold Umbral mínimo
     * @return array Matriz de matching
     */
    public function batchAnalysis($candidates, $jobs, $threshold = 50)
    {
        $results = [];
        $startTime = microtime(true);

        foreach ($candidates as $candidateIndex => $candidate) {
            $candidateResults = [];

            foreach ($jobs as $jobIndex => $job) {
                try {
                    $match = $this->evaluateMatch($candidate, $job);

                    if ($match['match_percentage'] >= $threshold) {
                        $candidateResults[] = [
                          'job_index' => $jobIndex,
                          'job_title' => $job['title'] ?? $job['titulo'] ?? 'Job ' . $jobIndex,
                          'match' => $match
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip failed matches
                    continue;
                }
            }

            // Ordenar trabajos por mejor match para este candidato
            usort($candidateResults, function ($a, $b) {
                return $b['match']['match_percentage'] - $a['match']['match_percentage'];
            });

            $results[] = [
              'candidate_index' => $candidateIndex,
              'candidate_name' => $candidate['nombre'] ?? $candidate['name'] ?? 'Candidato ' . $candidateIndex,
              'matches' => $candidateResults,
              'best_match_percentage' => !empty($candidateResults) ? $candidateResults[0]['match']['match_percentage'] : 0
            ];
        }

        $processingTime = microtime(true) - $startTime;

        return [
          'results' => $results,
          'summary' => [
            'total_candidates' => count($candidates),
            'total_jobs' => count($jobs),
            'processing_time' => round($processingTime, 2),
            'threshold_used' => $threshold
          ]
        ];
    }
}
