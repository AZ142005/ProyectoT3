#!/usr/bin/env bash
# ==============================================================================
# Script de Automatización de Respaldos de Base de Datos (Servidor Linux/Unix)
#
# Configurar en Crontab del servidor (ejemplo: ejecución diaria a las 3:00 AM):
#   crontab -e
#   0 3 * * * /bin/bash /ruta/al/proyecto/scripts/cron_backup.sh >> /ruta/al/proyecto/storage/logs/cron_backup.log 2>&1
# ==============================================================================

set -euo pipefail

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
PROJECT_ROOT="$( dirname "$SCRIPT_DIR" )"
PHP_BIN="$(which php || echo "/usr/bin/php")"

echo "[$(date '+%Y-%m-%d %H:%M:%S')] Iniciando tarea automatizada de respaldo de base de datos..."

if [ ! -x "$PHP_BIN" ] && ! command -v php >/dev/null 2>&1; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: Binario de PHP no encontrado en el servidor." >&2
    exit 1
fi

"$PHP_BIN" "$PROJECT_ROOT/scripts/backup_database.php"
EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Tarea automatizada finalizada con éxito."
else
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: La tarea de respaldo finalizó con código de salida $EXIT_CODE." >&2
    exit $EXIT_CODE
fi
