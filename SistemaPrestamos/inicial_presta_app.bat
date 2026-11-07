@echo off
setlocal enabledelayedexpansion

REM ══════════════════════════════════════════════════════════════════
REM  PRESTA-APP — Launcher
REM  Arranca MariaDB portable + el servidor embebido de PHP, y abre
REM  el navegador. Todo corre dentro de esta misma carpeta, sin tocar
REM  nada instalado en el resto del sistema del usuario.
REM ══════════════════════════════════════════════════════════════════

set BASE_DIR=%~dp0
set PHP_DIR=%BASE_DIR%php
set MARIADB_DIR=%BASE_DIR%mariadb
set DATA_DIR=%BASE_DIR%data
set APP_DIR=%BASE_DIR%app
set MYSQL_PORT=3307
set PHP_PORT=8000

echo ============================================
echo   Iniciando PRESTA-APP...
echo ============================================

REM ── 1. Inicializar la base de datos la PRIMERA vez que se abre ──────
if not exist "%DATA_DIR%\mysql" (
    echo Primera vez: preparando la base de datos...
    "%MARIADB_DIR%\bin\mariadb-install-db.exe" --datadir="%DATA_DIR%" --auth-root-authentication-method=normal
)

REM ── 2. Arrancar MariaDB en segundo plano, en un puerto propio ───────
REM Usamos el puerto 3307 (no el 3306 por defecto) para no chocar con
REM ningún otro MySQL/XAMPP que el usuario pueda tener instalado.
echo Iniciando base de datos...
start "PrestaApp-MySQL" /min "%MARIADB_DIR%\bin\mysqld.exe" --datadir="%DATA_DIR%" --port=%MYSQL_PORT% --socket="%DATA_DIR%\mysql.sock"

REM Esperar unos segundos a que MariaDB termine de levantar
timeout /t 5 /nobreak >nul

REM ── 3. Arrancar el servidor embebido de PHP ──────────────────────────
echo Iniciando servidor web...
start "PrestaApp-PHP" /min "%PHP_DIR%\php.exe" -S localhost:%PHP_PORT% -t "%APP_DIR%"

REM Esperar un segundo a que PHP levante
timeout /t 2 /nobreak >nul

REM ── 4. Abrir el navegador ─────────────────────────────────────────────
REM Si install.php ya corrió antes (existe app\db\db.php), abre el
REM dashboard directo; si no, abre el instalador.
if exist "%APP_DIR%\db\db.php" (
    start "" "http://localhost:%PHP_PORT%/dashboard.php"
) else (
    start "" "http://localhost:%PHP_PORT%/install.php"
)

echo.
echo PRESTA-APP esta corriendo. NO CIERRES esta ventana mientras lo uses.
echo Para salir, cierra esta ventana o presiona una tecla.
echo.
pause >nul

REM ── 5. Al cerrar, apagar MariaDB y PHP limpiamente ───────────────────
echo Cerrando PRESTA-APP...
taskkill /FI "WINDOWTITLE eq PrestaApp-MySQL*" /T /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq PrestaApp-PHP*" /T /F >nul 2>&1