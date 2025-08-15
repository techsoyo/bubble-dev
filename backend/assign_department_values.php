<?php
try {
  $pdo = new PDO('mysql:host=192.168.1.40;dbname=bubble_talents_DB', 'user', 'user123');

  echo "=== ASIGNACIÓN DE DEPARTMENT_ID Y DEPARTMENT_CATEGORY_ID ===\n";

  // 1. Verificar candidatos sin asignación de departamento
  $stmt = $pdo->query("SELECT COUNT(*) as total FROM bt_candidates WHERE department_id IS NULL OR department_category_id IS NULL");
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  echo "📊 Candidatos sin asignación de departamento: " . $result['total'] . "\n\n";

  if ($result['total'] == 0) {
    echo "✅ Todos los candidatos ya tienen departamento asignado\n";
    return;
  }

  // 2. Obtener candidatos que necesitan asignación
  $stmt = $pdo->query("
        SELECT id, name, hard_skills, soft_skills 
        FROM bt_candidates 
        WHERE department_id IS NULL OR department_category_id IS NULL
        ORDER BY created_at DESC
    ");

  $candidatesUpdated = 0;
  $candidatesProcessed = 0;

  echo "🔄 Procesando candidatos...\n";

  while ($candidate = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $candidatesProcessed++;
    $candidateId = $candidate['id'];
    $candidateName = $candidate['name'] ?: 'Sin nombre';

    echo "\n--- Procesando: $candidateName (ID: $candidateId) ---\n";

    // 3. Extraer skills del candidato
    $allSkills = [];

    if (!empty($candidate['hard_skills'])) {
      $hardSkills = json_decode($candidate['hard_skills'], true);
      if (is_array($hardSkills)) {
        $allSkills = array_merge($allSkills, $hardSkills);
      }
    }

    if (!empty($candidate['soft_skills'])) {
      $softSkills = json_decode($candidate['soft_skills'], true);
      if (is_array($softSkills)) {
        $allSkills = array_merge($allSkills, $softSkills);
      }
    }

    echo "🎯 Skills encontradas: " . implode(', ', array_slice($allSkills, 0, 5)) .
      (count($allSkills) > 5 ? '... (total: ' . count($allSkills) . ')' : '') . "\n";

    // 4. Mapear skills a departamento usando bt_skill_department_map
    $departmentId = null;
    $departmentCategoryId = null;

    if (!empty($allSkills)) {
      // Preparar skills para query
      $skillsLower = array_map('strtolower', $allSkills);
      $placeholders = implode(',', array_fill(0, count($skillsLower), '?'));

      $mapStmt = $pdo->prepare("
                SELECT department_id, department_category_id, skill, COUNT(*) as matches
                FROM bt_skill_department_map 
                WHERE LOWER(skill) IN ($placeholders)
                GROUP BY department_id, department_category_id
                ORDER BY matches DESC, department_id
                LIMIT 1
            ");
      $mapStmt->execute($skillsLower);
      $mapping = $mapStmt->fetch(PDO::FETCH_ASSOC);

      if ($mapping) {
        $departmentId = (int)$mapping['department_id'];
        $departmentCategoryId = (int)$mapping['department_category_id'];
        echo "✅ Departamento asignado por skills: Dept=$departmentId, Cat=$departmentCategoryId (matches: {$mapping['matches']})\n";
      }
    }

    // 5. Fallback: Asignación heurística si no hay mapping exacto
    if (!$departmentId && !empty($allSkills)) {
      $skillsText = implode(' ', array_map('strtolower', $allSkills));

      // Heurística básica
      if (
        strpos($skillsText, 'php') !== false || strpos($skillsText, 'node') !== false ||
        strpos($skillsText, 'backend') !== false || strpos($skillsText, 'api') !== false
      ) {
        $departmentId = 2; // Engineering
        $departmentCategoryId = 3; // Backend
        echo "🎯 Departamento asignado por heurística: Backend Development\n";
      } elseif (
        strpos($skillsText, 'react') !== false || strpos($skillsText, 'vue') !== false ||
        strpos($skillsText, 'frontend') !== false || strpos($skillsText, 'javascript') !== false
      ) {
        $departmentId = 2; // Engineering  
        $departmentCategoryId = 4; // Frontend
        echo "🎯 Departamento asignado por heurística: Frontend Development\n";
      } elseif (
        strpos($skillsText, 'marketing') !== false || strpos($skillsText, 'seo') !== false ||
        strpos($skillsText, 'social') !== false || strpos($skillsText, 'content') !== false
      ) {
        $departmentId = 4; // Marketing
        $departmentCategoryId = 8; // Digital Marketing (ajustar según tu mapping)
        echo "🎯 Departamento asignado por heurística: Marketing\n";
      } elseif (
        strpos($skillsText, 'design') !== false || strpos($skillsText, 'ux') !== false ||
        strpos($skillsText, 'ui') !== false || strpos($skillsText, 'creative') !== false
      ) {
        $departmentId = 2; // Engineering (o crear dept Design)
        $departmentCategoryId = 4; // Digital (ajustar)
        echo "🎯 Departamento asignado por heurística: Design\n";
      }
    }

    // 6. Fallback final: HR por defecto
    if (!$departmentId) {
      $departmentId = 6; // HR
      $departmentCategoryId = 1; // Operations (ajustar según tu estructura)
      echo "⚠️ Departamento por defecto: HR\n";
    }

    // 7. Actualizar candidato
    $updateStmt = $pdo->prepare("
            UPDATE bt_candidates 
            SET department_id = ?, department_category_id = ?
            WHERE id = ?
        ");

    if ($updateStmt->execute([$departmentId, $departmentCategoryId, $candidateId])) {
      $candidatesUpdated++;
      echo "✅ Candidato actualizado exitosamente\n";
    } else {
      echo "❌ Error al actualizar candidato\n";
    }
  }

  echo "\n=== RESUMEN DE ASIGNACIÓN ===\n";
  echo "📊 Candidatos procesados: $candidatesProcessed\n";
  echo "✅ Candidatos actualizados: $candidatesUpdated\n";

  // 8. Verificar resultado final
  $stmt = $pdo->query("
        SELECT 
            d.name as department_name,
            dc.name as category_name,
            COUNT(*) as candidates_count
        FROM bt_candidates c
        LEFT JOIN bt_departments d ON c.department_id = d.id
        LEFT JOIN bt_department_categories dc ON c.department_category_id = dc.id
        WHERE c.department_id IS NOT NULL
        GROUP BY c.department_id, c.department_category_id, d.name, dc.name
        ORDER BY candidates_count DESC
    ");

  echo "\n=== DISTRIBUCIÓN FINAL POR DEPARTAMENTO ===\n";
  while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo sprintf(
      "🏢 %s - %s: %d candidatos\n",
      $row['department_name'] ?: 'Unknown',
      $row['category_name'] ?: 'Unknown',
      $row['candidates_count']
    );
  }
} catch (Exception $e) {
  echo '❌ Error: ' . $e->getMessage() . "\n";
}
