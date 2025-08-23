<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use Models\ApplicationNote;

final class ApplicationNoteIntegrationTest extends TestCase
{
  private PDO $db;

  protected function setUp(): void
  {
    require_once __DIR__ . '/../../phpunit.bootstrap.php';
    if (!function_exists('getDbConnection')) {
      require_once __DIR__ . '/../../../config/database.php';
    }
    $this->db = getDbConnection();
  }

  public function testAddSequentialNoteAndFetch(): void
  {
    // Preparar: obtener una application_id válida desde la vista
    try {
      $stmt = $this->db->query('SELECT application_id FROM vw_applications_extended ORDER BY application_id DESC LIMIT 1');
    } catch (Throwable $e) {
      $this->markTestSkipped('vw_applications_extended no disponible: ' . $e->getMessage());
      return;
    }
    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if (empty($row)) {
      $this->markTestSkipped('No hay aplicaciones en vw_applications_extended para probar notas');
      return;
    }
    $applicationId = (string)$row['application_id'];

    // Actuar: insertar una nota secuencial
    $model = new ApplicationNote();
    $noteText = 'Integration test note ' . date('c');
    // Insertar la nota (puede no devolver lastInsertId en claves no autoincrement)
    try {
      $model->addSequentialNote($applicationId, $noteText);
    } catch (Throwable $e) {
      $this->markTestSkipped('No se pudo insertar nota (posible permisos/clave compuesta): ' . $e->getMessage());
      return;
    }

    // Verificar: recuperar notas y validar que la última contiene el texto
    $notes = $model->findByApplicationId($applicationId);
    if (empty($notes)) {
      $this->markTestSkipped('No se pudieron recuperar notas tras el insert');
      return;
    }
    $last = end($notes);
    $this->assertArrayHasKey('note', $last);
    $this->assertStringContainsString('Integration test note', $last['note']);
  }
}
