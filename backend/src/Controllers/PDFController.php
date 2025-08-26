<?php declare(strict_types=1);
namespace Controllers;

use Utils\Request;
use Utils\ResponseHelper;

class PDFController extends BaseController
{
    /**
     * POST /api/pdf/parse
     */
    public function parse(Request $request, array $params = [])
    {
        try {
            $file = $request->file('pdf') ?? $request->file('file');
            if (!$file) {
                return ResponseHelper::fail("Archivo PDF no proporcionado (campo 'pdf' o 'file')", 400);
            }

            // TODO: parsear PDF
            return ResponseHelper::success('PDF procesado', [
                'filename' => $file['name'] ?? null,
                'size'     => $file['size'] ?? null
            ]);
        } catch (\Throwable $e) {
            return ResponseHelper::error('Error procesando PDF', $e);
        }
    }

    // Opcionales, por si los usas en otro sitio:
    public function generate(Request $request, array $params = [])
    {
        $data = $request->all();
        return ResponseHelper::success('PDF generado', ['input' => $data]);
    }

    public function download(Request $request, array $params = [])
    {
        $id = $params['id'] ?? null;
        if (!$id) return ResponseHelper::fail('PDF id requerido', 400);
        return ResponseHelper::success("Descarga PDF $id", ['url' => "/storage/pdfs/$id.pdf"]);
    }
}
