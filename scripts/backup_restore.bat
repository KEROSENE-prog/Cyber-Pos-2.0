@echo off
REM =============================================================================
REM CYBERPOS 2.0 - LANEROS GAMER
REM Script de Respaldo y Restauracion de Base de Datos (Windows / XAMPP)
REM =============================================================================
setlocal enabledelayedexpansion

REM Configuracion por defecto
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_NAME=cyberpos"
set "DB_USER=root"
set "DB_PASS="
set "BACKUP_DIR=%~dp0..\backups"

REM Detectar ruta de binarios MySQL (XAMPP o PATH)
set "MYSQL_BIN="
if exist "C:\xampp\mysql\bin\mysqldump.exe" (
    set "MYSQL_BIN=C:\xampp\mysql\bin\"
) else (
    where mysqldump >nul 2>nul
    if %errorlevel% equ 0 (
        set "MYSQL_BIN="
    ) else (
        echo [ERROR] No se encontro mysqldump ni en C:\xampp\mysql\bin ni en PATH.
        pause
        exit /b 1
    )
)

REM Asegurar directorio de respaldos
if not exist "%BACKUP_DIR%" (
    mkdir "%BACKUP_DIR%"
)

REM Menu de opciones
cls
echo =============================================================================
echo   CYBERPOS 2.0 - GESTOR DE RESPALDO Y RESTAURACION (LANEROS GAMER)
echo =============================================================================
echo [1] Generar Respaldo Completo (Backup con Fecha y Hora)
echo [2] Restaurar Base de Datos desde un archivo .sql
echo [3] Inicializar con Esquema Limpio (database/schema.sql)
echo [4] Cargar Datos Semilla de Prueba (database/seeds.sql)
echo [5] Salir
echo =============================================================================
set /p "OPCION=Seleccione una opcion [1-5]: "

if "%OPCION%"=="1" goto BACKUP
if "%OPCION%"=="2" goto RESTORE
if "%OPCION%"=="3" goto SCHEMA
if "%OPCION%"=="4" goto SEEDS
if "%OPCION%"=="5" exit /b 0
goto MENU

:BACKUP
REM Generar timestamp en formato YYYY-MM-DD_HH-MM-SS
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value 2^>nul') do set "DT=%%I"
if not defined DT (
    set "DT=%DATE:~6,4%%DATE:~3,2%%DATE:~0,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%"
    set "DT=!DT: =0!"
) else (
    set "DT=!DT:~0,4!-!DT:~4,2!-!DT:~6,2!_!DT:~8,2!-!DT:~10,2!-!DT:~12,2!"
)

set "BACKUP_FILE=%BACKUP_DIR%\cyberpos_backup_%DT%.sql"

echo.
echo [*] Iniciando respaldo de la base de datos '%DB_NAME%'...
echo [*] Destino: %BACKUP_FILE%

if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysqldump.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --routines --triggers --single-transaction --default-character-set=utf8mb4 %DB_NAME% > "%BACKUP_FILE%"
) else (
    "%MYSQL_BIN%mysqldump.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --routines --triggers --single-transaction --default-character-set=utf8mb4 %DB_NAME% > "%BACKUP_FILE%"
)

if %errorlevel% equ 0 (
    echo [OK] Respaldo generado exitosamente.
    echo [*] Archivo guardado: %BACKUP_FILE%
) else (
    echo [ERROR] Ocurrio un fallo al generar el respaldo. Verifique que MySQL este en ejecucion.
)
echo.
pause
exit /b 0

:RESTORE
echo.
echo [*] Respaldos disponibles en %BACKUP_DIR%:
dir /b /o-d "%BACKUP_DIR%\*.sql" 2>nul
echo.
set /p "ARCHIVO=Ingrese la ruta completa o nombre del archivo .sql a restaurar: "

if not exist "%ARCHIVO%" (
    if exist "%BACKUP_DIR%\%ARCHIVO%" (
        set "ARCHIVO=%BACKUP_DIR%\%ARCHIVO%"
    ) else (
        echo [ERROR] El archivo '%ARCHIVO%' no existe.
        pause
        exit /b 1
    )
)

echo.
echo [ADVERTENCIA] La restauracion sobrescribira los datos actuales en '%DB_NAME%'.
set /p "CONFIRMA=Esta seguro de continuar? (S/N): "
if /i not "%CONFIRMA%"=="S" (
    echo [*] Operacion cancelada por el usuario.
    pause
    exit /b 0
)

echo [*] Restaurando base de datos desde: %ARCHIVO%...
if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --default-character-set=utf8mb4 %DB_NAME% < "%ARCHIVO%"
) else (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --default-character-set=utf8mb4 %DB_NAME% < "%ARCHIVO%"
)

if %errorlevel% equ 0 (
    echo [OK] Base de datos restaurada correctamente.
) else (
    echo [ERROR] Fallo en la restauracion. Verifique la conexion y el archivo SQL.
)
echo.
pause
exit /b 0

:SCHEMA
echo.
echo [*] Aplicando esquema DDL limpio (database/schema.sql)...
set "SCHEMA_FILE=%~dp0..\database\schema.sql"
if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --default-character-set=utf8mb4 < "%SCHEMA_FILE%"
) else (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --default-character-set=utf8mb4 < "%SCHEMA_FILE%"
)
if %errorlevel% equ 0 (
    echo [OK] Esquema de base de datos creado exitosamente.
) else (
    echo [ERROR] No se pudo aplicar el esquema.
)
echo.
pause
exit /b 0

:SEEDS
echo.
echo [*] Cargando semillas iniciales (database/seeds.sql)...
set "SEEDS_FILE=%~dp0..\database\seeds.sql"
if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --default-character-set=utf8mb4 %DB_NAME% < "%SEEDS_FILE%"
) else (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --default-character-set=utf8mb4 %DB_NAME% < "%SEEDS_FILE%"
)
if %errorlevel% equ 0 (
    echo [OK] Semillas de prueba cargadas exitosamente.
) else (
    echo [ERROR] No se pudieron cargar las semillas.
)
echo.
pause
exit /b 0

