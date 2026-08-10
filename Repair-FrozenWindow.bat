@echo off
rem ============================================================
rem  Repair-FrozenWindow.bat
rem  Repair-FrozenWindow.ps1 skriptini ishga tushiruvchi boshlovchi
rem ============================================================
title Windows 11 - Qotib qolgan oynani tuzatish
cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0Repair-FrozenWindow.ps1"
if errorlevel 1 (
    echo.
    echo [X] Skriptda xatolik yuz berdi.
    pause
)
