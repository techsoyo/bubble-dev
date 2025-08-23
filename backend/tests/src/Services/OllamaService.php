<!-- <?php

// namespace Services;

// /**
//  * Servicio para interactuar con la API de Ollama
//  */
// class OllamaService
// {
//     /**
//      * URL base de la API de Ollama
//      * @var string
//      */
//     private $apiUrl;

//     /**
//      * Modelo de IA a utilizar
//      * @var string
//      */
//     private $model;

//     /**
//      * Constructor
//      */
//     public function __construct()
//     {
//         // Cargar configuración desde las variables de entorno
//         $this->apiUrl = getenv('OLLAMA_API_URL') ?: 'http://localhost:11434/api';
//         $this->model = getenv('OLLAMA_MODEL') ?: 'recruitment-ai';
//     }

//     /**
//      * Realiza una petición a la API de Ollama
//      *
//      * @param string $endpoint Endpoint de la API
//      * @param array $data Datos para enviar en la petición
//      * @return array Respuesta de la API
//      * @throws \Exception Si hay un error en la petición
//      */
//     private function request($endpoint, $data)
//     {
//         $url = $this->apiUrl . '/' . $endpoint;

//         // Inicializar cURL
//         $ch = curl_init($url);


//         // Configurar la petición
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_POST, true);
//         curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
//         curl_setopt($ch, CURLOPT_HTTPHEADER, [
//           'Content-Type: application/json'
//         ]);
//         // Timeout de 60 segundos para evitar cuelgues
//         curl_setopt($ch, CURLOPT_TIMEOUT, 60);
//         // Log de inicio de petición
//         error_log("[OllamaService] Llamando a $url con modelo: " . ($data['model'] ?? 'N/A'));

//         // Ejecutar la petición
//         $response = curl_exec($ch);
//         $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

//         // Manejar errores de cURL
//         if ($response === false) {
//             $curlError = curl_error($ch);
//             error_log("[OllamaService] cURL error: $curlError");
//             curl_close($ch);
//             throw new \Exception('Error en la petición a Ollama (cURL): ' . $curlError);
//         }

//         // Cerrar la conexión
//         curl_close($ch);

//         // Verificar si hubo un error HTTP
//         if ($statusCode !== 200) {
//             error_log("[OllamaService] HTTP error $statusCode: $response");
//             throw new \Exception('Error en la petición a Ollama: ' . $response);
//         }

//         // Decodificar la respuesta
//         $result = json_decode($response, true);

//         if (json_last_error() !== JSON_ERROR_NONE) {
//             error_log('[OllamaService] JSON decode error: ' . json_last_error_msg());
//             throw new \Exception('Error al decodificar la respuesta: ' . json_last_error_msg());
//         }

//         return $result;
//     }

//     /**
//      * Analiza un CV utilizando IA
//      *
//      * @param string $cvText Texto del CV a analizar
//      * @return array Información extraída del CV
//      * @throws \Exception Si hay un error en el análisis
//      */
//     public function analyzeCV($cvText)
//     {
//         // Prompt detallado para extracción avanzada y categorización
//         $data = [
//           'model' => $this->model,
//           'prompt' => "Analiza el siguiente CV en texto plano y extrae la información en formato JSON estructurado con los siguientes campos:\n\n" .
//             "{\n" .
//             "  \"nombre\": \"\",\n" .
//             "  \"email\": \"\",\n" .
//             "  \"telefono\": \"\",\n" .
//             "  \"ubicacion_actual\": \"\",\n" .
//             "  \"fecha_nacimiento\": \"\",\n" .
//             "  \"portfolio\": \"\",\n" .
//             "  \"linkedin\": \"\",\n" .
//             "  \"otras_redes\": [],\n" .
//             "  \"resumen_profesional\": \"\",\n" .
//             "  \"soft_skills\": [],\n" .
//             "  \"intereses\": [],\n" .
//             "  \"referencias\": [],\n" .
//             "  \"disponibilidad\": \"\",\n" .
//             "  \"puestos_anteriores\": [\n" .
//             "    {\n" .
//             "      \"puesto\": \"\",\n" .
//             "      \"empresa\": \"\",\n" .
//             "      \"fecha_inicio\": \"\",\n" .
//             "      \"fecha_fin\": \"\",\n" .
//             "      \"responsabilidades\": [],\n" .
//             "      \"logros\": []\n" .
//             "    }\n" .
//             "  ],\n" .
//             "  \"tecnologias_herramientas\": [],\n" .
//             "  \"idiomas\": [\n" .
//             "    {\n" .
//             "      \"idioma\": \"\",\n" .
//             "      \"nivel\": \"\"\n" .
//             "    }\n" .
//             "  ],\n" .
//             "  \"educacion\": [\n" .
//             "    {\n" .
//             "      \"grado\": \"\",\n" .
//             "      \"institucion\": \"\",\n" .
//             "      \"fecha_inicio\": \"\",\n" .
//             "      \"fecha_fin\": \"\"\n" .
//             "    }\n" .
//             "  ],\n" .
//             "  \"certificaciones\": [],\n" .
//             "  \"categoria\": \"\",\n" .
//             "  \"subcategoria\": \"\",\n" .
//             "  \"otros\": \"\"\n" .
//             "}\n\n" .
//             "Asocia el perfil a una de las siguientes categorías y subcategorías según la experiencia y habilidades detectadas:\n\n" .
//             "[\n" .
//             "  { categoria: 'Management', subcategorias: ['Account Manager', 'Account Director', 'Medical Strategist/Planner', 'Scientific Account Executive'] },\n" .
//             "  { categoria: 'Creativity (Art & Design)', subcategorias: ['Copywriter (health)', 'Art Director', 'Graphic Designer', 'Content Creator/Content Strategist'] },\n" .
//             "  { categoria: 'Digital & Technology', subcategorias: ['UX/UI Designer', 'Front-end/Web Developer', 'Mobile Developer (iOS/Android)', 'Digital Project Manager'] },\n" .
//             "  { categoria: 'Audiovisual & Production', subcategorias: ['Video Producer', 'Motion Graphics Specialist', 'Postproduction Editor'] },\n" .
//             "  { categoria: 'Events & Experiences', subcategorias: ['Event Manager', 'Production Coordinator', 'Experiential Marketing Specialist'] },\n" .
//             "  { categoria: 'Communication & PR', subcategorias: ['PR/Media Relations Specialist', 'Community Manager', 'Content Manager'] },\n" .
//             "  { categoria: 'Paid Media & Performance', subcategorias: ['Google Ads/Meta/TikTok Specialist', 'Email/CRM Marketing', 'Digital Analytics'] },\n" .
//             "  { categoria: 'Internships/Junior', subcategorias: ['Design Intern', 'Copy Intern', 'Production Intern', 'Strategy Intern', 'Digital Intern'] }\n" .
//             "]\n\n" .
//             "Texto del CV:\n---\n" . $cvText . "\n---",
//           'format' => 'json'
//         ];

//         $result = $this->request('generate', $data);

//         // Intentar extraer la parte JSON de la respuesta
//         $jsonStart = strpos($result['response'], '{');
//         $jsonEnd = strrpos($result['response'], '}');

//         if ($jsonStart !== false && $jsonEnd !== false) {
//             $jsonString = substr($result['response'], $jsonStart, $jsonEnd - $jsonStart + 1);
//             $parsedData = json_decode($jsonString, true);

//             if ($parsedData) {
//                 return $parsedData;
//             }
//         }

//         // Si no se pudo extraer JSON, retornar error controlado
//         throw new \RuntimeException('Respuesta del proveedor no contiene JSON válido');
//     }

//     /**
//      * Calcula el porcentaje de coincidencia entre un candidato y un trabajo
//      *
//      * @param int $candidateId ID del candidato
//      * @param int $jobId ID del trabajo
//      * @return array Detalles del matching
//      * @throws \Exception Si hay un error en el cálculo
//      */
//     public function calculateMatching($candidateId, $jobId)
//     {
//         // Obtener información de candidato y trabajo (implementación real pendiente)
//         $candidateInfo = $this->getCandidateInfo($candidateId);
//         $jobInfo = $this->getJobInfo($jobId);

//         // Prompt para calcular coincidencia
//         $prompt = 'Determina el porcentaje de coincidencia entre este candidato y esta oferta de trabajo. ' .
//           'Analiza las habilidades, experiencia, educación y requisitos. Devuelve un JSON con ' .
//           "porcentaje de coincidencia (0-100), fortalezas, debilidades y recomendaciones.\n\n" .
//           "Información del candidato:\n" . json_encode($candidateInfo, JSON_PRETTY_PRINT) . "\n\n" .
//           "Información del trabajo:\n" . json_encode($jobInfo, JSON_PRETTY_PRINT);

//         $data = [
//           'model' => $this->model,
//           'prompt' => $prompt,
//           'stream' => false,
//           'format' => 'json'
//         ];

//         $result = $this->request('generate', $data);

//         // Intentar extraer la parte JSON de la respuesta
//         $jsonStart = strpos($result['response'], '{');
//         $jsonEnd = strrpos($result['response'], '}');

//         if ($jsonStart !== false && $jsonEnd !== false) {
//             $jsonString = substr($result['response'], $jsonStart, $jsonEnd - $jsonStart + 1);
//             $matchingData = json_decode($jsonString, true);

//             if ($matchingData) {
//                 return $matchingData;
//             }
//         }

//         // Si no se pudo extraer JSON, retornar error controlado
//         throw new \RuntimeException('Respuesta del proveedor no contiene JSON válido');
//     }

//     /**
//      * Implementación del chatbot utilizando Ollama
//      *
//      * @param array $params Parámetros para el chat (messages, etc.)
//      * @return string Respuesta del chatbot
//      * @throws \Exception Si hay un error en la generación
//      */
//     public function chat($params)
//     {
//         $messages = $params['messages'] ?? [];

//         // Construir el prompt basado en los mensajes anteriores
//         $prompt = "Eres un asistente especializado en reclutamiento y recursos humanos.\n\n";

//         foreach ($messages as $message) {
//             $role = $message['role'];
//             $content = $message['content'];
//             $prompt .= ucfirst($role) . ': ' . $content . "\n";
//         }

//         $prompt .= 'Assistant: ';

//         $data = [
//           'model' => $this->model,
//           'prompt' => $prompt,
//           'stream' => false
//         ];

//         $result = $this->request('generate', $data);
//         return $result['response'];
//     }

//     /**
//      * Obtiene información de un candidato
//      *
//      * @param int $candidateId ID del candidato
//      * @return array Información del candidato
//      */
//     private function getCandidateInfo($candidateId)
//     {
//         throw new \RuntimeException('getCandidateInfo no implementado');
//     }

//     /**
//      * Obtiene información de un trabajo
//      *
//      * @param int $jobId ID del trabajo
//      * @return array Información del trabajo
//      */
//     private function getJobInfo($jobId)
//     {
//         throw new \RuntimeException('getJobInfo no implementado');
//     }
// }
