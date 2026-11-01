#!/usr/bin/env bash
# =============================================================================
# CYBERPOS 2.0 - LANEROS GAMER
# Script de Respaldo y Restauración de Base de Datos (Linux / macOS / Git Bash)
# =============================================================================

set -e

# Colores para salida en terminal
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directorio base del proyecto
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/backups"

# Cargar variables de .env si existe
ENV_FILE="${ROOT_DIR}/.env"
if [ -f "$ENV_FILE" ]; then
    export $(grep -v '^#' "$ENV_FILE" | xargs -d '\n' 2>/dev/null || true)
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-cyberpos}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

# Asegurar directorio de respaldos
mkdir -p "$BACKUP_DIR"

# Construir argumentos comunes de autenticación MySQL
MYSQL_AUTH="-h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER}"
if [ -n "$DB_PASS" ]; then
    MYSQL_AUTH="${MYSQL_AUTH} -p${DB_PASS}"
fi

mostrar_menu() {
    clear
    echo -e "${BLUE}=============================================================================${NC}"
    echo -e "${GREEN}   CYBERPOS 2.0 - GESTOR DE RESPALDO Y RESTAURACIÓN (LANEROS GAMER)${NC}"
    echo -e "${BLUE}=============================================================================${NC}"
    echo -e " [1] Generar Respaldo Completo (Backup con Fecha y Hora)"
    echo -e " [2] Restaurar Base de Datos desde un archivo .sql"
    echo -e " [3] Inicializar con Esquema Limpio (database/schema.sql)"
    echo -e " [4] Cargar Datos Semilla de Prueba (database/seeds.sql)"
    echo -e " [5] Salir"
    echo -e "${BLUE}=============================================================================${NC}"
    read -rp "Seleccione una opción [1-5]: " OPCION
}

ejecutar_backup() {
    TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
    BACKUP_FILE="${BACKUP_DIR}/cyberpos_backup_${TIMESTAMP}.sql"

    echo -e "\n${BLUE}[*] Generando respaldo de la base de datos '${DB_NAME}'...${NC}"
    echo -e "${BLUE}[*] Destino: ${BACKUP_FILE}${NC}"

    mysqldump ${MYSQL_AUTH} \
        --routines \
        --triggers \
        --single-transaction \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" > "${BACKUP_FILE}"

    if [ $? -eq 0 ] && [ -s "${BACKUP_FILE}" ]; then
        TAMANO=$(du -h "${BACKUP_FILE}" | cut -f1)
        echo -e "${GREEN}[OK] Respaldo generado exitosamente (${TAMANO}).${NC}"
        echo -e "${GREEN}[*] Archivo: ${BACKUP_FILE}${NC}"
    else
        echo -e "${RED}[ERROR] Falló la creación del respaldo. Verifique conexión a MySQL.${NC}"
    fi
}

ejecutar_restore() {
    echo -e "\n${BLUE}[*] Respaldos disponibles en ${BACKUP_DIR}:${NC}"
    ls -lt "${BACKUP_DIR}"/*.sql 2>/dev/null || echo "No hay respaldos previos."

    echo ""
    read -rp "Ingrese la ruta completa o nombre del archivo .sql a restaurar: " ARCHIVO

    if [ ! -f "$ARCHIVO" ]; then
        if [ -f "${BACKUP_DIR}/${ARCHIVO}" ]; then
            ARCHIVO="${BACKUP_DIR}/${ARCHIVO}"
        else
            echo -e "${RED}[ERROR] El archivo '$ARCHIVO' no existe.${NC}"
            return 1
        fi
    fi

    echo -e "\n${YELLOW}[ADVERTENCIA] La restauración sobrescribirá los datos actuales en '${DB_NAME}'.${NC}"
    read -rp "¿Está seguro de continuar? (s/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[sS]$ ]]; then
        echo -e "${YELLOW}[*] Operación cancelada por el usuario.${NC}"
        return 0
    fi

    echo -e "${BLUE}[*] Restaurando base de datos desde ${ARCHIVO}...${NC}"
    mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 "${DB_NAME}" < "${ARCHIVO}"
    echo -e "${GREEN}[OK] Base de datos restaurada correctamente.${NC}"
}

ejecutar_schema() {
    SCHEMA_FILE="${ROOT_DIR}/database/schema.sql"
    echo -e "\n${BLUE}[*] Aplicando esquema DDL limpio (${SCHEMA_FILE})...${NC}"
    mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 < "${SCHEMA_FILE}"
    echo -e "${GREEN}[OK] Esquema de base de datos creado exitosamente.${NC}"
}

ejecutar_seeds() {
    SEEDS_FILE="${ROOT_DIR}/database/seeds.sql"
    echo -e "\n${BLUE}[*] Sembrando datos iniciales (${SEEDS_FILE})...${NC}"
    mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 "${DB_NAME}" < "${SEEDS_FILE}"
    echo -e "${GREEN}[OK] Datos semilla cargados exitosamente.${NC}"
}

# Procesar parámetros directos (modo CLI sin menú)
case "${1:-}" in
    backup)
        ejecutar_backup
        exit 0
        ;;
    restore)
        if [ -z "${2:-}" ]; then
            echo -e "${RED}Uso: $0 restore <ruta_archivo.sql>${NC}"
            exit 1
        fi
        ARCHIVO="$2"
        mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 "${DB_NAME}" < "${ARCHIVO}"
        echo -e "${GREEN}[OK] Restaurado.${NC}"
        exit 0
        ;;
    schema)
        ejecutar_schema
        exit 0
        ;;
    seeds)
        ejecutar_seeds
        exit 0
        ;;
    *)
        # Modo interactivo
        while true; do
            mostrar_menu
            case "$OPCION" in
                1) ejecutar_backup; read -rp "Presione Enter para continuar...";;
                2) ejecutar_restore; read -rp "Presione Enter para continuar...";;
                3) ejecutar_schema; read -rp "Presione Enter para continuar...";;
                4) ejecutar_seeds; read -rp "Presione Enter para continuar...";;
                5) echo -e "${GREEN}¡Hasta luego!${NC}"; exit 0;;
                *) echo -e "${RED}Opción inválida.${NC}"; sleep 1;;
            esac
        done
        ;;
esac

