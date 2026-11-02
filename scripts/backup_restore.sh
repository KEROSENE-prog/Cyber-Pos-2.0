#!/usr/bin/env bash
# Script de respaldo y restauracion de base de datos para CyberPOS (Linux / Mac / Git Bash)

set -e

# Colores en consola
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BACKUP_DIR="${ROOT_DIR}/backups"

# Leer variables del .env si existe
ENV_FILE="${ROOT_DIR}/.env"
if [ -f "$ENV_FILE" ]; then
    export $(grep -v '^#' "$ENV_FILE" | xargs -d '\n' 2>/dev/null || true)
fi

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-cyberpos}"
DB_USER="${DB_USER:-root}"
DB_PASS="${DB_PASS:-}"

mkdir -p "$BACKUP_DIR"

MYSQL_AUTH="-h ${DB_HOST} -P ${DB_PORT} -u ${DB_USER}"
if [ -n "$DB_PASS" ]; then
    MYSQL_AUTH="${MYSQL_AUTH} -p${DB_PASS}"
fi

mostrar_menu() {
    clear
    echo -e "${BLUE}=============================================================================${NC}"
    echo -e "${GREEN}   CYBERPOS - GESTOR DE BASE DE DATOS (LANEROS GAMER)${NC}"
    echo -e "${BLUE}=============================================================================${NC}"
    echo -e " [1] Hacer Respaldo Completo (con fecha y hora)"
    echo -e " [2] Restaurar una copia desde archivo .sql"
    echo -e " [3] Limpiar y crear tablas de nuevo (schema.sql)"
    echo -e " [4] Cargar datos de prueba iniciales (seeds.sql)"
    echo -e " [5] Salir"
    echo -e "${BLUE}=============================================================================${NC}"
    read -rp "Elige una opcion [1-5]: " OPCION
}

ejecutar_backup() {
    TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
    BACKUP_FILE="${BACKUP_DIR}/cyberpos_backup_${TIMESTAMP}.sql"

    echo -e "\n${BLUE}[*] Respaldando '${DB_NAME}' en:${NC} ${BACKUP_FILE}"

    mysqldump ${MYSQL_AUTH} \
        --routines \
        --triggers \
        --single-transaction \
        --default-character-set=utf8mb4 \
        "${DB_NAME}" > "${BACKUP_FILE}"

    if [ $? -eq 0 ] && [ -s "${BACKUP_FILE}" ]; then
        TAMANO=$(du -h "${BACKUP_FILE}" | cut -f1)
        echo -e "${GREEN}[OK] Respaldo generado con exito (${TAMANO}).${NC}"
    else
        echo -e "${RED}[ERROR] No se pudo generar el respaldo. Revisa la conexion a MySQL.${NC}"
    fi
}

ejecutar_restore() {
    echo -e "\n${BLUE}[*] Respaldos disponibles en ${BACKUP_DIR}:${NC}"
    ls -lt "${BACKUP_DIR}"/*.sql 2>/dev/null || echo "No hay respaldos guardados."

    echo ""
    read -rp "Escribe el nombre o ruta del archivo .sql a restaurar: " ARCHIVO

    if [ ! -f "$ARCHIVO" ]; then
        if [ -f "${BACKUP_DIR}/${ARCHIVO}" ]; then
            ARCHIVO="${BACKUP_DIR}/${ARCHIVO}"
        else
            echo -e "${RED}[ERROR] El archivo '$ARCHIVO' no existe.${NC}"
            return 1
        fi
    fi

    echo -e "\n${YELLOW}[AVISO] Esto sobreescribira los datos de '${DB_NAME}'.${NC}"
    read -rp "Deseas continuar? (s/N): " CONFIRM
    if [[ ! "$CONFIRM" =~ ^[sS]$ ]]; then
        echo -e "${YELLOW}[*] Operacion cancelada.${NC}"
        return 0
    fi

    echo -e "${BLUE}[*] Restaurando desde ${ARCHIVO}...${NC}"
    mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 "${DB_NAME}" < "${ARCHIVO}"
    echo -e "${GREEN}[OK] Base de datos restaurada correctamente.${NC}"
}

ejecutar_schema() {
    SCHEMA_FILE="${ROOT_DIR}/database/schema.sql"
    echo -e "\n${BLUE}[*] Creando estructura de tablas...${NC}"
    mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 < "${SCHEMA_FILE}"
    echo -e "${GREEN}[OK] Esquema aplicado con exito.${NC}"
}

ejecutar_seeds() {
    SEEDS_FILE="${ROOT_DIR}/database/seeds.sql"
    echo -e "\n${BLUE}[*] Cargando datos iniciales...${NC}"
    mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 "${DB_NAME}" < "${SEEDS_FILE}"
    echo -e "${GREEN}[OK] Datos de prueba cargados correctamente.${NC}"
}

case "${1:-}" in
    backup)
        ejecutar_backup
        exit 0
        ;;
    restore)
        if [ -z "${2:-}" ]; then
            echo -e "${RED}Uso: $0 restore <archivo.sql>${NC}"
            exit 1
        fi
        mysql ${MYSQL_AUTH} --default-character-set=utf8mb4 "${DB_NAME}" < "$2"
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
        while true; do
            mostrar_menu
            case "$OPCION" in
                1) ejecutar_backup; read -rp "Presiona Enter para continuar...";;
                2) ejecutar_restore; read -rp "Presiona Enter para continuar...";;
                3) ejecutar_schema; read -rp "Presiona Enter para continuar...";;
                4) ejecutar_seeds; read -rp "Presiona Enter para continuar...";;
                5) echo -e "${GREEN}Listo.${NC}"; exit 0;;
                *) echo -e "${RED}Opcion no valida.${NC}"; sleep 1;;
            esac
        done
        ;;
esac
