@echo off
chcp 65001 >nul
cd /d "%~dp0"
title UZDUB AI Server (port 11434)
echo ===============================================
echo   UZDUB AI server ishga tushyapti...
echo   Sayt uni oddiy Ollama deb qabul qiladi.
echo   To'xtatish uchun bu oynani yoping.
echo ===============================================
py -m pip install flask pymysql requests --quiet --disable-pip-version-check 2>nul

rem --- LOKAL NEYRON TARMOQ (Ollama, qwen3:8b) — 11435 da bo'lmasa ishga tushiramiz ---
where ollama >nul 2>nul
if %errorlevel%==0 (
  netstat -an 2>nul | findstr ":11435" | findstr "LISTENING" >nul
  if errorlevel 1 (
    echo [Ollama] 11435 da ishlamayapti - ishga tushirilmoqda...
    start "Ollama serve" cmd /k "ollama serve"
    timeout /t 6 >nul
  )
)

py -X utf8 ai_server.py
pause