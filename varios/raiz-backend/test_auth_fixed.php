<?php
if ((getenv('APP_ENV') ?: 'production') === 'production') {
  http_response_code(404);
  exit;
}
echo "test_auth_fixed deshabilitado en producción. Ejecuta solo en desarrollo.\n";
