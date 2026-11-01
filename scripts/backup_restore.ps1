<#
.SYNOPSIS
    CYBERPOS 2.0 - LANEROS GAMER
    Script PowerShell de Respaldo y Restauracion de Base de Datos (Windows / XAMPP)

.DESCRIPTION
    Permite generar copias de seguridad de la base de datos 'cyberpos' con marca de tiempo,
    restaurar copias previas, inicializar el esquema DDL y poblar las semillas.

.PARAMETER Action
    Accion directa a ejecutar: 'backup', 'restore', 'schema', 'seeds', 'menu'.
    Por defecto: 'menu'.

.PARAMETER File
    Ruta del archivo SQL a restaurar cuando Action = 'restore'.

.EXAMPLE
    .\backup_restore.ps1 -Action backup
    .\backup_restore.ps1 -Action restore -File ..\backups\cyberpos_backup_2026-11-01.sql
#>

[CmdletBinding()]
param(
    [ValidateSet('menu', 'backup', 'restore', 'schema', 'seeds')]
    [string]$Action = 'menu',
    [string]$File = ''
)

$ErrorActionPreference = 'Stop'
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$RootDir   = Split-Path -Parent $ScriptDir
$BackupDir = Join-Path $RootDir "backups"

# Crear carpeta de respaldos si no existe
if (-not (Test-Path $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir | Out-Null
}

# Cargar variables desde .env si existe
$envFile = Join-Path $RootDir ".env"
$DbHost = "127.0.0.1"
$DbPort = "3306"
$DbName = "cyberpos"
$DbUser = "root"
$DbPass = ""

if (Test-Path $envFile) {
    Get-Content $envFile | ForEach-Object {
        $line = $_.Trim()
        if ($line -and -not $line.StartsWith("#") -and $line.Contains("=")) {
            $parts = $line.Split("=", 2)
            $k = $parts[0].Trim()
            $v = $parts[1].Trim().Trim('"').Trim("'")
            if ($k -eq "DB_HOST") { $DbHost = $v }
            if ($k -eq "DB_PORT") { $DbPort = $v }
            if ($k -eq "DB_NAME") { $DbName = $v }
            if ($k -eq "DB_USER") { $DbUser = $v }
            if ($k -eq "DB_PASS") { $DbPass = $v }
        }
    }
}

# Detectar binarios de MySQL (XAMPP o PATH)
$MysqlBin = ""
$DumpBin  = ""

if (Test-Path "C:\xampp\mysql\bin\mysqldump.exe") {
    $DumpBin  = "C:\xampp\mysql\bin\mysqldump.exe"
    $MysqlBin = "C:\xampp\mysql\bin\mysql.exe"
} else {
    $cmdMysql = Get-Command mysql -ErrorAction SilentlyContinue
    $cmdDump  = Get-Command mysqldump -ErrorAction SilentlyContinue
    if ($cmdMysql -and $cmdDump) {
        $MysqlBin = $cmdMysql.Source
        $DumpBin  = $cmdDump.Source
    } else {
        Write-Error "No se encontro 'mysql' ni 'mysqldump'. Verifique la instalacion de XAMPP en C:\xampp o agregue MySQL al PATH."
        exit 1
    }
}

function Build-AuthArgs {
    $argsList = @("-h", $DbHost, "-P", $DbPort, "-u", $DbUser)
    if ($DbPass) {
        $argsList += "-p$DbPass"
    }
    return $argsList
}

function Invoke-Backup {
    $timestamp = (Get-Date).ToString("yyyy-MM-dd_HH-mm-ss")
    $destFile  = Join-Path $BackupDir "cyberpos_backup_$timestamp.sql"

    Write-Host "`n[*] Iniciando respaldo de '$DbName'..." -ForegroundColor Cyan
    Write-Host "[*] Destino: $destFile" -ForegroundColor Gray

    $dumpArgs = (Build-AuthArgs) + @(
        "--routines",
        "--triggers",
        "--single-transaction",
        "--default-character-set=utf8mb4",
        $DbName
    )

    $process = Start-Process -FilePath $DumpBin -ArgumentList $dumpArgs -RedirectStandardOutput $destFile -NoNewWindow -Wait -PassThru
    if ($process.ExitCode -eq 0 -and (Test-Path $destFile) -and (Get-Item $destFile).Length -gt 0) {
        $sizeKB = [math]::Round((Get-Item $destFile).Length / 1KB, 2)
        Write-Host "[OK] Respaldo generado exitosamente ($sizeKB KB)." -ForegroundColor Green
        Write-Host "[*] Archivo: $destFile" -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Fallo la creacion del respaldo. Codigo: $($process.ExitCode)" -ForegroundColor Red
    }
}

function Invoke-RestoreFile([string]$sqlPath) {
    if (-not (Test-Path $sqlPath)) {
        $tryBackup = Join-Path $BackupDir $sqlPath
        if (Test-Path $tryBackup) {
            $sqlPath = $tryBackup
        } else {
            Write-Host "[ERROR] El archivo '$sqlPath' no existe." -ForegroundColor Red
            return
        }
    }

    Write-Host "`n[ADVERTENCIA] La restauracion sobreescribira los datos actuales en '$DbName'." -ForegroundColor Yellow
    $confirm = Read-Host "Desea continuar? (S/N)"
    if ($confirm -notmatch '^[sS]$') {
        Write-Host "[*] Operacion cancelada por el usuario." -ForegroundColor Gray
        return
    }

    Write-Host "[*] Restaurando desde: $sqlPath..." -ForegroundColor Cyan
    $mysqlArgs = (Build-AuthArgs) + @("--default-character-set=utf8mb4", $DbName)
    $process = Start-Process -FilePath $MysqlBin -ArgumentList $mysqlArgs -RedirectStandardInput $sqlPath -NoNewWindow -Wait -PassThru
    if ($process.ExitCode -eq 0) {
        Write-Host "[OK] Base de datos restaurada correctamente." -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Fallo la restauracion. Codigo: $($process.ExitCode)" -ForegroundColor Red
    }
}

function Invoke-Schema {
    $schemaPath = Join-Path $RootDir "database\schema.sql"
    Write-Host "`n[*] Aplicando esquema DDL limpio ($schemaPath)..." -ForegroundColor Cyan
    $mysqlArgs = (Build-AuthArgs) + @("--default-character-set=utf8mb4")
    $process = Start-Process -FilePath $MysqlBin -ArgumentList $mysqlArgs -RedirectStandardInput $schemaPath -NoNewWindow -Wait -PassThru
    if ($process.ExitCode -eq 0) {
        Write-Host "[OK] Esquema de base de datos aplicado correctamente." -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Fallo al aplicar el esquema. Codigo: $($process.ExitCode)" -ForegroundColor Red
    }
}

function Invoke-Seeds {
    $seedsPath = Join-Path $RootDir "database\seeds.sql"
    Write-Host "`n[*] Poblando datos semilla ($seedsPath)..." -ForegroundColor Cyan
    $mysqlArgs = (Build-AuthArgs) + @("--default-character-set=utf8mb4", $DbName)
    $process = Start-Process -FilePath $MysqlBin -ArgumentList $mysqlArgs -RedirectStandardInput $seedsPath -NoNewWindow -Wait -PassThru
    if ($process.ExitCode -eq 0) {
        Write-Host "[OK] Semillas de prueba cargadas correctamente." -ForegroundColor Green
    } else {
        Write-Host "[ERROR] Fallo al cargar semillas. Codigo: $($process.ExitCode)" -ForegroundColor Red
    }
}

# Despacho segun argumento o menu
switch ($Action) {
    'backup'  { Invoke-Backup; exit }
    'restore' {
        if (-not $File) {
            Write-Host "Debe especificar -File <ruta_archivo.sql>" -ForegroundColor Red
            exit 1
        }
        Invoke-RestoreFile -sqlPath $File
        exit
    }
    'schema'  { Invoke-Schema; exit }
    'seeds'   { Invoke-Seeds; exit }
    'menu' {
        while ($true) {
            Clear-Host
            Write-Host "=============================================================================" -ForegroundColor Blue
            Write-Host "   CYBERPOS 2.0 - GESTOR DE RESPALDO Y RESTAURACION (LANEROS GAMER)" -ForegroundColor Green
            Write-Host "=============================================================================" -ForegroundColor Blue
            Write-Host " [1] Generar Respaldo Completo (Backup con Fecha y Hora)"
            Write-Host " [2] Restaurar Base de Datos desde archivo .sql"
            Write-Host " [3] Inicializar con Esquema Limpio (database/schema.sql)"
            Write-Host " [4] Cargar Datos Semilla de Prueba (database/seeds.sql)"
            Write-Host " [5] Salir"
            Write-Host "=============================================================================" -ForegroundColor Blue
            $opt = Read-Host "Seleccione una opcion [1-5]"
            switch ($opt) {
                '1' { Invoke-Backup; Read-Host "Presione Enter para continuar..." }
                '2' {
                    Write-Host "`nRespaldos disponibles en: $BackupDir" -ForegroundColor Cyan
                    Get-ChildItem -Path $BackupDir -Filter "*.sql" | Select-Object Name, Length, LastWriteTime | Format-Table -AutoSize
                    $f = Read-Host "Ingrese el nombre o ruta del archivo .sql"
                    Invoke-RestoreFile -sqlPath $f
                    Read-Host "Presione Enter para continuar..."
                }
                '3' { Invoke-Schema; Read-Host "Presione Enter para continuar..." }
                '4' { Invoke-Seeds; Read-Host "Presione Enter para continuar..." }
                '5' { Write-Host "Hasta luego!" -ForegroundColor Green; exit }
                default { Write-Host "Opcion invalida." -ForegroundColor Red; Start-Sleep -Seconds 1 }
            }
        }
    }
}
