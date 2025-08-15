<?php

namespace Controllers;

use Models\Candidate;
use Services\CVParsingService;
use Services\FileService;
use Utils\Request;

/**
 * Controlador para la gestión de candidatos
 */
class CandidateController extends BaseController
{
    private $candidateModel;
    private $cvParsingService;
    private $fileService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->candidateModel = new Candidate();
        $this->cvParsingService = new CVParsingService();
        $this->fileService = new FileService();
    }

    /**
     * Obtener todos los candidatos
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function getAll(Request $request)
    {
        // Parámetros de filtrado opcionales
        $filters = [
          'skill' => $request->getParams()['skill'] ?? null,
          'location' => $request->getParams()['location'] ?? null,
          'experience' => $request->getParams()['experience'] ?? null,
          'search' => $request->getParams()['search'] ?? null
        ];

        // Parámetros de paginación opcionales
        $page = isset($request->getParams()['page']) ? (int)$request->getParams()['page'] : 1;
        $limit = isset($request->getParams()['limit']) ? (int)$request->getParams()['limit'] : 10;

        // Verificar permisos (solo admin y reclutadores pueden ver todos los candidatos)
        $userData = $request->getUser();
        if (!in_array($userData['role'], ['admin', 'recruiter'])) {
            $this->error('No tienes permisos para acceder a esta información', null, 403);
            return;
        }

        // Obtener candidatos filtrados y paginados
        $candidates = $this->candidateModel->findAll($filters, $page, $limit);
        $total = $this->candidateModel->countAll($filters);

        $this->success('Candidatos obtenidos correctamente', [
          'candidates' => $candidates,
          'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
          ]
        ]);
    }

    /**
     * Obtener un candidato por su ID
     *
     * @param Request $request Objeto de solicitud
     * @param array $params Parámetros de la ruta
     * @return void
     */
    public function getById(Request $request, $params)
    {
        if (!isset($params['id'])) {
            $this->error('ID de candidato no proporcionado', null, 400);
            return;
        }

        $candidate = $this->candidateModel->findById($params['id']);

        if (!$candidate) {
            $this->error('Candidato no encontrado', null, 404);
            return;
        }

        // Verificar permisos (solo admin, reclutadores o el propio candidato pueden ver los detalles)
        $userData = $request->getUser();
        if (!in_array($userData['role'], ['admin', 'recruiter']) && $userData['sub'] !== $candidate['user_id']) {
            $this->error('No tienes permisos para acceder a esta información', null, 403);
            return;
        }

        $this->success('Candidato obtenido correctamente', $candidate);
    }

    /**
     * Crear un nuevo candidato
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function create(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
          'user_id' => 'required|numeric',
          'full_name' => 'required',
          'email' => 'required|email',
          'phone' => 'required',
          'location' => 'required',
          'skills' => 'required'
        ]);

        if (!$data) {
            return;
        }

        // Convertir skills de string a array si es necesario
        if (is_string($data['skills'])) {
            $data['skills'] = json_encode(explode(',', $data['skills']));
        } elseif (is_array($data['skills'])) {
            $data['skills'] = json_encode($data['skills']);
        }

        // Verificar permisos (solo admin, reclutadores o el propio usuario pueden crear su perfil)
        $userData = $request->getUser();
        if (!in_array($userData['role'], ['admin', 'recruiter']) && $userData['sub'] !== $data['user_id']) {
            $this->error('No tienes permisos para crear este perfil', null, 403);
            return;
        }

        // Procesar CV si se ha subido
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $cvUploadDir = __DIR__ . '/../../../uploads/cvs';

            $cvPath = $this->fileService->uploadFile($_FILES['cv'], $cvUploadDir);

            if ($cvPath) {
                $data['cv_path'] = $cvPath;

                // Procesar CV para extracción de texto (sin IA obligatoria)
                try {
                    $result = $this->cvParsingService->processCV($cvPath);

                    if ($result['success']) {
                        // Guardar referencia al archivo de texto
                        $data['cv_text_file'] = $result['text_file'];

                        // Extraer habilidades básicas del texto
                        if (!empty($result['extracted_text'])) {
                            $basicSkills = $this->cvParsingService->extractBasicSkills($result['extracted_text']);
                            if (!empty($basicSkills)) {
                                // Combinar con skills existentes
                                $existingSkills = !empty($data['skills']) ? json_decode($data['skills'], true) : [];
                                if (!is_array($existingSkills)) {
                                    $existingSkills = [];
                                }

                                $allSkills = array_unique(array_merge($existingSkills, $basicSkills));
                                $data['skills'] = json_encode($allSkills);
                            }
                        }
                    }

                    // Log para debug
                    error_log('CV procesado correctamente: ' . ($result['success'] ? 'éxito' : 'error'));
                } catch (\Exception $e) {
                    // Solo registrar el error pero continuar sin IA
                    error_log('Error procesando CV (sin IA): ' . $e->getMessage());
                }
            }
        }

        // Crear candidato
        $candidateId = $this->candidateModel->create($data);

        if (!$candidateId) {
            $this->error('Error al crear el candidato');
            return;
        }

        // Obtener el candidato creado
        $candidate = $this->candidateModel->findById($candidateId);

        $this->success('Candidato creado correctamente', $candidate, 201);
    }

    /**
     * Actualizar un candidato existente
     *
     * @param Request $request Objeto de solicitud
     * @param array $params Parámetros de la ruta
     * @return void
     */
    public function update(Request $request, $params)
    {
        if (!isset($params['id'])) {
            $this->error('ID de candidato no proporcionado', null, 400);
            return;
        }

        // Validar datos de entrada
        $data = $this->validate($request, [
          'full_name' => 'required',
          'email' => 'required|email',
          'phone' => 'required',
          'location' => 'required'
        ]);

        if (!$data) {
            return;
        }

        // Convertir skills de string a array si es necesario
        if (isset($data['skills'])) {
            if (is_string($data['skills'])) {
                $data['skills'] = json_encode(explode(',', $data['skills']));
            } elseif (is_array($data['skills'])) {
                $data['skills'] = json_encode($data['skills']);
            }
        }

        // Verificar que el candidato existe
        $candidate = $this->candidateModel->findById($params['id']);

        if (!$candidate) {
            $this->error('Candidato no encontrado', null, 404);
            return;
        }

        // Verificar permisos (solo admin, reclutadores o el propio candidato pueden actualizar el perfil)
        $userData = $request->getUser();
        if (!in_array($userData['role'], ['admin', 'recruiter']) && $userData['sub'] !== $candidate['user_id']) {
            $this->error('No tienes permisos para actualizar este perfil', null, 403);
            return;
        }

        // Procesar CV si se ha subido
        if (isset($_FILES['cv']) && $_FILES['cv']['error'] === UPLOAD_ERR_OK) {
            $cvUploadDir = __DIR__ . '/../../uploads/cvs';

            $cvPath = $this->fileService->uploadFile($_FILES['cv'], $cvUploadDir);

            if ($cvPath) {
                $data['cv_path'] = $cvPath;

                // Procesar CV para extracción de texto (sin IA obligatoria)
                try {
                    $result = $this->cvParsingService->processCV($cvPath);

                    if ($result['success']) {
                        // Guardar referencia al archivo de texto
                        $data['cv_text_file'] = $result['text_file'];

                        // Extraer habilidades básicas del texto
                        if (!empty($result['extracted_text'])) {
                            $basicSkills = $this->cvParsingService->extractBasicSkills($result['extracted_text']);
                            if (!empty($basicSkills)) {
                                // Combinar con skills existentes
                                $existingSkills = !empty($data['skills']) ? json_decode($data['skills'], true) : [];
                                if (!is_array($existingSkills)) {
                                    $existingSkills = [];
                                }

                                $allSkills = array_unique(array_merge($existingSkills, $basicSkills));
                                $data['skills'] = json_encode($allSkills);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Solo registrar el error pero continuar sin IA
                    error_log('Error procesando CV (sin IA): ' . $e->getMessage());
                }
            }
        }

        // Actualizar candidato
        $updated = $this->candidateModel->update($params['id'], $data);

        if (!$updated) {
            $this->error('Error al actualizar el candidato');
            return;
        }

        // Obtener el candidato actualizado
        $candidate = $this->candidateModel->findById($params['id']);

        $this->success('Candidato actualizado correctamente', $candidate);
    }

    /**
     * Eliminar un candidato
     *
     * @param Request $request Objeto de solicitud
     * @param array $params Parámetros de la ruta
     * @return void
     */
    public function delete(Request $request, $params)
    {
        if (!isset($params['id'])) {
            $this->error('ID de candidato no proporcionado', null, 400);
            return;
        }

        // Verificar que el candidato existe
        $candidate = $this->candidateModel->findById($params['id']);

        if (!$candidate) {
            $this->error('Candidato no encontrado', null, 404);
            return;
        }

        // Verificar permisos (solo admin, reclutadores o el propio candidato pueden eliminar el perfil)
        $userData = $request->getUser();
        if (!in_array($userData['role'], ['admin', 'recruiter']) && $userData['sub'] !== $candidate['user_id']) {
            $this->error('No tienes permisos para eliminar este perfil', null, 403);
            return;
        }

        // Eliminar candidato
        $deleted = $this->candidateModel->delete($params['id']);

        if (!$deleted) {
            $this->error('Error al eliminar el candidato');
            return;
        }

        $this->success('Candidato eliminado correctamente');
    }
}
