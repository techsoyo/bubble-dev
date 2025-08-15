#!/bin/bash

# Script de pruebas para CV Schema Backend
# Uso: ./test-cv-schema-backend.sh

BASE_URL="http://localhost/bubble_of_talents_1.0/backend/api"
ENDPOINT="$BASE_URL/cv-schema-test.php"

echo "=== PRUEBAS CV SCHEMA BACKEND ==="
echo "Endpoint: $ENDPOINT"
echo ""

# Test 1: Obtener template (GET)
echo "1. OBTENER TEMPLATE:"
echo "curl -X GET \"$ENDPOINT\""
echo ""
curl -X GET "$ENDPOINT" | jq '.'
echo ""
echo "---"
echo ""

# Test 2: Normalizar datos válidos (POST)
echo "2. NORMALIZAR DATOS VÁLIDOS:"
VALID_DATA='{
  "nombre": "Juan Pérez",
  "email": "juan.perez@example.com", 
  "telefono": "+34 666 777 888",
  "ubicacion_actual": "Madrid, España",
  "fecha_nacimiento": "1990-05-15",
  "portfolio": "https://juanperez.dev",
  "linkedin": "https://linkedin.com/in/juanperez",
  "otras_redes": ["https://github.com/juanperez"],
  "resumen_profesional": "Desarrollador Full Stack con 5 años de experiencia",
  "soft_skills": ["Comunicación", "Trabajo en equipo"],
  "hard_skills": ["JavaScript", "PHP", "React"],
  "data_source": "ai_processing",
  "puestos_anteriores": [{
    "puesto": "Desarrollador Senior",
    "empresa": "TechCorp", 
    "fecha_inicio": "2020-01-15",
    "fecha_fin": "2024-03-30",
    "descripcion": "Desarrollo de aplicaciones web",
    "responsabilidades": ["Liderazgo técnico", "Mentoring"],
    "ubicacion": "Madrid",
    "actual": false
  }]
}'

echo "curl -X POST \"$ENDPOINT\" -H \"Content-Type: application/json\" -d '$VALID_DATA'"
echo ""
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d "$VALID_DATA" | jq '.'
echo ""
echo "---"
echo ""

# Test 3: Normalizar datos inválidos (POST)
echo "3. NORMALIZAR DATOS INVÁLIDOS:"
INVALID_DATA='{
  "nombre": "",
  "email": "email-invalido",
  "telefono": "<script>alert(\"xss\")</script>",
  "fecha_nacimiento": "fecha-invalida",
  "portfolio": "url-invalida"
}'

echo "curl -X POST \"$ENDPOINT\" -H \"Content-Type: application/json\" -d '$INVALID_DATA'"
echo ""
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d "$INVALID_DATA" | jq '.'
echo ""
echo "---"
echo ""

# Test 4: JSON inválido (POST)
echo "4. JSON INVÁLIDO:"
echo "curl -X POST \"$ENDPOINT\" -H \"Content-Type: application/json\" -d '{invalid json'"
echo ""
curl -X POST "$ENDPOINT" \
  -H "Content-Type: application/json" \
  -d '{invalid json' | jq '.'
echo ""
echo "---"
echo ""

# Test 5: Método no permitido (PUT)
echo "5. MÉTODO NO PERMITIDO:"
echo "curl -X PUT \"$ENDPOINT\""
echo ""
curl -X PUT "$ENDPOINT" | jq '.'
echo ""

echo "=== PRUEBAS COMPLETADAS ==="
