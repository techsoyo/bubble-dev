<?php

namespace Controllers;

use Services\CVParsingService;
use Services\JobMatchingService;
// use Services\OllamaService;
use Utils\Request;

/**
 * Controlador para las funcionalidades de IA
 */
class AIController extends BaseController
{
    /**
     * Analizar un CV desde archivo .txt usando IA avanzada (Ollama)
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function parseCVFromFile(Request $request)
    {
        error_log('INICIO parseCVFromFile');
        $filename = $request->input('filename');

        if (!$filename) {
            error_log('parseCVFromFile: FALTA filename');
            $this->error('Se requiere el nombre del archivo .txt');
            error_log('FIN parseCVFromFile');
            return;
        }


        $filePath = __DIR__ . '/../../uploads/textos/' . basename($filename);
        if (!file_exists($filePath)) {
            error_log("parseCVFromFile: ARCHIVO NO EXISTE $filename");
            $this->error('El archivo no existe: ' . $filename);
            error_log('FIN parseCVFromFile');
            return;
        }


        $cvText = file_get_contents($filePath);

        try {
            error_log('parseCVFromFile: INICIO analyzeCV');
            // $result = $this->ollamaService->analyzeCV($cvText);
            error_log('parseCVFromFile: analyzeCV FINALIZADO');

            // Guardar el resultado en JSON para trazabilidad
            $jsonPath = __DIR__ . '/../../uploads/json/' . pathinfo($filename, PATHINFO_FILENAME) . '.json';
            file_put_contents($jsonPath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            $this->success('CV analizado correctamente', $result);
            error_log('FIN parseCVFromFile');
        } catch (\Exception $e) {
            error_log('parseCVFromFile: ERROR ' . $e->getMessage());
            $this->error('Error al analizar el CV: ' . $e->getMessage());
            error_log('FIN parseCVFromFile');
        }
    }
    // private $ollamaService;
    private $cvParsingService;
    private $jobMatchingService;

    /**
     * Constructor
     */
    public function __construct()
    {
        // parent::__construct();
        // $this->ollamaService = new OllamaService();
        $this->cvParsingService = new CVParsingService();
        $this->jobMatchingService = new JobMatchingService();
    }

    /**
     * Analizar un CV con IA
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function parseCV(Request $request)
    {
        // Verificar si se envió texto
        $cvText = $request->input('cv_text');

        // Para un MVP, simplificamos y solo procesamos texto
        if (!$cvText) {
            $this->error('Se requiere el texto del CV');
            return;
        }

        try {
            // Procesar el CV usando el servicio
            $result = $this->cvParsingService->parseCV($cvText);

            // Extraer habilidades específicamente
            $skills = $this->cvParsingService->extractSkills($cvText);

            // Combinar resultados
            $combinedResult = [
              'parsed_data' => $result,
              'skills' => $skills
            ];

            $this->success('CV analizado correctamente', $combinedResult);
        } catch (\Exception $e) {
            $this->error('Error al analizar el CV: ' . $e->getMessage());
        }
    }

    /**
     * Calcular el matching entre un candidato y un trabajo
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function calculateMatching(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
          'candidate_id' => 'required|numeric',
          'job_id' => 'required|numeric'
        ]);

        if (!$data) {
            return;
        }

        try {
            // Placeholder temporal: datos mínimos para prueba controlada
            $candidateData = [
              'id' => $data['candidate_id'],
              'name' => 'Candidato ' . $data['candidate_id']
            ];

            $jobData = [
              'id' => $data['job_id'],
              'title' => 'Trabajo ' . $data['job_id']
            ];

            // Calcular el matching usando el servicio
            $result = $this->jobMatchingService->evaluateMatch(
                $candidateData,
                $jobData
            );

            // Generar explicación detallada
            $explanation = $this->jobMatchingService->explainMatching(
                $candidateData,
                $jobData
            );

            $result['explanation'] = $explanation;

            $this->success('Matching calculado correctamente', $result);
        } catch (\Exception $e) {
            $this->error('Error al calcular el matching: ' . $e->getMessage());
        }
    }

    /**
     * Chatbot de IA
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function chatbot(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
          'message' => 'required'
        ]);

        if (!$data) {
            return;
        }

        // Obtener mensajes anteriores si existen
        $previousMessages = $request->input('previous_messages', []);

        try {
            // Procesar el mensaje
            // $response = $this->ollamaService->chat([
              'messages' => array_merge($previousMessages, [
                ['role' => 'user', 'content' => $data['message']]
              ])
            ]);

            $this->success('Mensaje procesado correctamente', [
              'response' => $response,
              'messages' => array_merge($previousMessages, [
                ['role' => 'user', 'content' => $data['message']],
                ['role' => 'assistant', 'content' => $response]
              ])
            ]);
        } catch (\Exception $e) {
            $this->error('Error en el chatbot: ' . $e->getMessage());
        }
    }

    /**
     * Análisis avanzado de personalidad (método de ejemplo para futuras extensiones)
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function analyzePersonality(Request $request)
    {
        $this->error('Esta funcionalidad será implementada en una versión futura', null, 501);
    }

    /**
     * Predicción de rendimiento (método de ejemplo para futuras extensiones)
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function predictPerformance(Request $request)
    {
        $this->error('Esta funcionalidad será implementada en una versión futura', null, 501);
    }
}
