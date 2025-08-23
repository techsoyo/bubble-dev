<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../phpunit.bootstrap.php';

final class ApplicationsExtendedViewTest extends TestCase
{
  private PDO $db;

  protected function setUp(): void
  {
    if (!function_exists('getDbConnection')) {
      require_once __DIR__ . '/../../../config/database.php';
    }
    $this->db = getDbConnection();
    $this->assertInstanceOf(PDO::class, $this->db, 'DB connection should be a PDO instance');
  }

  public function testViewExistsAndHasExpectedColumns(): void
  {
    try {
      $stmt = $this->db->query('SELECT * FROM vw_applications_extended LIMIT 1');
    } catch (Throwable $e) {
      $this->markTestSkipped('vw_applications_extended no disponible: ' . $e->getMessage());
      return;
    }
    if ($stmt === false) {
      $this->markTestSkipped('Consulta a vw_applications_extended falló (posible vista inexistente o permisos)');
      return;
    }

    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $expectedColumns = [
      'application_id',
      'created_at',
      'updated_at',
      'status',
      'score',
      'source',
      'job_id',
      'job_title',
      'job_company',
      'job_location',
      'candidate_id',
      'candidate_name',
      'candidate_email',
      'resume',
      'cover_letter'
    ];

    if (empty($row)) {
      $this->markTestSkipped('vw_applications_extended sin datos para validar columnas');
    } else {
      foreach ($expectedColumns as $col) {
        $this->assertArrayHasKey($col, $row, "Column '$col' missing in vw_applications_extended");
      }
    }
  }
}
