-- =============================================================================
-- Hotfix Evaluación VII para CyberPOS 2.0 (Laneros Gamer)
-- Archivo: database/hotfix_produccion.sql
-- Ejecutar sobre una base de datos ya existente sin borrar sus datos.
-- =============================================================================

USE `cyberpos`;

-- 1. Modificar columna numero en equipos para que sea NOT NULL DEFAULT '00'
ALTER TABLE `equipos`
  MODIFY COLUMN `numero` VARCHAR(10) NOT NULL DEFAULT '00';

-- 2. Asegurar ENUMs en equipos
ALTER TABLE `equipos`
  MODIFY COLUMN `tipo` ENUM('pc', 'consola') NOT NULL DEFAULT 'pc',
  MODIFY COLUMN `estado` ENUM('inactiva', 'activa', 'finalizada') NOT NULL DEFAULT 'inactiva';

-- 3. Agregar columna cliente_id y clave foránea en equipos (idempotente)
SET @col_exists = (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'equipos'
    AND COLUMN_NAME = 'cliente_id'
);

SET @sql_add_col = IF(@col_exists = 0,
  'ALTER TABLE `equipos` ADD COLUMN `cliente_id` INT NULL DEFAULT NULL AFTER `cliente`',
  'SELECT 1'
);
PREPARE stmt_col FROM @sql_add_col;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

SET @fk_exists = (
  SELECT COUNT(*)
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME = 'equipos'
    AND CONSTRAINT_NAME = 'fk_equipos_cliente'
);

SET @sql_add_fk = IF(@fk_exists = 0,
  'ALTER TABLE `equipos` ADD CONSTRAINT `fk_equipos_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE, ADD INDEX `idx_equipos_cliente` (`cliente_id`)',
  'SELECT 1'
);
PREPARE stmt_fk FROM @sql_add_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- 4. Asegurar ENUM de tipo_registro en sesiones_uso
ALTER TABLE `sesiones_uso`
  MODIFY COLUMN `tipo_registro` ENUM('alquiler_tiempo', 'venta_snack') NOT NULL;
