@echo off
title UZDUB ZIP yaratish
echo ========================================
echo   UZDUB — ZIP yaratilmoqda...
echo ========================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0_create_zip.ps1"

echo.
pause
