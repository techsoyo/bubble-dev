<?php
// Ruta: backend/config/routes/pdf_routes.php

use Controllers\PDFController;

return [
    [
        'method' => 'POST',
        'pattern' => '/api/pdf/parse',
        'handler' => [PDFController::class, 'parse']
    ],
];
