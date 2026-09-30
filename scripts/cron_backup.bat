@echo off
REM ==============================================================================
REM Batch Wrapper para Automatización de Respaldos en Servidores Windows
REM Compatible con el Programador de Tareas (Task Scheduler) de Windows
REM ==============================================================================

set SCRIPT_DIR=%~dp0
set PROJECT_ROOT=%SCRIPT_DIR%..

echo [%DATE% %TIME%] Ejecutando respaldo automatizado de base de datos...
php "%PROJECT_ROOT%\scripts\backup_database.php"
exit /b %ERRORLEVEL%
