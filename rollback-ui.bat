@echo off
echo ===================================================
echo ChandraKala Jewellers - Restoring UI from Backup
echo ===================================================
if not exist "%~dp0backup_ui_pre_opt" (
    echo Error: Backup directory backup_ui_pre_opt not found.
    pause
    exit /b 1
)

xcopy "%~dp0backup_ui_pre_opt\*" "%~dp0" /E /Y /I
echo.
echo Rollback completed successfully! Original UI files restored.
pause
