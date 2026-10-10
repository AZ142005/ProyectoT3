<#
.SYNOPSIS
    Registra las tareas programadas diarias de mantenimiento en el Programador de Tareas de Windows.
.DESCRIPTION
    Crea (o reemplaza con /F) cuatro tareas diarias:
      - Condominio-Backup   23:30  -> scripts\cron_backup.ps1 (respaldo y rotación de la base de datos)
      - Condominio-Tokens   02:00  -> scripts\cleanup_tokens.php (tokens expirados y notificaciones)
      - Condominio-Logs     02:15  -> scripts\cleanup_logs.php (purga y rotación de logs)
      - Condominio-Uploads  02:30  -> scripts\cleanup_uploads.php (uploads huérfanos)
    Tras registrarlas activa StartWhenAvailable: si la PC estaba apagada o suspendida
    a la hora programada, la tarea se ejecuta apenas vuelva a estar disponible.
    Uso:
      powershell -NoProfile -ExecutionPolicy Bypass -File scripts\setup_scheduled_tasks.ps1 [-WhatIf]
.PARAMETER WhatIf
    Simula la ejecución: muestra los comandos schtasks planificados sin registrar ninguna tarea.
#>

param(
    [switch]$WhatIf
)

$ErrorActionPreference = 'Stop'

# Raíz del proyecto: este script vive en <root>\scripts
$root = Split-Path -Parent $PSScriptRoot

# Resolver el binario PHP (PATH primero, fallback XAMPP)
$php = (Get-Command php -ErrorAction SilentlyContinue).Source
if (-not $php) {
    $php = 'C:\xampp\php\php.exe'
}
if (-not (Test-Path $php)) {
    throw "No se encontró el binario PHP. Instale PHP o ajuste la ruta C:\xampp\php\php.exe en este script."
}

# Validar que existan los scripts a programar
$scriptsRequeridos = @(
    (Join-Path $root 'scripts\cleanup_tokens.php'),
    (Join-Path $root 'scripts\cleanup_logs.php'),
    (Join-Path $root 'scripts\cleanup_uploads.php'),
    (Join-Path $root 'scripts\cron_backup.ps1')
)
foreach ($script in $scriptsRequeridos) {
    if (-not (Test-Path $script)) {
        throw "No se encontró el script requerido: $script"
    }
}

# Comandos /TR (el ejecutable y la ruta del script van entre comillas dobles)
$trBackup  = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -File "' + (Join-Path $root 'scripts\cron_backup.ps1') + '"'
$trTokens  = '"' + $php + '" "' + (Join-Path $root 'scripts\cleanup_tokens.php') + '"'
$trLogs    = '"' + $php + '" "' + (Join-Path $root 'scripts\cleanup_logs.php') + '"'
$trUploads = '"' + $php + '" "' + (Join-Path $root 'scripts\cleanup_uploads.php') + '"'

# Tareas diarias: nombre, hora y comando
$tareas = @(
    @{ Nombre = 'Condominio-Backup';  Hora = '23:30'; Tr = $trBackup },
    @{ Nombre = 'Condominio-Tokens';  Hora = '02:00'; Tr = $trTokens },
    @{ Nombre = 'Condominio-Logs';    Hora = '02:15'; Tr = $trLogs },
    @{ Nombre = 'Condominio-Uploads'; Hora = '02:30'; Tr = $trUploads }
)

if ($WhatIf) {
    Write-Host "SIMULACIÓN (-WhatIf): no se registrará ninguna tarea."
    Write-Host ""
    foreach ($tarea in $tareas) {
        Write-Host ('schtasks /Create /F /TN "{0}" /SC DAILY /ST {1} /TR {2}' -f $tarea.Nombre, $tarea.Hora, $tarea.Tr)
    }
    Write-Host ""
    Write-Host "Total planificado: $($tareas.Count) tarea(s) diaria(s). PHP: $php"
    exit 0
}

# Registrar cada tarea (se reemplaza si ya existe gracias a /F)
foreach ($tarea in $tareas) {
    Write-Host "Registrando tarea $($tarea.Nombre) ($($tarea.Hora))..."
    # Windows PowerShell 5.1 no escapa las comillas internas al invocar procesos nativos;
    # se escapan con \" para que schtasks reciba el /TR completo.
    $trArgs = $tarea.Tr
    if ($PSVersionTable.PSEdition -eq 'Desktop') {
        $trArgs = $trArgs -replace '"', '\"'
    }
    & schtasks /Create /F /TN $tarea.Nombre /SC DAILY /ST $tarea.Hora /TR $trArgs
    if ($LASTEXITCODE -ne 0) {
        throw "schtasks /Create falló para $($tarea.Nombre) (código de salida $LASTEXITCODE)."
    }
}

# Ajustes post-registro: ejecutar corridas omitidas (PC apagada/suspendida) y con batería.
# Nota: el objeto de settings CIM expone las propiedades invertidas
# (DisallowStartIfOnBatteries / StopIfGoingOnBatteries); se asignan solo si existen.
foreach ($tarea in $tareas) {
    try {
        $st = Get-ScheduledTask -TaskName $tarea.Nombre -ErrorAction Stop
        $settings = $st.Settings
        $settings.StartWhenAvailable = $true
        if ($settings.PSObject.Properties.Name -contains 'DisallowStartIfOnBatteries') {
            $settings.DisallowStartIfOnBatteries = $false
        }
        if ($settings.PSObject.Properties.Name -contains 'StopIfGoingOnBatteries') {
            $settings.StopIfGoingOnBatteries = $false
        }
        Set-ScheduledTask -TaskName $tarea.Nombre -Settings $settings | Out-Null
    } catch {
        Write-Warning "No se pudieron ajustar los settings de $($tarea.Nombre): $($_.Exception.Message)"
    }
}

# Confirmar cada tarea con schtasks /Query
foreach ($tarea in $tareas) {
    & schtasks /Query /TN $tarea.Nombre | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "schtasks /Query no encontró la tarea $($tarea.Nombre)."
    }
    Write-Host "  OK: $($tarea.Nombre) registrada y visible en schtasks."
}

Write-Host ""
Write-Host "Resumen: $($tareas.Count) tareas diarias registradas (PHP: $php)."
