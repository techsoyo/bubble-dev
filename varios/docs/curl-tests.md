# Comandos de prueba para CV Schema Backend

## 1. Obtener template (GET)
curl -X GET "http://localhost:8080/api/cv-schema-test.php"

## 2. Normalizar datos válidos (POST)
curl -X POST "http://localhost:8080/api/cv-schema-test.php" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "Juan Pérez",
    "email": "juan.perez@example.com",
    "telefono": "+34 666 777 888",
    "ubicacion_actual": "Madrid, España",
    "fecha_nacimiento": "1990-05-15",
    "portfolio": "https://juanperez.dev",
    "linkedin": "https://linkedin.com/in/juanperez",
    "otras_redes": ["https://github.com/juanperez"],
    "resumen_profesional": "Desarrollador Full Stack con 5 años de experiencia...",
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
    }],
    "educacion": [{
      "titulo": "Ingeniería Informática",
      "campo_estudio": "Informática",
      "institucion": "Universidad Politécnica",
      "fecha_inicio": "2015-09-01",
      "fecha_fin": "2019-06-30",
      "nivel_educativo": "Grado"
    }],
    "routing": {
      "categoria_departamento_id": 1,
      "departamento_id": 5,
      "reclutador_id": "rec_001",
      "fuente": "ai",
      "razon": "Perfil técnico sólido"
    }
  }'

## 3. Normalizar datos inválidos (POST)
curl -X POST "http://localhost:8080/api/cv-schema-test.php" \
  -H "Content-Type: application/json" \
  -d '{
    "nombre": "",
    "email": "email-invalido",
    "telefono": "<script>alert(\"xss\")</script>",
    "fecha_nacimiento": "fecha-invalida",
    "portfolio": "url-invalida"
  }'

## 4. JSON malformado (POST)
curl -X POST "http://localhost:8080/api/cv-schema-test.php" \
  -H "Content-Type: application/json" \
  -d '{invalid json'

## 5. Método no permitido (PUT)
curl -X PUT "http://localhost:8080/api/cv-schema-test.php"

## 6. Sin Content-Type (POST)
curl -X POST "http://localhost:8080/api/cv-schema-test.php" \
  -d '{"nombre": "Test"}'
