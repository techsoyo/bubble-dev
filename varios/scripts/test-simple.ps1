# Test Individual Endpoints - CV Pipeline

Write-Host "=== TEST PARSE ENDPOINT ===" -ForegroundColor Green

# Test parse con CV válido
Write-Host "Probando parse.php con CV válido..." -ForegroundColor Yellow
curl.exe -X POST -H "Content-Type: multipart/form-data" -F "cv=@backend/storage/private/cv/cv_legible.pdf" "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/parse"

Write-Host ""
Write-Host "---" -ForegroundColor Gray
Write-Host ""

# Test parse con CV corrupto
Write-Host "Probando parse.php con CV corrupto (fallback manual)..." -ForegroundColor Yellow
curl.exe -X POST -H "Content-Type: multipart/form-data" -F "cv=@backend/storage/private/cv/cv_malo.pdf" "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/parse"

Write-Host ""
Write-Host "=== TEST CONFIRM ENDPOINT ===" -ForegroundColor Green

# Test confirm con datos válidos
Write-Host "Probando confirm.php con datos válidos..." -ForegroundColor Yellow
$jsonData = '{"candidate_id":"test_123","cv_data":{"personal":{"name":"Juan Pérez","email":"juan@test.com"},"experience":[{"company":"Tech Corp","position":"Developer"}],"skills":["PHP","JavaScript"]}}'

curl.exe -X POST -H "Content-Type: application/json" -d $jsonData "http://localhost/bubble_of_talents_1.0/backend/public/api/cv/confirm"

Write-Host ""
Write-Host "=== TESTS COMPLETADOS ===" -ForegroundColor Green
