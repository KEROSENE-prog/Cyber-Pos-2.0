-- =============================================================================
-- CYBERPOS 2.0 - ESQUEMA DE BASE DE DATOS DDL (schema.sql)
-- Establecimiento: Cyber Gaming "Laneros Gamer"
-- Motor: MySQL 8.0+ / MariaDB 10.4+ (XAMPP Compatible)
-- Codificación: UTF-8 Unicode (utf8mb4 / utf8mb4_unicode_ci)
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `cyberpos`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `cyberpos`;

-- Desactivar temporalmente restricciones para permitir re-creación idempotente
SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO';

-- -----------------------------------------------------------------------------
-- 0. ELIMINACIÓN DE TABLAS PREVIAS (ORDEN DE DEPENDENCIAS)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `pedidos_snacks`;
DROP TABLE IF EXISTS `solicitudes_tiempo`;
DROP TABLE IF EXISTS `sesiones_uso`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `equipos`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `configuracion`;

-- -----------------------------------------------------------------------------
-- 1. TABLA: usuarios
-- Descripción: Gestión de cuentas, roles de acceso y tokens de sesión activa.
-- -----------------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_completo`  VARCHAR(120) NOT NULL,
  `email`            VARCHAR(150) NOT NULL,
  `password`         VARCHAR(255) NOT NULL COMMENT 'Hash seguro generado con password_hash(PASSWORD_BCRYPT)',
  `rol`              ENUM('administrador', 'operador', 'cliente') NOT NULL DEFAULT 'cliente',
  `avatar_url`       TEXT NULL,
  `token`            VARCHAR(64) NULL COMMENT 'Token aleatorio de 48 caracteres hex para autenticación API',
  `token_expira`     DATETIME NULL COMMENT 'Fecha límite de validez del token (TTL por defecto: 7 días)',
  `fecha_creacion`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `uk_usuarios_email` UNIQUE (`email`),
  INDEX `idx_usuarios_token` (`token`),
  INDEX `idx_usuarios_rol` (`rol`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. TABLA: equipos
-- Descripción: Estaciones de juego (PCs gaming y Consolas) con control de tiempo.
-- -----------------------------------------------------------------------------
CREATE TABLE `equipos` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_equipo`       VARCHAR(100) NOT NULL,
  `numero`              VARCHAR(10) NULL COMMENT 'Identificador visual de estación (ej. 01, 02)',
  `tipo`                ENUM('pc', 'consola') NOT NULL DEFAULT 'pc',
  `estado`              ENUM('inactiva', 'activa', 'finalizada') NOT NULL DEFAULT 'inactiva',
  `tiempo_transcurrido` INT NOT NULL DEFAULT 0 COMMENT 'Segundos acumulados antes de la sesión actual',
  `tiempo_limite`       INT NOT NULL DEFAULT 0 COMMENT 'Segundos permitidos contratados (0 = libre / sin límite)',
  `tarifa_por_hora`     DECIMAL(10,2) NOT NULL DEFAULT 1.50 COMMENT 'Precio de alquiler en USD por hora',
  `cliente`             VARCHAR(120) NULL COMMENT 'Nombre o alias del jugador ocupando la estación',
  `fecha_inicio`        DATETIME NULL COMMENT 'Momento en que inició la corrida activa del cronómetro',
  `notificado`          TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si ya se avisó al cliente fin de tiempo',
  `fecha_creacion`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_equipos_estado` (`estado`),
  INDEX `idx_equipos_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. TABLA: productos
-- Descripción: Inventario de snacks, bebidas y servicios disponibles en el cyber.
-- -----------------------------------------------------------------------------
CREATE TABLE `productos` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`          VARCHAR(150) NOT NULL,
  `precio`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Precio de venta al público en USD',
  `stock`           INT NOT NULL DEFAULT 0 COMMENT 'Cantidad de unidades disponibles para venta física',
  `url_imagen`      TEXT NULL,
  `categoria`       VARCHAR(60) NOT NULL DEFAULT 'otro' COMMENT 'bebidas, comida, servicios, golosinas',
  `origen`          ENUM('snacks', 'personalizados') NOT NULL DEFAULT 'snacks',
  `fecha_creacion`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_productos_origen` (`origen`),
  INDEX `idx_productos_categoria` (`categoria`),
  INDEX `idx_productos_stock` (`stock`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. TABLA: sesiones_uso
-- Descripción: Registro de auditoría financiera (alquiler de tiempo y ventas POS).
-- -----------------------------------------------------------------------------
CREATE TABLE `sesiones_uso` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`           INT NULL COMMENT 'ID de la estación asociada (NULL para venta directa en mostrador)',
  `equipo_nombre`       VARCHAR(100) NULL,
  `cliente`             VARCHAR(120) NULL,
  `segundos_alquilados` INT NOT NULL DEFAULT 0,
  `monto_cobrado`       DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto facturado en USD',
  `tipo_registro`       ENUM('alquiler_tiempo', 'venta_snack') NOT NULL DEFAULT 'venta_snack',
  `detalle`             TEXT NULL COMMENT 'Detalle JSON de productos facturados o desglose del servicio',
  `fecha_registro`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sesiones_equipo`
    FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_sesiones_fecha` (`fecha_registro`),
  INDEX `idx_sesiones_tipo` (`tipo_registro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. TABLA: solicitudes_tiempo
-- Descripción: Solicitudes de recarga o tiempo adicional enviadas por clientes.
-- -----------------------------------------------------------------------------
CREATE TABLE `solicitudes_tiempo` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`          INT NULL,
  `cliente_id`         INT NULL,
  `cliente_nombre`     VARCHAR(120) NULL,
  `minutos`            INT NOT NULL DEFAULT 0 COMMENT 'Minutos de juego solicitados',
  `monto`              DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Monto a pagar en USD',
  `estado`             ENUM('pendiente', 'esperando_cliente', 'esperando_admin', 'completada', 'cancelada')
                        NOT NULL DEFAULT 'pendiente',
  `confirmado_admin`   TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 si el operador/admin validó el pago',
  `confirmado_cliente` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_solicitudes_equipo`
    FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  CONSTRAINT `fk_solicitudes_cliente`
    FOREIGN KEY (`cliente_id`)
    REFERENCES `usuarios` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_solicitudes_estado` (`estado`),
  INDEX `idx_solicitudes_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. TABLA: pedidos_snacks
-- Descripción: Pedidos de comida y bebidas realizados desde la estación de juego.
-- -----------------------------------------------------------------------------
CREATE TABLE `pedidos_snacks` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`      INT NULL,
  `equipo_nombre`  VARCHAR(100) NULL,
  `cliente_nombre` VARCHAR(120) NULL,
  `items`          JSON NULL COMMENT 'Arreglo JSON con los items, cantidades y precios unitarios',
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total de la orden en USD',
  `estado`         ENUM('pendiente', 'completado', 'cancelado') NOT NULL DEFAULT 'pendiente',
  `fecha`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_pedidos_equipo`
    FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_pedidos_estado` (`estado`),
  INDEX `idx_pedidos_fecha` (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. TABLA: configuracion
-- Descripción: Parámetros globales del establecimiento (fila singleton id = 1).
-- -----------------------------------------------------------------------------
CREATE TABLE `configuracion` (
  `id`                        INT NOT NULL PRIMARY KEY COMMENT 'Identificador único de configuración (siempre 1)',
  `tasa_bcv`                  DECIMAL(10,2) NOT NULL DEFAULT 36.85 COMMENT 'Tasa oficial del BCV en Bolívares por USD',
  `tasa_manual`               TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0: sincronización vía API BCV; 1: fijada manualmente',
  `nombre_cyber`              VARCHAR(150) NOT NULL DEFAULT 'CyberPOS Laneros Gamer',
  `precio_hora_pc`            DECIMAL(10,2) NOT NULL DEFAULT 1.50 COMMENT 'Precio base hora PC en USD',
  `precio_hora_consola`       DECIMAL(10,2) NOT NULL DEFAULT 2.00 COMMENT 'Precio base hora Consola en USD',
  `ultima_actualizacion_tasa` DATETIME NULL,
  `theme_color`               VARCHAR(20) NOT NULL DEFAULT '#35c029' COMMENT 'Color acento del branding (#35c029)',
  `avatar_url`                TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Restaurar configuraciones previas de integridad
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
SET SQL_MODE=@OLD_SQL_MODE;

