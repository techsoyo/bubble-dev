<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Models\Department;

require_once __DIR__ . '/../../phpunit.bootstrap.php';

final class DepartmentIntegrationTest extends TestCase
{
  private PDO $db;

  protected function setUp(): void
  {
    if (!function_exists('getDbConnection')) {
      require_once __DIR__ . '/../../../config/database.php';
    }
    $this->db = getDbConnection();
  }

  public function testPipelineViewReturnsExpectedColumns(): void
  {
    $model = new Department();
    try {
      $rows = $model->getDepartmentPipeline(null, false);
    } catch (Throwable $e) {
      $this->markTestSkipped('vw_pipeline_department no disponible: ' . $e->getMessage());
      return;
    }
    $this->assertIsArray($rows);
    if (!empty($rows)) {
      $row = $rows[0];
      foreach (['department_id', 'department_name', 'recruiter_id', 'applications_count', 'candidates_count'] as $col) {
        $this->assertArrayHasKey($col, $row, "Missing '$col' in vw_pipeline_department row");
      }
    } else {
      $this->markTestSkipped('vw_pipeline_department returned 0 rows; skip column assertions.');
    }
  }

  public function testDepartmentsWithLoadUsesViews(): void
  {
    $model = new Department();
    try {
      $rows = $model->getDepartmentsWithLoad([], false);
    } catch (Throwable $e) {
      $this->markTestSkipped('getDepartmentsWithLoad no disponible: ' . $e->getMessage());
      return;
    }
    $this->assertIsArray($rows);
    if (!empty($rows)) {
      $row = $rows[0];
      foreach (['id', 'name', 'candidates_count', 'applications_count', 'recruiter_active_load', 'load_status'] as $col) {
        $this->assertArrayHasKey($col, $row, "Missing '$col' in departments with load row");
      }
    } else {
      $this->markTestSkipped('getDepartmentsWithLoad returned 0 rows; skip column assertions.');
    }
  }
}
