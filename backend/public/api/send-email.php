<?php


require_once __DIR__ . '/./bootstrap.php';
JWTMiddleware::requireAuth(); // cookie HttpOnly obligatoria

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    CsrfMiddleware::protect(); // double-submit cookie
}

if (($_ENV['APP_ENV'] ?? 'production') === 'production' && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized (cookie required)']);
    exit;
}

// cookie HttpOnly obligatoria

// Proteger solo mÃ©todos que cambian estado
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST','PUT','PATCH','DELETE'], true)) {
    // double-submit cookie
}

// En producciÃ³n NO aceptar Authorization header (solo cookie)
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized (cookie required)']);
        exit;
    }
}

// ORIGINAL CODE BELOW
/**
 * Email Sending Endpoint
 * Handles email sending using PHPMailer
 * 
 * Endpoint: POST /api/send-email
 * 
 * @author Bubble of Talents Development Team
 * @version 1.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ALLOWED_ORIGINS'] ?? 'http://localhost:5173'));
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
  ]);
  exit();
}

require_once '../../autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

try {
  // Get JSON input
  $input = json_decode(file_get_contents('php://input'), true);

  if (!$input) {
    throw new PHPMailerException('Invalid JSON input');
  }

  // Validate required fields
  $required_fields = ['to', 'subject', 'body'];
  foreach ($required_fields as $field) {
    if (empty($input[$field])) {
      throw new PHPMailerException("Missing required field: {$field}");
    }
  }

  $to = filter_var($input['to'], FILTER_VALIDATE_EMAIL);
  if (!$to) {
    throw new PHPMailerException('Invalid email address');
  }

  $subject = trim($input['subject']);
  $body = $input['body'];
  $isHtml = isset($input['isHtml']) ? (bool)$input['isHtml'] : false;

  // Create PHPMailer instance
  $mail = new PHPMailer(true);

  // Server settings
  $mail->isSMTP();
  $mail->Host = $_ENV['MAIL_HOST'] ?? 'localhost';
  $mail->SMTPAuth = true;
  $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
  $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = intval($_ENV['MAIL_PORT'] ?? 587);

  // Recipients
  $mail->setFrom(
    $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@bubble-talents.com',
    $_ENV['MAIL_FROM_NAME'] ?? 'Bubble of Talents'
  );
  $mail->addAddress($to);
  $mail->addReplyTo(
    $_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@bubble-talents.com',
    $_ENV['MAIL_FROM_NAME'] ?? 'Bubble of Talents'
  );

  // Content
  $mail->isHTML($isHtml);
  $mail->Subject = $subject;
  $mail->Body = $body;

  if ($isHtml) {
    // Create plain text version from HTML
    $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $body));
  }

  // Send email
  $mail->send();

  // Log successful email (optional - for monitoring)
  error_log("Email sent successfully to: {$to}, Subject: {$subject}");

  echo json_encode([
    'success' => true,
    'message' => 'Email sent successfully'
  ]);
} catch (PHPMailerException $e) {
  // Log error
  error_log("Email sending failed: " . $e->getMessage());

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Failed to send email: ' . $e->getMessage()
  ]);
} catch (Error $e) {
  // Log error
  error_log("Email sending error: " . $e->getMessage());

  http_response_code(500);
  echo json_encode([
    'success' => false,
    'message' => 'Server error occurred while sending email'
  ]);
}


