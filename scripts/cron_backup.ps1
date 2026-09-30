<#
.SYNOPSIS
    Script de Automatización de Respaldos de Base de Datos para Servidor Windows.
.DESCRIPTION
    Ejecuta el script PHP de respaldo y registra la salida en logs/cron_backup.log.
    Configurar en el Programador de Tareas de Windows (Task Scheduler) para ejecución diaria:
    powershell.exe -ExecutionPolicy Bypass -File "C:\ruta\ProyectoT3\scripts\cron_backup.ps1"
#>

$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectRoot = Split-Path -Parent $ScriptDir
$LogDir = Join-Path $ProjectRoot "storage\logs"
if (-not (Test-Path $LogDir)) {
    New-Item -ItemType Directory -Path $LogDir -Force | Out-Null
}
$LogFile = Join-Path $LogDir "cron_backup.log"

$Timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
"[$Timestamp] Iniciando tarea automatizada de respaldo de base de datos en servidor Windows..." | Out-File -FilePath $LogFile -Append -Encoding utf8

$PhpBin = "php"
$BackupScript = Join-Path $ProjectRoot "scripts\backup_database.php"

try {
    & $PhpBin $BackupScript 2>&1 | Out-File -FilePath $LogFile -Append -Encoding utf8
    if ($LASTEXITCODE -eq 0) {
        "[$Timestamp] Tarea automatizada completada con éxito." | Out-File -FilePath $LogFile -Append -Encoding utf8
    } else {
        "[$Timestamp] ERROR: Falló la tarea de respaldo con código de salida $LASTEXITCODE." | Out-File -FilePath $LogFile -Append -Encoding utf8
    }
} catch {
    "[$Timestamp] EXCEPCIÓN: $($_.Exception.Message)" | Out-File -FilePath $LogFile -Append -Encoding utf8
}
