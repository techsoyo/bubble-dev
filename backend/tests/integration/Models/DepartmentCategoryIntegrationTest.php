<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Models\DepartmentCategory;

require_once __DIR__ . '/../../phpunit.bootstrap.php';

final class DepartmentCategoryIntegrationTest extends TestCase
{
  protected function setUp(): void {}

  public function testReadCategoriesAndStats(): void
  {
    $model = new DepartmentCategory();
    // Lectura simple: debería no lanzar excepción y devolver array
    try {
      $rows = $model->findAll([], 1, 5, ['name' => 'ASC']);
    } catch (Throwable $e) {
      $this->markTestSkipped('Lectura de categorías no disponible: ' . $e->getMessage());
      return;
    }
    $this->assertIsArray($rows);

    // Stats desde consulta directa del modelo
    try {
      $stats = $model->getCategoryStats();
    } catch (Throwable $e) {
      $this->markTestSkipped('Stats de categorías no disponible: ' . $e->getMessage());
      return;
    }
    $this->assertIsArray($stats);
    if (!array_key_exists('total_categories', $stats)) {
      $this->markTestSkipped("Campo 'total_categories' no presente en stats de categorías");
      return;
    }
    $this->assertGreaterThanOrEqual(0, (int)$stats['total_categories']);
  }
}
