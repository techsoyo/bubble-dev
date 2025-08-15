# Script de pruebas para CV Schema Backend (Windows PowerShell)
# Uso: .\test-cv-schema-backend.ps1

$BASE_URL = "http://localhost/bubble_of_talents_1.0/backend/api"
$ENDPOINT = "$BASE_URL/cv-schema-test.php"

Write-Host "=== PRUEBAS CV SCHEMA BACKEND ===" -ForegroundColor Green
Write-Host "Endpoint: $ENDPOINT"
Write-Host ""

# Test 1: Obtener template (GET)
Write-Host "1. OBTENER TEMPLATE:" -ForegroundColor Yellow
Write-Host "Invoke-RestMethod -Uri '$ENDPOINT' -Method GET"
Write-Host ""
try {
  $response = Invoke-RestMethod -Uri $ENDPOINT -Method GET
  $response | ConvertTo-Json -Depth 10
}
catch {
  Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""
Write-Host "---"
Write-Host ""

# Test 2: Normalizar datos válidos (POST)
Write-Host "2. NORMALIZAR DATOS VÁLIDOS:" -ForegroundColor Yellow
$validData = @{
  nombre              = "Juan Pérez"
  email               = "juan.perez@example.com"
  telefono            = "+34 666 777 888"
  ubicacion_actual    = "Madrid, España"
  fecha_nacimiento    = "1990-05-15"
  portfolio           = "https://juanperez.dev"
  linkedin            = "https://linkedin.com/in/juanperez"
  otras_redes         = @("https://github.com/juanperez")
  resumen_profesional = "Desarrollador Full Stack con 5 años de experiencia"
  soft_skills         = @("Comunicación", "Trabajo en equipo")
  hard_skills         = @("JavaScript", "PHP", "React")
  data_source         = "ai_processing"
  puestos_anteriores  = @(
    @{
      puesto            = "Desarrollador Senior"
      empresa           = "TechCorp"
      fecha_inicio      = "2020-01-15"
      fecha_fin         = "2024-03-30"
      descripcion       = "Desarrollo de aplicaciones web"
      responsabilidades = @("Liderazgo técnico", "Mentoring")
      ubicacion         = "Madrid"
      actual            = $false
    }
  )
}

$validJson = $validData | ConvertTo-Json -Depth 10
Write-Host "Invoke-RestMethod -Uri '$ENDPOINT' -Method POST -Body (datos válidos) -ContentType 'application/json'"
Write-Host ""
try {
  $response = Invoke-RestMethod -Uri $ENDPOINT -Method POST -Body $validJson -ContentType "application/json"
  $response | ConvertTo-Json -Depth 10
}
catch {
  Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""
Write-Host "---"
Write-Host ""

# Test 3: Normalizar datos inválidos (POST)
Write-Host "3. NORMALIZAR DATOS INVÁLIDOS:" -ForegroundColor Yellow
$invalidData = @{
  nombre           = ""
  email            = "email-invalido"
  telefono         = "<script>alert('xss')</script>"
  fecha_nacimiento = "fecha-invalida"
  portfolio        = "url-invalida"
}

$invalidJson = $invalidData | ConvertTo-Json -Depth 10
Write-Host "Invoke-RestMethod -Uri '$ENDPOINT' -Method POST -Body (datos inválidos) -ContentType 'application/json'"
Write-Host ""
try {
  $response = Invoke-RestMethod -Uri $ENDPOINT -Method POST -Body $invalidJson -ContentType "application/json"
  $response | ConvertTo-Json -Depth 10
}
catch {
  Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""
Write-Host "---"
Write-Host ""

# Test 4: JSON inválido (POST)
Write-Host "4. JSON INVÁLIDO:" -ForegroundColor Yellow
Write-Host "Invoke-RestMethod -Uri '$ENDPOINT' -Method POST -Body '{invalid json' -ContentType 'application/json'"
Write-Host ""
try {
  $response = Invoke-RestMethod -Uri $ENDPOINT -Method POST -Body "{invalid json" -ContentType "application/json"
  $response | ConvertTo-Json -Depth 10
}
catch {
  Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""
Write-Host "---"
Write-Host ""

# Test 5: Método no permitido (PUT)
Write-Host "5. MÉTODO NO PERMITIDO:" -ForegroundColor Yellow
Write-Host "Invoke-RestMethod -Uri '$ENDPOINT' -Method PUT"
Write-Host ""
try {
  $response = Invoke-RestMethod -Uri $ENDPOINT -Method PUT
  $response | ConvertTo-Json -Depth 10
}
catch {
  Write-Host "Error: $($_.Exception.Message)" -ForegroundColor Red
}
Write-Host ""

Write-Host "=== PRUEBAS COMPLETADAS ===" -ForegroundColor Green
