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
  `numero`             VARCHAR(10) NULL,
  `tipo`               ENUM('pc','consola') NOT NULL DEFAULT 'pc',
  `estado`             ENUM('inactiva','activa','finalizada') NOT NULL DEFAULT 'inactiva',
  `tiempo_transcurrido` INT NOT NULL DEFAULT 0,   -- segundos acumulados (congelados)
  `tiempo_limite`      INT NOT NULL DEFAULT 0,     -- segundos permitidos (0 = sin limite)
  `tarifa_por_hora`    DECIMAL(10,2) NOT NULL DEFAULT 1.50,
  `cliente`            VARCHAR(120) NULL,
  `fecha_inicio`       DATETIME NULL,              -- inicio de la corrida activa
  `notificado`         TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_creacion`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  `tipo_registro`       ENUM('alquiler_tiempo','venta_snack') NOT NULL DEFAULT 'venta_snack',
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

INSERT INTO `equipos` (`id`,`nombre_equipo`,`numero`,`tipo`,`estado`,`tiempo_transcurrido`,`tiempo_limite`,`tarifa_por_hora`,`cliente`,`notificado`) VALUES
(1,'PC 01','01','pc','inactiva',0,0,1.50,'',0),
(2,'PC 02','02','pc','inactiva',0,0,1.50,'',0),
(3,'PC 03','03','pc','inactiva',0,0,1.50,'',0),
(4,'PC 04','04','pc','inactiva',0,0,1.50,'',0),
(5,'PC 05','05','pc','inactiva',0,0,1.50,'',0),
(6,'PC 06','06','pc','inactiva',0,0,1.50,'',0),
(7,'Consola 01','07','consola','inactiva',0,0,2.00,'',0),
(8,'Consola 02','08','consola','inactiva',0,0,2.00,'',0),
(9,'Consola 03','09','consola','inactiva',0,0,2.00,'',0),
(10,'Consola 04','10','consola','inactiva',0,0,2.00,'',0);

INSERT INTO `productos` (`id`,`nombre`,`precio`,`stock`,`url_imagen`,`categoria`,`origen`,`fecha_creacion`) VALUES
(1,'Monster Energy','2.50',24,'https://images.unsplash.com/photo-1622543925917-763c34d1a86e?w=500&q=80','bebidas','snacks','2026-10-01 10:00:00'),
(2,'Lays Papas','1.50',15,'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=500&q=80','comida','snacks','2026-10-01 10:00:00'),
(3,'Snickers','1.00',30,'https://images.unsplash.com/photo-1570145820259-b5b80c5c8bd6?w=500&q=80','comida','snacks','2026-10-01 10:00:00'),
(4,'Coca Cola 500ml','1.75',20,'https://images.unsplash.com/photo-1554866585-cd94860890b7?w=500&q=80','bebidas','snacks','2026-10-01 10:00:00'),
(5,'Doritos Mega Queso','1.80',18,'https://images.unsplash.com/photo-1600952841320-db92ec4047ca?w=500&q=80','comida','snacks','2026-10-01 10:00:00'),
(6,'Agua Mineral 600ml','1.00',45,'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=500&q=80','bebidas','snacks','2026-10-01 10:00:00'),
(7,'Oreo','1.25',35,'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=500&q=80','comida','snacks','2026-10-01 10:00:00'),
(8,'Hamburguesa Doble Gamer','4.50',12,'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=500&q=80','comida','snacks','2026-10-01 10:00:00');

INSERT INTO `sesiones_uso` (`equipo_id`,`equipo_nombre`,`cliente`,`segundos_alquilados`,`monto_cobrado`,`tipo_registro`,`fecha_registro`) VALUES
(1,'PC 01','Kelvin',7200,3.00,'alquiler_tiempo',NOW()),
(3,'PC 03','Carlos',10800,4.50,'alquiler_tiempo',NOW()),
(7,'Consola 01','Andres',7200,4.00,'alquiler_tiempo',NOW()),
(NULL,'Venta Directa','Publico',0,15.00,'venta_snack',NOW()),
(NULL,'Venta Directa','Publico',0,423.50,'venta_snack',NOW());

INSERT INTO `configuracion`
  (`id`,`tasa_bcv`,`tasa_manual`,`nombre_cyber`,`precio_hora_pc`,`precio_hora_consola`,`ultima_actualizacion_tasa`,`theme_color`,`avatar_url`)
VALUES
  (1,36.85,0,'CyberPOS Laneros Gamer',1.50,2.00,NOW(),'#35c029','');