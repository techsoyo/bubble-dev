<?php

namespace Controllers;

use Models\Job;
use Utils\Request;

/**
 * Controlador para la gestión de trabajos
 */
class JobController extends BaseController
{
    private $jobModel;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->jobModel = new Job();
    }

    /**
     * Obtener todos los trabajos
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function getAll(Request $request)
    {
        // Parámetros de filtrado opcionales
        $filters = [
            'category' => $request->getParams()['category'] ?? null,
            'location' => $request->getParams()['location'] ?? null,
            'type' => $request->getParams()['type'] ?? null,
            'search' => $request->getParams()['search'] ?? null
        ];

        // Parámetros de paginación opcionales
        $page = isset($request->getParams()['page']) ? (int)$request->getParams()['page'] : 1;
        $limit = isset($request->getParams()['limit']) ? (int)$request->getParams()['limit'] : 10;

        // Obtener trabajos filtrados y paginados
        $jobs = $this->jobModel->findAll($filters, $page, $limit);
        $total = $this->jobModel->countAll($filters);

        $this->success('Trabajos obtenidos correctamente', [
            'jobs' => $jobs,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Obtener un trabajo por su ID
     *
     * @param Request $request Objeto de solicitud
     * @param array $params Parámetros de la ruta
     * @return void
     */
    public function getById(Request $request, $params)
    {
        if (!isset($params['id'])) {
            $this->error('ID de trabajo no proporcionado', null, 400);
            return;
        }

        $job = $this->jobModel->findById($params['id']);

        if (!$job) {
            $this->error('Trabajo no encontrado', null, 404);
            return;
        }

        $this->success('Trabajo obtenido correctamente', $job);
    }

    /**
     * Crear un nuevo trabajo
     *
     * @param Request $request Objeto de solicitud
     * @return void
     */
    public function store(Request $request)
    {
        // Validar datos de entrada
        $data = $this->validate($request, [
            'title' => 'required',
            'description' => 'required',
            'location' => 'required',
            'type' => 'required',
            'category' => 'required',
            'company_id' => 'required|numeric'
        ]);

        if (!$data) {
            return;
        }

        // Añadir fecha de publicación
        $data['date_posted'] = date('Y-m-d H:i:s');

        // Añadir usuario que crea el trabajo
        $userData = $request->getUser();
        $data['created_by'] = $userData['sub'];

        // Crear trabajo
        $jobId = $this->jobModel->store($data);

        if (!$jobId) {
            $this->error('Error al crear el trabajo');
            return;
        }

        // Obtener el trabajo creado
        $job = $this->jobModel->findById($jobId);

        $this->success('Trabajo creado correctamente', $job, 201);
    }

    /**
     * Actualizar un trabajo existente
     *
     * @param Request $request Objeto de solicitud
     * @param array $params Parámetros de la ruta
     * @return void
     */
    public function update(Request $request, $params)
    {
        if (!isset($params['id'])) {
            $this->error('ID de trabajo no proporcionado', null, 400);
            return;
        }

        // Validar datos de entrada
        $data = $this->validate($request, [
            'title' => 'required',
            'description' => 'required',
            'location' => 'required',
            'type' => 'required',
            'category' => 'required'
        ]);

        if (!$data) {
            return;
        }

        // Verificar que el trabajo existe
        $job = $this->jobModel->findById($params['id']);

        if (!$job) {
            $this->error('Trabajo no encontrado', null, 404);
            return;
        }

        // Verificar que el usuario tiene permisos para actualizar el trabajo
        $userData = $request->getUser();
        if ($userData['role'] !== 'admin' && $job['created_by'] !== $userData['sub']) {
            $this->error('No tienes permisos para actualizar este trabajo', null, 403);
            return;
        }

        // Actualizar trabajo
        $updated = $this->jobModel->update($params['id'], $data);

        if (!$updated) {
            $this->error('Error al actualizar el trabajo');
            return;
        }

        // Obtener el trabajo actualizado
        $job = $this->jobModel->findById($params['id']);

        $this->success('Trabajo actualizado correctamente', $job);
    }

    /**
     * Eliminar un trabajo
     *
     * @param Request $request Objeto de solicitud
     * @param array $params Parámetros de la ruta
     * @return void
     */
    public function delete(Request $request, $params)
    {
        if (!isset($params['id'])) {
            $this->error('ID de trabajo no proporcionado', null, 400);
            return;
        }

        // Verificar que el trabajo existe
        $job = $this->jobModel->findById($params['id']);

        if (!$job) {
            $this->error('Trabajo no encontrado', null, 404);
            return;
        }

        // Verificar que el usuario tiene permisos para eliminar el trabajo
        $userData = $request->getUser();
        if ($userData['role'] !== 'admin' && $job['created_by'] !== $userData['sub']) {
            $this->error('No tienes permisos para eliminar este trabajo', null, 403);
            return;
        }

        // Eliminar trabajo
        $deleted = $this->jobModel->delete($params['id']);

        if (!$deleted) {
            $this->error('Error al eliminar el trabajo');
            return;
        }

        $this->success('Trabajo eliminado correctamente');
    }
}
