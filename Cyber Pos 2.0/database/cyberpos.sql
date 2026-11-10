-- =============================================================================
-- CyberPOS - Base de datos (MySQL / MariaDB)
-- Instituto Universitario Jesus Obrero (IUJO)
--
-- Importar con:
--   mysql -u root -p < database/cyberpos.sql
-- o desde phpMyAdmin importando este archivo.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `cyberpos`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `cyberpos`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `sesiones_uso`;
DROP TABLE IF EXISTS `solicitudes_tiempo`;
DROP TABLE IF EXISTS `pedidos_snacks`;
DROP TABLE IF EXISTS `productos`;
DROP TABLE IF EXISTS `equipos`;
DROP TABLE IF EXISTS `usuarios`;
DROP TABLE IF EXISTS `configuracion`;
SET FOREIGN_KEY_CHECKS = 1;

-- -----------------------------------------------------------------------------
-- Usuarios (autenticacion y roles)
-- -----------------------------------------------------------------------------
CREATE TABLE `usuarios` (
  `id`               INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_completo`  VARCHAR(120) NOT NULL,
  `email`            VARCHAR(150) NOT NULL UNIQUE,
  `password`         VARCHAR(255) NOT NULL,
  `rol`              ENUM('administrador','cliente') NOT NULL DEFAULT 'cliente',
  `avatar_url`       TEXT NULL,
  `token`            VARCHAR(64) NULL,
  `token_expira`     DATETIME NULL,
  `fecha_creacion`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Equipos (PC / Consolas) y control de tiempo
-- -----------------------------------------------------------------------------
CREATE TABLE `equipos` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_equipo`      VARCHAR(100) NOT NULL,
  `numero`             VARCHAR(10) NOT NULL DEFAULT '00',
  `tipo`               ENUM('pc','consola') NOT NULL DEFAULT 'pc',
  `estado`             ENUM('inactiva','activa','finalizada') NOT NULL DEFAULT 'inactiva',
  `tiempo_transcurrido` INT NOT NULL DEFAULT 0,
  `tiempo_limite`      INT NOT NULL DEFAULT 0,
  `tarifa_por_hora`    DECIMAL(10,2) NOT NULL DEFAULT 1.50,
  `cliente`            VARCHAR(120) NULL,
  `cliente_id`         INT NULL DEFAULT NULL,
  `fecha_inicio`       DATETIME NULL,
  `notificado`         TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_equipos_cliente`
    FOREIGN KEY (`cliente_id`)
    REFERENCES `usuarios` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE,
  INDEX `idx_equipos_estado` (`estado`),
  INDEX `idx_equipos_tipo` (`tipo`),
  INDEX `idx_equipos_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Productos (snacks estandar + catalogo personalizado)
-- -----------------------------------------------------------------------------
CREATE TABLE `productos` (
  `id`              INT AUTO_INCREMENT PRIMARY KEY,
  `nombre`          VARCHAR(150) NOT NULL,
  `precio`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `stock`           INT NOT NULL DEFAULT 0,
  `url_imagen`      TEXT NULL,
  `categoria`       VARCHAR(60) NOT NULL DEFAULT 'otro',
  `origen`          ENUM('snacks','personalizados') NOT NULL DEFAULT 'snacks',
  `fecha_creacion`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sesiones de uso (alquiler de tiempo + ventas directas)
-- -----------------------------------------------------------------------------
CREATE TABLE `sesiones_uso` (
  `id`                  INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`           INT NULL,
  `equipo_nombre`       VARCHAR(100) NULL,
  `cliente`             VARCHAR(120) NULL,
  `segundos_alquilados` INT NOT NULL DEFAULT 0,
  `monto_cobrado`       DECIMAL(10,2) NOT NULL DEFAULT 0,
  `tipo_registro`       ENUM('alquiler_tiempo','venta_snack') NOT NULL,
  `detalle`             TEXT NULL,
  `fecha_registro`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_sesion_equipo` FOREIGN KEY (`equipo_id`)
    REFERENCES `equipos`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Solicitudes de tiempo (flujo cliente <-> admin)
-- -----------------------------------------------------------------------------
CREATE TABLE `solicitudes_tiempo` (
  `id`                 INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`          INT NULL,
  `cliente_id`         INT NULL,
  `cliente_nombre`     VARCHAR(120) NULL,
  `minutos`            INT NOT NULL DEFAULT 0,
  `monto`              DECIMAL(10,2) NOT NULL DEFAULT 0,
  `estado`             ENUM('pendiente','esperando_cliente','esperando_admin','completada','cancelada')
                        NOT NULL DEFAULT 'pendiente',
  `confirmado_admin`   TINYINT(1) NOT NULL DEFAULT 0,
  `confirmado_cliente` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha`              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Pedidos de snacks
-- -----------------------------------------------------------------------------
CREATE TABLE `pedidos_snacks` (
  `id`             INT AUTO_INCREMENT PRIMARY KEY,
  `equipo_id`      INT NULL,
  `equipo_nombre`  VARCHAR(100) NULL,
  `cliente_nombre` VARCHAR(120) NULL,
  `items`          JSON NULL,
  `total`          DECIMAL(10,2) NOT NULL DEFAULT 0,
  `estado`         ENUM('pendiente','completado','cancelado') NOT NULL DEFAULT 'pendiente',
  `fecha`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Configuracion global (fila unica id = 1)
-- -----------------------------------------------------------------------------
CREATE TABLE `configuracion` (
  `id`                        INT PRIMARY KEY,
  `tasa_bcv`                  DECIMAL(10,2) NOT NULL DEFAULT 36.85,
  `tasa_manual`               TINYINT(1) NOT NULL DEFAULT 0,
  `nombre_cyber`              VARCHAR(150) NOT NULL DEFAULT 'CyberPOS Laneros Gamer',
  `precio_hora_pc`            DECIMAL(10,2) NOT NULL DEFAULT 1.50,
  `precio_hora_consola`       DECIMAL(10,2) NOT NULL DEFAULT 2.00,
  `ultima_actualizacion_tasa` DATETIME NULL,
  `theme_color`               VARCHAR(20) NOT NULL DEFAULT '#35c029',
  `avatar_url`                TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DATOS INICIALES (DEMO)
-- Contrasenas: admin123 / cliente123
-- =============================================================================

INSERT INTO `usuarios` (`id`,`nombre_completo`,`email`,`password`,`rol`,`avatar_url`,`fecha_creacion`) VALUES
(1,'Administrador Laneros','admin@laneros.com','$2y$10$EK32893gyQJDV1lUtMyedOPHy30.AdKPZH3dk1M5mx4s3bZ0sTRQ.','administrador','','2026-10-01 10:00:00'),
(2,'Kelvin Nieto','kelvin@laneros.com','$2y$10$EK32893gyQJDV1lUtMyedOPHy30.AdKPZH3dk1M5mx4s3bZ0sTRQ.','administrador','','2026-10-01 10:00:00'),
(3,'Cliente Gamer Demo','cliente@laneros.com','$2y$10$xR/ncVAi7Y197PU4r7dDYuuZxkPILz49EDKCYdS1jNh1xW8VRRoSW','cliente','','2026-10-01 10:00:00');

INSERT INTO `equipos` (`id`,`nombre_equipo`,`numero`,`tipo`,`estado`,`tiempo_transcurrido`,`tiempo_limite`,`tarifa_por_hora`,`cliente`,`cliente_id`,`notificado`) VALUES
(1,'PC 01','01','pc','inactiva',0,0,1.50,'',NULL,0),
(2,'PC 02','02','pc','inactiva',0,0,1.50,'',NULL,0),
(3,'PC 03','03','pc','inactiva',0,0,1.50,'',NULL,0),
(4,'PC 04','04','pc','inactiva',0,0,1.50,'',NULL,0),
(5,'PC 05','05','pc','inactiva',0,0,1.50,'',NULL,0),
(6,'PC 06','06','pc','inactiva',0,0,1.50,'',NULL,0),
(7,'PlayStation 5 - VIP 01','07','consola','activa',0,7200,2.50,'Cliente Gamer Demo',3,0),
(8,'PlayStation 5 - VIP 02','08','consola','inactiva',0,0,2.50,'',NULL,0),
(9,'PlayStation 5 - Arena 03','09','consola','inactiva',0,0,2.00,'',NULL,0),
(10,'PlayStation 5 - Arena 04','10','consola','inactiva',0,0,2.00,'',NULL,0);

INSERT INTO `productos` (`id`,`nombre`,`precio`,`stock`,`categoria`,`origen`) VALUES
(1,'Monster Energy Ultra White (473ml)',2.50,24,'bebidas','snacks'),
(2,'Red Bull Energy Drink (250ml)',2.75,20,'bebidas','snacks'),
(3,'Coca-Cola Sabor Original (500ml)',1.75,35,'bebidas','snacks'),
(4,'Pepsi Black Zero Azúcar (500ml)',1.50,28,'bebidas','snacks'),
(5,'Agua Mineral Minalba (600ml)',1.00,50,'bebidas','snacks'),
(6,'Doritos Mega Queso (120g)',1.80,26,'comida','snacks'),
(7,'Papas Lays Clásicas (115g)',1.50,22,'comida','snacks'),
(8,'Chocolate Snickers Bar (52g)',1.00,40,'comida','snacks'),
(9,'Galletas Oreo Original (117g)',1.25,35,'comida','snacks'),
(10,'Sandwich Tostado Jamón y Queso Gamer',3.00,16,'comida','personalizados'),
(11,'Hamburguesa Laneros Doble Carne',4.50,14,'comida','personalizados'),
(12,'Servicio Impresión B/N (Hoja Carta)',0.20,500,'servicios','personalizados');

INSERT INTO `configuracion` (`id`,`tasa_bcv`,`tasa_manual`,`nombre_cyber`,`precio_hora_pc`,`precio_hora_consola`,`theme_color`) VALUES
(1,36.85,0,'CyberPOS Laneros Gamer',1.50,2.00,'#35c029');
