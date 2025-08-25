#!/bin/bash
# =============================================
# Script bash para aplicar semilla de job_requirements y job_skills
# Fecha: 25 de agosto de 2025
# =============================================

HOST="localhost"
DATABASE="bubble_talents"
USERNAME="root"
PASSWORD=""

echo "🌱 Aplicando semilla para job_requirements y job_skills..."

SEED_FILE="backend/database/seeds/job_requirements_skills_seed.sql"

if [ ! -f "$SEED_FILE" ]; then
    echo "❌ Error: No se encuentra el archivo de semilla: $SEED_FILE"
    exit 1
fi

echo "📊 Conectando a base de datos: $DATABASE@$HOST"
echo "🔄 Ejecutando semilla..."

if [ -z "$PASSWORD" ]; then
    mysql -h"$HOST" -u"$USERNAME" "$DATABASE" < "$SEED_FILE"
else
    mysql -h"$HOST" -u"$USERNAME" -p"$PASSWORD" "$DATABASE" < "$SEED_FILE"
fi

if [ $? -eq 0 ]; then
    echo "✅ Semilla aplicada exitosamente!"
    echo ""
    echo "📋 Próximos pasos:"
    echo "1. Actualizar job_requirements.php para usar datos de BD"
    echo "2. Actualizar job_skills.php para usar datos de BD"
    echo "3. Remover arrays mockData hardcodeados"
else
    echo "❌ Error al aplicar la semilla"
    exit 1
fi

echo ""
echo "🎯 Para verificar los datos insertados, ejecuta:"
echo "   SELECT * FROM bt_job_requirements WHERE job_id = 1;"
echo "   SELECT * FROM bt_job_skills WHERE job_id = 1;"
