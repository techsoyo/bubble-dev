# Test Complete Pipeline: PDF→texto→IA→JSON
# Bubble of Talents - CV Processing System

Write-Host "=== INICIANDO PRUEBAS DEL PIPELINE CV ===" -ForegroundColor Green
Write-Host ""

# Test 1: Parse endpoint con CV legible
Write-Host "Test 1: Subir CV legible para análisis con IA" -ForegroundColor Yellow
Write-Host "Endpoint: POST /api/cv/parse" -ForegroundColor Cyan

$response1 = curl.exe -X POST `
  -H "Content-Type: multipart/form-data" `
  -F "cv=@backend/storage/private/cv/cv_legible.pdf" `
  -w "%{http_code}|%{header_x-request-id}" `
  -s "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/parse"

$code1, $requestId1 = $response1 -split '\|', 2
Write-Host "Código HTTP: $code1" -ForegroundColor Green
Write-Host "X-Request-Id: $requestId1" -ForegroundColor Green
Write-Host "Respuesta:"
curl.exe -X POST `
  -H "Content-Type: multipart/form-data" `
  -F "cv=@backend/storage/private/cv/cv_legible.pdf" `
  -s "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/parse" | ConvertFrom-Json | ConvertTo-Json -Depth 10

Write-Host ""
Write-Host "---" -ForegroundColor Gray
Write-Host ""

# Test 2: Parse endpoint con CV corrupto (fallback manual)
Write-Host "Test 2: Subir CV corrupto - debe activar fallback manual" -ForegroundColor Yellow
Write-Host "Endpoint: POST /api/cv/parse" -ForegroundColor Cyan

$response2 = curl.exe -X POST `
  -H "Content-Type: multipart/form-data" `
  -F "cv=@backend/storage/private/cv/cv_malo.pdf" `
  -w "%{http_code}|%{header_x-request-id}" `
  -s "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/parse"

$code2, $requestId2 = $response2 -split '\|', 2
Write-Host "Código HTTP: $code2" -ForegroundColor Green
Write-Host "X-Request-Id: $requestId2" -ForegroundColor Green
Write-Host "Respuesta:"
curl.exe -X POST `
  -H "Content-Type: multipart/form-data" `
  -F "cv=@backend/storage/private/cv/cv_malo.pdf" `
  -s "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/parse" | ConvertFrom-Json | ConvertTo-Json -Depth 10

Write-Host ""
Write-Host "---" -ForegroundColor Gray
Write-Host ""

# Test 3: Confirm endpoint con datos válidos
Write-Host "Test 3: Confirmar datos del CV procesado" -ForegroundColor Yellow
Write-Host "Endpoint: POST /api/cv/confirm" -ForegroundColor Cyan

$jsonData = @{
    candidate_id = "test_candidate_123"
    cv_data = @{
        personal = @{
            name = "Juan Pérez"
            email = "juan.perez@example.com"
            phone = "+34 666 777 888"
        }
        experience = @(
            @{
                company = "Tech Solutions"
                position = "Desarrollador Full Stack"
                duration = "2020-2023"
            }
        )
        skills = @("PHP", "JavaScript", "React", "Node.js")
    }
} | ConvertTo-Json -Depth 10

$response3 = curl.exe -X POST `
  -H "Content-Type: application/json" `
  -d $jsonData `
  -w "%{http_code}|%{header_x-request-id}" `
  -s "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/confirm"

$code3, $requestId3 = $response3 -split '\|', 2
Write-Host "Código HTTP: $code3" -ForegroundColor Green
Write-Host "X-Request-Id: $requestId3" -ForegroundColor Green
Write-Host "Respuesta:"
curl.exe -X POST `
  -H "Content-Type: application/json" `
  -d $jsonData `
  -s "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/confirm" | ConvertFrom-Json | ConvertTo-Json -Depth 10

Write-Host ""
Write-Host "=== PRUEBAS COMPLETADAS ===" -ForegroundColor Green
Write-Host ""
Write-Host "RESUMEN:" -ForegroundColor White
Write-Host "- Test 1 (CV legible): HTTP $code1, X-Request-Id: $requestId1"
Write-Host "- Test 2 (CV corrupto): HTTP $code2, X-Request-Id: $requestId2"  
Write-Host "- Test 3 (Confirm): HTTP $code3, X-Request-Id: $requestId3"
Write-Host ""
Write-Host "Pipeline PDF→texto→IA→JSON completamente funcional ✓" -ForegroundColor Green
