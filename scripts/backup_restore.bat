@echo off
REM Script de respaldo y restauracion para la base de datos de CyberPOS (XAMPP)
setlocal enabledelayedexpansion

REM Variables de conexion
set "DB_HOST=127.0.0.1"
set "DB_PORT=3306"
set "DB_NAME=cyberpos"
set "DB_USER=root"
set "DB_PASS="
set "BACKUP_DIR=%~dp0..\backups"

REM Buscamos mysqldump en la ruta por defecto de XAMPP o en el PATH
set "MYSQL_BIN="
if exist "C:\xampp\mysql\bin\mysqldump.exe" (
    set "MYSQL_BIN=C:\xampp\mysql\bin\"
) else (
    where mysqldump >nul 2>nul
    if %errorlevel% equ 0 (
        set "MYSQL_BIN="
    ) else (
        echo [ERROR] No se encontro mysqldump. Revisa que XAMPP este en C:\xampp o en tu PATH.
        pause
        exit /b 1
    )
)

REM Creamos la carpeta de respaldos si todavia no existe
if not exist "%BACKUP_DIR%" (
    mkdir "%BACKUP_DIR%"
)

cls
echo =============================================================================
echo   CYBERPOS - GESTOR DE BASE DE DATOS (LANEROS GAMER)
echo =============================================================================
echo [1] Hacer Respaldo Completo (con fecha y hora)
echo [2] Restaurar una copia desde archivo .sql
echo [3] Limpiar y crear tablas de nuevo (schema.sql)
echo [4] Cargar datos de prueba iniciales (seeds.sql)
echo [5] Salir
echo =============================================================================
set /p "OPCION=Elige una opcion [1-5]: "

if "%OPCION%"=="1" goto BACKUP
if "%OPCION%"=="2" goto RESTORE
if "%OPCION%"=="3" goto SCHEMA
if "%OPCION%"=="4" goto SEEDS
if "%OPCION%"=="5" exit /b 0
goto MENU

:BACKUP
REM Obtenemos la fecha y hora para el nombre del archivo
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value 2^>nul') do set "DT=%%I"
if not defined DT (
    set "DT=%DATE:~6,4%%DATE:~3,2%%DATE:~0,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%"
    set "DT=!DT: =0!"
) else (
    set "DT=!DT:~0,4!-!DT:~4,2!-!DT:~6,2!_!DT:~8,2!-!DT:~10,2!-!DT:~12,2!"
)

set "BACKUP_FILE=%BACKUP_DIR%\cyberpos_backup_%DT%.sql"

echo.
echo [*] Guardando respaldo de '%DB_NAME%' en:
echo     %BACKUP_FILE%

if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysqldump.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --routines --triggers --single-transaction --default-character-set=utf8mb4 %DB_NAME% > "%BACKUP_FILE%"
) else (
    "%MYSQL_BIN%mysqldump.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --routines --triggers --single-transaction --default-character-set=utf8mb4 %DB_NAME% > "%BACKUP_FILE%"
)

if %errorlevel% equ 0 (
    echo [OK] Respaldo guardado con exito.
) else (
    echo [ERROR] No se pudo hacer el respaldo. Revisa que MySQL este encendido en XAMPP.
)
echo.
pause
exit /b 0

:RESTORE
echo.
echo [*] Copias de seguridad encontradas:
dir /b /o-d "%BACKUP_DIR%\*.sql" 2>nul
echo.
set /p "ARCHIVO=Escribe el nombre del archivo a restaurar: "

if not exist "%ARCHIVO%" (
    if exist "%BACKUP_DIR%\%ARCHIVO%" (
        set "ARCHIVO=%BACKUP_DIR%\%ARCHIVO%"
    ) else (
        echo [ERROR] No encontramos el archivo '%ARCHIVO%'.
        pause
        exit /b 1
    )
)

echo.
echo [AVISO] Esto reemplazara los datos actuales de '%DB_NAME%'.
set /p "CONFIRMA=Deseas continuar? (S/N): "
if /i not "%CONFIRMA%"=="S" (
    echo [*] Cancelado por el usuario.
    pause
    exit /b 0
)

echo [*] Restaurando datos...
if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --default-character-set=utf8mb4 %DB_NAME% < "%ARCHIVO%"
) else (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --default-character-set=utf8mb4 %DB_NAME% < "%ARCHIVO%"
)

if %errorlevel% equ 0 (
    echo [OK] Base de datos restaurada correctamente.
) else (
    echo [ERROR] Hubo un error al restaurar. Revisa la conexion a MySQL.
)
echo.
pause
exit /b 0

:SCHEMA
echo.
echo [*] Recreando la estructura de tablas (schema.sql)...
set "SCHEMA_FILE=%~dp0..\database\schema.sql"
if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --default-character-set=utf8mb4 < "%SCHEMA_FILE%"
) else (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --default-character-set=utf8mb4 < "%SCHEMA_FILE%"
)
if %errorlevel% equ 0 (
    echo [OK] Tablas creadas con exito.
) else (
    echo [ERROR] No se pudo ejecutar el schema.
)
echo.
pause
exit /b 0

:SEEDS
echo.
echo [*] Insertando datos de prueba (seeds.sql)...
set "SEEDS_FILE=%~dp0..\database\seeds.sql"
if "%DB_PASS%"=="" (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% --default-character-set=utf8mb4 %DB_NAME% < "%SEEDS_FILE%"
) else (
    "%MYSQL_BIN%mysql.exe" -h %DB_HOST% -P %DB_PORT% -u %DB_USER% -p%DB_PASS% --default-character-set=utf8mb4 %DB_NAME% < "%SEEDS_FILE%"
)
if %errorlevel% equ 0 (
    echo [OK] Datos de prueba cargados correctamente.
) else (
    echo [ERROR] Error al cargar los datos semilla.
)
echo.
pause
exit /b 0
