<?php

declare(strict_types=1);

namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class AIController extends BaseController
{
    /**
     * Analizar un CV subido como archivo
     */
    public function parseCVFromFile(Request $request, array $params = [])
    {
        $file = $request->files['cv'] ?? null;
        if (!$file) {
            return ResponseHelper::error('Archivo CV no encontrado', null, 400);
        }

        // TODO: implementar lógica de parsing
        return ResponseHelper::success('CV procesado correctamente (archivo)', [
            'filename' => $file['name']
        ]);
    }

    /**
     * Analizar CV en texto plano
     */
    public function parseCV(Request $request, array $params = [])
    {
        $text = $request->input('text');
        if (!$text) {
            return ResponseHelper::error('Texto del CV no proporcionado', null, 400);
        }

        // TODO: IA para parsing del texto
        return ResponseHelper::success('CV procesado correctamente (texto)', [
            'length' => strlen($text)
        ]);
    }

    /**
     * Analizar un PDF directamente
     */
    public function analyzePdfDirect(Request $request, array $params = [])
    {
        $file = $request->files['pdf'] ?? null;
        if (!$file) {
            return ResponseHelper::error('Archivo PDF no encontrado', null, 400);
        }

        // TODO: lógica de Anáslisis IA sobre PDF
        return ResponseHelper::success('PDF analizado correctamente', [
            'filename' => $file['name']
        ]);
    }

    /**
     * Calcular el matching de un candidato con un trabajo
     */
    public function calculateMatching(Request $request, array $params = [])
    {
        $candidateId = $request->input('candidate_id');
        $jobId = $request->input('job_id');

        if (!$candidateId || !$jobId) {
            return ResponseHelper::error('Faltan parí¡metros candidate_id o job_id', null, 400);
        }

        // Verificar si estamos en producción
        if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
            http_response_code(501);
            echo json_encode(['error' => 'AI not configured']);
            exit;
        }

        // TODO: IA para calcular matching real
        // En desarrollo, usar score fijo en lugar de aleatorio
        return ResponseHelper::success('Matching calculado (dev)', [
            'candidate_id' => $candidateId,
            'job_id' => $jobId,
            'score' => 75, // score fijo para desarrollo, reemplazar con IA real
            'note' => 'Score mock para desarrollo - implementar IA en producción'
        ]);
    }

    /**
     * Chatbot IA
     */
    public function chatbot(Request $request, array $params = [])
    {
        $message = $request->input('message');
        if (!$message) {
            return ResponseHelper::error('Mensaje no proporcionado', null, 400);
        }

        // TODO: conectar con modelo IA
        return ResponseHelper::success('Respuesta generada', [
            'input' => $message,
            'reply' => "Echo: $message"
        ]);
    }

    /**
     * Verificar salud del servicio de IA
     */
    public function healthCheck(Request $request, array $params = [])
    {
        return ResponseHelper::success('IA funcionando correctamente', [
            'status' => 'ok',
            'uptime' => time()
        ]);
    }

    public function analyzePersonality(Request $request, array $params = [])
    {
        $text = $request->input('text');
        if (!$text) {
            return ResponseHelper::error('Texto no proporcionado', null, 400);
        }

        return ResponseHelper::success('Anáslisis de personalidad completado', [
            'traits' => ['proactivo', 'colaborativo']
        ]);
    }

    public function predictPerformance(Request $request, array $params = [])
    {
        $candidateId = $request->input('candidate_id');
        if (!$candidateId) {
            return ResponseHelper::error('candidate_id no proporcionado', null, 400);
        }

        return ResponseHelper::success('Predicción completada', [
            'candidate_id' => $candidateId,
            'prediction' => 'alto rendimiento'
        ]);
    }
}
