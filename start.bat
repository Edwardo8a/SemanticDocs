@echo off
title SemanticDocs - Entorno de Desarrollo Local
color 0A

echo =====================================================================
echo           SemanticDocs - Buscador Semantico de Documentos
echo =====================================================================
echo.

cd /d "%~dp0"

echo [1/3] Verificando e iniciando Ollama en segundo plano...
set "OLLAMA_MODELS=D:\02_Desarrollo_Resources\OllamaModels"
start /B "" "C:\Users\Eduardo8a\AppData\Local\Programs\Ollama\ollama.exe" serve >nul 2>&1
timeout /t 2 /nobreak >nul

echo [2/3] Iniciando procesador de colas en segundo plano (queue:listen)...
start "SemanticDocs - Cola de Procesamiento" cmd /k "color 0B && title SemanticDocs Queue Worker && cd /d %~dp0 && php artisan queue:listen --tries=3"

echo [3/3] Iniciando servidor web de Laravel en http://localhost:8001...
echo.
echo Presiona Ctrl+C para detener el servidor web.
echo =====================================================================
php artisan serve --port=8001
