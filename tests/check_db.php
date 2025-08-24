<?php

/**
 * Verifica conexión MySQL y existencia de tablas clave y registros.
 * Ajusta $conf y $tables según tu esquema.
 */

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

$conf = [
  'host' => '192.168.1.40',
  'user' => 'user',
  'pass' => 'user123',
  'db'   => 'bubble_talents_DB'   // <-- AJUSTA NOMBRE DB
];

$tables = [
 'bt_application_notes'   => 0,      
 'bt_applications'        => 0,      
 'bt_candidate_certifications'  => 0,
'bt_candidate_education'       => 0,
 'bt_candidate_experiences'     => 0,
 'bt_candidate_languages'     => 0,  
 'bt_candidate_projects'      => 0,  
 'bt_candidate_references'    => 0,  
 'bt_candidate_routing'     => 0,    
 'bt_candidate_skill_map'   => 0,    
 'bt_candidate_skills'      => 0,    
 'bt_candidates'            => 0,    
 'bt_chatbot_analytics'     => 0,    
 'bt_chatbot_nodes'        => 0,     
 'bt_chatbot_options'       => 0,    
 'bt_culture'               => 0,    
 'bt_department_categories'  => 0,   
'bt_departments'             => 0,  
 'bt_interviews'             => 0,   
 'bt_job_benefits'          => 0,    
 'bt_job_requirements'     => 0,     
 'bt_job_skill_map'       => 0,      
 'bt_job_skills'       => 0,         
 'bt_jobs'           => 0,          
 'bt_news'            => 0,          
 'bt_notification_preferences'  => 0,
 'bt_notifications'           => 0,  
 'bt_password_reset_tokens'    => 0, 
 'bt_skill_aliases'             => 0,
 'bt_skill_department_map'   => 0,   
 'bt_skills'               => 0,     
 'bt_social_logins'        => 0,    
 'bt_staff_profiles'      => 0,     
 'bt_staff_sessions'    => 0
];

$mysqli = @new mysqli($conf['host'], $conf['user'], $conf['pass'], $conf['db']);
if ($mysqli->connect_error) {
  fwrite(STDERR, "FAIL Conexión MySQL: " . $mysqli->connect_error . PHP_EOL);
  exit(1);
}
echo "OK Conexión MySQL\n";

// Tablas
$fail = false;
foreach ($tables as $t => $min) {
  $res = $mysqli->query("SHOW TABLES LIKE '$t'");
  if (!$res || $res->num_rows === 0) {
    echo "[FAIL] Tabla '$t' NO existe\n";
    $fail = true;
    continue;
  }
  $cntRes = $mysqli->query("SELECT COUNT(*) AS c FROM `$t`");
  if ($cntRes) {
    $row = $cntRes->fetch_assoc();
    $c = (int)$row['c'];
    $okRows = ($c >= $min);
    echo sprintf("[%s] %s: %d filas (min %d)\n", $okRows ? 'OK' : 'WARN', $t, $c, $min);
  } else {
    echo "[FAIL] No se pudo contar filas en '$t'\n";
    $fail = true;
  }
}
$mysqli->close();

exit($fail ? 1 : 0);
